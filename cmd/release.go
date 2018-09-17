// Copyright © 2018 彭博 <pengbo@coding.net>
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

package cmd

import (
	"fmt"
	"io"
	"net/url"
	"os"
	"reflect"
	"regexp"
	"strconv"
	"strings"
	"text/template"
	"time"

	"e.coding.net/codingcorp/coding-cli/pkg/model"

	"e.coding.net/codingcorp/coding-cli/pkg/request"
	"github.com/spf13/cobra"
)

const (
	defaultBranchURI = "/api/user/codingcorp/project/coding-dev/git/branches/default"
	commitDetailURI  = "/api/user/codingcorp/project/coding-dev/git/commit/%s"
	diffURI          = "/api/user/codingcorp/project/coding-dev/git/compare_v2?source=%s&target=%s&w=&prefix="
	mergeURI         = "/api/user/codingcorp/project/coding-dev/git/merge/%d"
	gitBlobURI       = "/api/user/codingcorp/project/coding-dev/git/blob/%s"
	currentUserURI   = "/api/current_user"
	diffTemplate     = "/p/coding-dev/git/compare/%s...%s"
)

// 改动文件正则与服务间的关系，优先匹配前面的规则
// 没有值的匹配到结果则为对应的服务名，例如 repo-auth-server
var patterns = []string{
	`coding-front-v2/apps/share`,
	`coding-front-v2/apps/assets`,
	`coding-front-v2/apps/enterprise`,
	`coding-front-v2/apps/platform`,
	`coding-front/main`,
	`coding-front/admin`,
	`coding-front/e-admin`,
	`common/sdk/nexus`,
	`nexus-server`,
	`e-build-artifact`,
	`common`,
	`shared-business`,
	`e-`,
	`shared-testing`,
	`lib-`,
	`coding-`,
	`git-standalone`,
	`tweet-standalone`,
	`core-standalone`,
	`repo-auth-server`,
	`go-git-server`,
	`git-svn-server`,
	`md2html`,
	`e-scheduler`,
	`svn-server`,
	`v2-front-proxy`,
	`webhook-listener`,
	`message`,
	`gravatar`,
	`email`,
}

var services = [][]string{
	{"enterprise-front", "platform-front"},
	{"enterprise-front", "platform-front"},
	{"enterprise-front"},
	{"platform-front"},
	{"front"},
	{"admin"},
	{"e-admin"},
	{"nexus-server"},
	{"nexus-server"},
	{"e-build-artifact"},
	{"e-coding", "coding", "scheduler", "e-scheduler"},
	{"e-coding", "coding", "scheduler", "e-scheduler"},
	{"e-coding", "e-scheduler"},
	{"e-coding", "e-scheduler"},
	{"coding", "scheduler"},
	{"coding", "scheduler"},
	{"coding", "scheduler"},
	{"coding", "scheduler"},
	{"coding", "scheduler"},
}

var productOnlyServices = map[string][]string{
	"enterprise-saas": []string{"enterprise-front", "e-admin", "e-build-artifact", "e-coding", "e-scheduler"},
	"professional":    []string{"platform-front", "admin", "coding", "scheduler"},
}

var output string
var rtype string
var product string

// releaseCmd represents the release command
var releaseCmd = &cobra.Command{
	Use:   "release [分支、提交或标签]",
	Short: "创建版本发布",
	Long: `创建版本发布

为分支、提交或标签（简称 ref）创建版本发布，版本发布分为常规发布和紧急修复两类。`,
	Args: cobra.MinimumNArgs(1),
	Run: func(cmd *cobra.Command, args []string) {
		if len(args) == 0 {
			fmt.Fprintln(os.Stderr, "需提供分支名、提交或标签")
			return
		}
		target := args[0]
		source, err := defaultBranchCommitID()
		if err != nil {
			fmt.Fprintf(os.Stderr, "无法获取默认分支, %v\n", err)
			return
		}
		r, err := release(source, target)
		if err != nil {
			fmt.Fprintf(os.Stderr, "无法创建版本发布, %v\n", err)
			return
		}
		// 计算发布版本号
		patch := 1
		if rtype == "hotfix" {
			patch = 2
		}
		r.Release = fmt.Sprintf("%s.%d-%s", time.Now().Format("20060102"), patch, target)
		// 获取当前用户
		user, err := currentUser()
		if err != nil {
			fmt.Fprintf(os.Stderr, "获取负责人失败, %v\n", err)
			return
		}
		r.Principal = user.Name
		// 过滤产品线特有服务
		var otherProductService []string
		if product == "enterprise-saas" {
			otherProductService = productOnlyServices["professional"]
		} else {
			otherProductService = productOnlyServices["enterprise-saas"]
		}
		ns := make([]string, 0)
		for _, s := range r.Services {
			if !contains(otherProductService, s) {
				ns = append(ns, s)
			}
		}
		r.Services = ns
		f := os.Stdout
		shouldSave := len(output) > 0
		if shouldSave {
			f, err = os.Create(output)
		}
		err = r.save(f)
		if err != nil {
			if shouldSave {
				fmt.Fprintf(os.Stderr, "文件保存失败, %v\n", err)
			} else {
				fmt.Fprintf(os.Stderr, "%v", err)
			}
		} else if shouldSave {
			fmt.Printf("版本发布 %s 已保存到 %s\n", r.Release, output)
		}
	},
}

func contains(s interface{}, elem interface{}) bool {
	arrV := reflect.ValueOf(s)
	if arrV.Kind() == reflect.Slice {
		for i := 0; i < arrV.Len(); i++ {
			if arrV.Index(i).Interface() == elem {
				return true
			}
		}
	}
	return false
}

type createRelease struct {
	Changes   []changelog
	Diff      string
	Hotfix    bool
	Principal string
	Milestone string
	Services  []string
	Release   string
	Migration []migration
	Master    string
}

var funcMap = template.FuncMap{
	"inc": func(i int) int {
		return i + 1
	},
}

func (release *createRelease) save(o io.Writer) error {
	outputTpl, err := template.New("output").Funcs(funcMap).Parse(`## ChangeLog

{{range .Changes}}- {{.Title}} #{{.MergeID}}
{{end}}

## Diff

{{.Diff}}

## Checklist

### 发布类型

{{if .Hotfix}}Hotfix{{else}}常规更新{{end}}

### 负责人

@{{.Principal}}

### 版本规划

{{if .Milestone}}{{.Milestone}}{{else}}无{{end}}

### 发布服务

| 应用名称 | 发布镜像 | 执行顺序 |
| ---------- | ---------- | ---------- |
{{range $index, $element := .Services}}| {{$element}} | {{$.Release}} | {{(inc $index)}} |
{{end}}

### 服务配置修改

{{range .Migration}}{{.ScriptName}}
` + "`" + "`" + "`" + `
{{.Script}}
` + "`" + "`" + "`" + `
{{end}}

### 发布后 master 指向

` + "`" + "`" + "`" + `
{{.Master}}
` + "`" + "`" + "`" + `
`)

	if err != nil {
		return fmt.Errorf("构建输出文件模板失败, %v", err)
	}

	err = outputTpl.Execute(o, release)
	if err != nil {
		return fmt.Errorf("执行模板失败, %v", err)
	}

	return nil

}

func currentUser() (*model.User, error) {
	req := request.NewGet(currentUserURI)
	user := model.User{}
	err := req.SendAndUnmarshal(&user)
	if err != nil {
		return nil, fmt.Errorf("获取当前用户失败, %v", err)
	}
	return &user, nil
}

// release 主要包含以下五个部分
// 1. 变更记录（changelog）
// 2. 版本对比（Diff）
// 3. 发布服务列表
// 4. 服务配置更新
func release(src string, target string) (r *createRelease, err error) {
	// ref to Commit ID
	s := src
	t := target
	r = &createRelease{}
	if len(src) != 40 {
		s, err = commitID(src)
	}
	r.Master = s

	if err != nil {
		return nil, err
	}
	if len(target) != 40 {
		t, err = commitID(target)
	}
	if err != nil {
		return nil, err
	}

	// 变更记录
	d, err := diff(s, t)
	if err != nil {
		return nil, err
	}
	r.Changes, err = changelogs(d.Commits)
	if err != nil {
		return nil, err
	}

	// 网页版本对比链接
	r.Diff, err = compareURL(s, t)
	if err != nil {
		return nil, err
	}

	// 变更文件列表中提取需要更新的服务列表
	paths := d.DiffStat.Paths
	names := make([]string, 0)
	for _, path := range paths {
		names = append(names, path.Name)
	}
	r.Services = findServices(names)

	// 不同合并请求中的迁移脚本
	r.Migration, err = migrationScripts(r.Changes)
	if err != nil {
		return nil, fmt.Errorf("获取迁移脚本失败，%v", err)
	}

	return r, nil
}

type migration struct {
	Services   []string
	ScriptName string
	Script     string
}

func file(c model.Commit, n string) (string, error) {
	encodedParams := url.PathEscape(fmt.Sprintf("%s/%s", c.CommitID, n))
	req := request.NewGet(fmt.Sprintf(gitBlobURI, encodedParams))
	blob := model.Blob{}
	err := req.SendAndUnmarshal(&blob)
	if err != nil {
		return "", fmt.Errorf("获取文件内容失败, name: %s, %v", n, err)
	}
	return blob.File.Data, nil
}

func migrationScripts(c []changelog) ([]migration, error) {
	scripts := make([]migration, 0)
	for _, log := range c {
		req := request.NewGet(fmt.Sprintf(mergeURI, log.MergeID))
		merge := model.Merge{}
		err := req.SendAndUnmarshal(&merge)
		if err != nil {
			return nil, fmt.Errorf("获取合并请求失败, mergeID: %d, %v", log.MergeID, err)
		}
		paths := merge.MergeRequest.DiffStat.Paths
		for _, path := range paths {
			if path.Deletions == 0 && path.Insertions > 0 {
				var migrationScripsPrefix = "e-coding/doc/mysql/migrate_script/migration"
				if strings.HasPrefix(path.Name, migrationScripsPrefix) {
					services := findServices([]string{path.Name})
					mrFirstCommit := merge.MergeRequest.Commits[0]
					script, err := file(mrFirstCommit, path.Name)
					if err != nil {
						return nil, fmt.Errorf("获取迁移脚本文件内容失败，mergeID: %d, %v", log.MergeID, err)
					}
					scripts = append(scripts, migration{Services: services, Script: script, ScriptName: path.Name})
				}
			}
		}
	}
	return scripts, nil
}

func findServices(names []string) []string {
	ss := make([]string, 0)
	servicesSize := len(services)
	for _, name := range names {
		index, err := matchPattern(name, patterns)
		if err != nil {
			fmt.Println(err)
			continue
		}
		if index > servicesSize-1 {
			ss = append(ss, patterns[index])
			continue
		}
		ss = append(ss, services[index]...)
	}
	return uniq(ss)
}

func uniq(slices []string) []string {
	keys := make(map[string]bool, 0)
	list := make([]string, 0)
	for _, entry := range slices {
		if _, value := keys[entry]; !value {
			keys[entry] = true
			list = append(list, entry)
		}
	}
	return list
}

func matchPattern(file string, patterns []string) (int, error) {
	for index, pattern := range patterns {
		if strings.HasPrefix(file, pattern) {
			return index, nil
		}
	}
	return -1, fmt.Errorf("无匹配结果, 文件不包含以下前缀\n\t文件：%s\n\t前缀：[%s]", file, strings.Join(patterns, ", "))
}

// changelog 包含 Merge Request 标题、Merge Request 完整信息以及任务完整信息
type changelog struct {
	Title   string
	MergeID int
}

func changelogs(commits []model.Commit) ([]changelog, error) {
	changelogs := make([]changelog, 0)
	for _, commit := range commits {
		msg := commit.AllMessage
		messages := strings.Split(msg, "\n")
		title, _, mergeID := filterTitleAndURL(messages)
		if len(title) == 0 {
			continue
		}
		title = strings.Replace(title, "Merge Request: ", "", 1)
		c := changelog{Title: title}
		if mergeID != 0 {
			c.MergeID = mergeID
		}
		changelogs = append(changelogs, c)
	}
	return changelogs, nil
}

var mergeRequestIDReg = regexp.MustCompile(`merge/(\d+)`)

func mergeRequestID(msg string) int {
	matches := mergeRequestIDReg.FindStringSubmatch(msg)
	if len(matches) == 2 {
		id, err := strconv.Atoi(matches[1])
		if err != nil {
			return 0
		}
		return id
	}
	return 0
}

func filterTitleAndURL(messages []string) (title string, url string, mergeID int) {
	for _, msg := range messages {
		if strings.Index(msg, "Merge Request") != -1 {
			title = msg
			continue
		}
		if strings.Index(msg, "URL") != -1 {
			mergeID = mergeRequestID(msg)
			url = msg
		}
	}
	return title, url, mergeID
}

// compareURL 返回有 src 和 target 对应的 commit 组成的 diff 链接
// 形如：https://codingcorp.coding.net/p/coding-dev/git/compare/master...enterprise-saas
func compareURL(src string, target string) (u string, err error) {
	s := url.PathEscape(src)
	t := url.PathEscape(target)
	u = request.Host + fmt.Sprintf(diffTemplate, s, t)
	return
}

func commitID(ref string) (id string, err error) {
	if ref == "" {
		return "", fmt.Errorf("ref 不能为空")
	}
	req := request.NewGet(fmt.Sprintf(commitDetailURI, ref))
	commit := model.ComplexCommit{}
	err = req.SendAndUnmarshal(&commit)
	if err != nil {
		return "", fmt.Errorf("获取 commit ID 失败, ref: %s, %v", ref, err)
	}
	return commit.CommitDetail.CommitID, nil
}

func diff(src string, target string) (d *model.Diff, err error) {
	req := request.NewGet(fmt.Sprintf(diffURI, src, target))
	b := model.Diff{}
	err = req.SendAndUnmarshal(&b)
	if err != nil {
		return nil, err
	}
	return &b, nil
}

func defaultBranchCommitID() (branch string, err error) {
	req := request.NewGet(defaultBranchURI)
	b := model.Branch{}
	err = req.SendAndUnmarshal(&b)
	if err != nil {
		return
	}
	branch = b.Name
	return
}

func init() {
	rootCmd.AddCommand(releaseCmd)
	releaseCmd.Flags().StringVarP(&output, "output", "o", "", "保存到文件")
	releaseCmd.Flags().StringVarP(&rtype, "type", "t", "normal", "发布类型，hotfix - 紧急修复或者 normal - 常规更新")
	releaseCmd.Flags().StringVarP(&product, "product", "p", "enterprise-saas", "产品线，enterprise-saas 或者 professional")
}

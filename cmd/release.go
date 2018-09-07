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
	"net/url"
	"os"
	"regexp"
	"strconv"
	"strings"

	"e.coding.net/codingcorp/coding-cli/pkg/model"

	"e.coding.net/codingcorp/coding-cli/pkg/request"
	"github.com/spf13/cobra"
)

const (
	defaultBranchURI = "/api/user/codingcorp/project/coding-dev/git/branches/default"
	commitDetailURI  = "/api/user/codingcorp/project/coding-dev/git/commit/%s"
	diffURI          = "/api/user/codingcorp/project/coding-dev/git/compare_v2?source=%s&target=%s&w=&prefix="
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

// releaseCmd represents the release command
var releaseCmd = &cobra.Command{
	Use:   "release [分支、提交或标签]",
	Short: "创建版本发布",
	Long: `创建版本发布

为分支、提交或标签（简称 ref）创建版本发布，版本发布分为常规发布和紧急修复两类。
创建版本发布时，默认发布类型为常规更新，可通过 -t(--type) 指定类型。创建必须提供 ref 信息即可。`,
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
		err = release(source, target)
		if err != nil {
			fmt.Fprintf(os.Stderr, "无法创建版本发布, %v\n", err)
			return
		}
	},
}

// release 主要包含以下五个部分
// 1. 变更记录（Changelog）
// 2. 版本对比（Diff）
// 3. 发布服务列表
// 4. 服务配置更新
func release(src string, target string) (err error) {
	// Ref to Commit ID
	s := src
	t := target
	if len(src) != 40 {
		s, err = commitID(src)
	}
	if err != nil {
		return err
	}
	if len(target) != 40 {
		t, err = commitID(target)
	}
	if err != nil {
		return err
	}
	fmt.Printf("compare: %s(%s)...%s(%s)\n\n", s, src, t, target)

	// Changelog
	d, err := diff(s, t)
	if err != nil {
		return err
	}
	changes, err := changelog(d.Commits)
	if err != nil {
		return err
	}
	size := len(changes)
	for i, c := range changes {
		fmt.Printf("changelog %d/%d: %s #%d\n", i+1, size, c.Title, c.MergeID)
	}

	// Diff
	compareLink, err := compareURL(s, t)
	if err != nil {
		return err
	}
	fmt.Printf("\ncompare link %s\n\n", compareLink)

	// Service from change files
	paths := d.DiffStat.Paths
	names := make([]string, 0)
	for _, path := range paths {
		names = append(names, path.Name)
	}
	fmt.Printf("\nservices %v\n\n", findServices(names))

	return nil
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

// ChangeLog 包含 Merge Request 标题、Merge Request 完整信息以及任务完整信息
type ChangeLog struct {
	Title   string
	MergeID int
}

func changelog(commits []model.Commit) ([]ChangeLog, error) {
	changelogs := make([]ChangeLog, 0)
	for _, commit := range commits {
		msg := commit.AllMessage
		messages := strings.Split(msg, "\n")
		title, _, mergeID := filterTitleAndURL(messages)
		if len(title) == 0 {
			continue
		}
		title = strings.Replace(title, "Merge Request: ", "", 1)
		c := ChangeLog{Title: title}
		if mergeID != 0 {
			c.MergeID = mergeID
		}
		changelogs = append(changelogs, c)
	}
	return changelogs, nil
}

func mergeRequestID(msg string) int {
	mergeRequestIDReg := regexp.MustCompile(`merge/(\d+)`)
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
}

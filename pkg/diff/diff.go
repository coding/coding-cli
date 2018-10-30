package diff

import (
	"fmt"
	"io"
	"os"
	"regexp"
	"strconv"
	"strings"
	"text/template"
	"time"

	"e.coding.net/codingcorp/coding-cli/pkg/api"
	"e.coding.net/codingcorp/coding-cli/pkg/config"
	"e.coding.net/codingcorp/coding-cli/pkg/model"
	"github.com/golang/glog"
)

var funcMap = template.FuncMap{
	"inc": func(i int) int {
		return i + 1
	},
}

//Diff 分析 commit diff 请求
type Diff struct {
	source     string
	target     string
	product    string
	mode       string
	patch      int8
	sourceHash string
	targetHash string
	diff       model.Diff
	context    model.Context
	conf       config.Config
}

//Run post request and analysis commit
func Analysis(
	product string,
	source string,
	target string,
	t string,
	patch int8,
	output string,
	configFile string,
) {
	c := Diff{
		product: product,
		source:  source,
		target:  target,
		mode:    t,
		patch:   patch,
	}

	c.context.Date = time.Now().Format("2006-01-02")

	c.conf = *config.Load(configFile)

	c.context.Master = c.getSourceHash()
	c.context.PostMaster = c.getTargetHash()

	if !c.isClean() {
		glog.Exitln("基于目标分支对比源分支，有目标分支不存在的提交，请使用 merge 或者 rebase 操作保持分支差异干净")
	}

	c.currentUser()
	c.generateTag()
	c.compare()
	c.services()

	f := os.Stdout
	if len(output) > 0 {
		f, _ = os.Create(output)
		c.save(f)
	}
}

func (c *Diff) getSourceHash() string {
	c.sourceHash = c.source
	if len(c.source) != 40 {
		c.sourceHash = api.CommitID(c.source)
	}
	return c.sourceHash
}

func (c *Diff) getTargetHash() string {
	c.targetHash = c.target
	if len(c.target) != 40 {
		c.targetHash = api.CommitID(c.target)
	}
	return c.targetHash
}

func (c *Diff) compare() {

	glog.Infof("正在创建基于 %s(%s)...%s(%s) 的版本发布", c.sourceHash, c.source, c.targetHash, c.target)

	// 变更记录
	c.changelogs()
	glog.Infof("此版本包含 %d 个主要改动", len(c.context.Changes))

	// 网页版本对比链接
	c.context.CompareURL = c.compareURL()
}

//generateTag 生成发布版本标签
func (c *Diff) generateTag() {
	today := time.Now().Format("20060102")
	c.context.Release = fmt.Sprintf("%s.%d-%s", today, c.patch, c.product)
}

func (c *Diff) currentUser() {
	user := api.CurrentUser()
	c.context.CurrentUser = *user
	c.context.Principal = user.Name
}

func (c *Diff) isClean() bool {
	c.diff = *api.Diff(c.targetHash, c.sourceHash)
	return len(c.diff.Commits) <= 0
}

func (c *Diff) changelogs() {
	c.diff = *api.Diff(c.sourceHash, c.targetHash)
	changelogs := make([]model.ChangeLog, 0)
	for _, commit := range c.diff.Commits {
		msg := commit.AllMessage
		messages := strings.Split(msg, "\n")
		title, _, mergeID := filterTitleAndURL(messages)
		if len(title) == 0 {
			continue
		}
		title = strings.Replace(title, "Merge Request: ", "", 1)
		cl := model.ChangeLog{Title: title}
		if mergeID != 0 {
			cl.MergeID = mergeID
		}
		merge := api.Merge(cl.MergeID)
		cl.Merge = *merge
		glog.Infof("提取到合并请求 #%d - %s", mergeID, merge.MergeRequest.Title)
		ref := api.Refer(cl.MergeID)
		if ref.Task != nil {
			taskIDs := make([]int, 0)
			for _, t := range ref.Task {
				taskIDs = append(taskIDs, t.Code)
			}
			cl.TaskIDs = taskIDs
		}
		changelogs = append(changelogs, cl)
	}
	c.context.Changes = changelogs
}

func (c *Diff) compareURL() string {
	return api.CompareURL(c.sourceHash, c.targetHash)
}

func (c *Diff) services() {
	paths := c.paths()
	services := make([]model.Service, 0)
	for _, service := range c.conf.Service {
		changes := make([]model.Path, 0)
		for _, s := range service.Source {
			matches := match(paths, s)
			changes = append(changes, matches...)
		}
		if len(changes) > 0 {
			services = append(services, model.Service{
				Name:        service.Name,
				Migrate:     service.Migrate,
				ChangeFiles: changes,
			})
		}
	}

	for i := range services {
		s := &services[i]
		s.Migration = make([]model.Migration, 0)
		for _, f := range s.ChangeFiles {
			if strings.HasPrefix(f.Path, s.Migrate) && len(s.Migrate) > 0 {
				s.Migration = append(s.Migration, model.Migration{
					ScriptName: f.Path,
					Script:     api.Blob(c.targetHash, f.Name),
				})
			}
		}
	}

	c.context.Staging.Service = services
	c.context.Staging.Name = "Staging"

	c.context.Prod.Service = services
	c.context.Prod.Name = "Production"
}

func (c *Diff) paths() []model.Path {
	paths := make([]model.Path, 0)
	for _, p := range c.diff.DiffStat.Paths {
		paths = append(paths, p)
	}
	return paths
}

func (c *Diff) save(o io.Writer) {
	outputTpl, err := template.New("release.md").Funcs(funcMap).Parse(diffTemplate)

	if err != nil {
		glog.Exitln("构建输出文件模板失败, ", err)
	}

	err = outputTpl.Execute(o, c.context)
	if err != nil {
		glog.Exitln("执行模板失败, ", err)
	}
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

func match(files []model.Path, prefix string) []model.Path {
	paths := make([]model.Path, 0)
	for _, f := range files {
		if strings.HasPrefix(f.Path, prefix) {
			paths = append(paths, f)
		}
	}
	return paths
}

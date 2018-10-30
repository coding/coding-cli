package cmd

import (
	"bytes"
	"html/template"
	"io/ioutil"
	"strings"
	"time"

	"github.com/golang/glog"
	"github.com/spf13/cobra"
)

const (
	featureFlag      = "feature"
	contentFlag      = "content"
	fileNameTemplate = `{{.date}}-{{.feature}}.{{.type}}.{{.ext}}`
)

var content string
var feature string
var append bool

var evnCmd = &cobra.Command{
	Use:   "env",
	Short: "生成环境变量文件",
	Long:  `使用命令行生成环境变量修改的 Checklist 文件`,
	Run: func(cmd *cobra.Command, args []string) {

	},
}

var addEvnCmd = &cobra.Command{
	Use:   "add",
	Short: "新增环境变量",
	Long: `使用命令行生成环境变量新增的 Checklist 文件
示例：coding-cli env add -c "redis.host=17.0.0.1" -f add_redis_host
未指定 --output(-o) 将会在当前目录生成文件 YYYY-MM-DD-add_redis_host.add.env 文件，使用 -a=true 可以在指定 --output 文件中添加内容`,
	Run: func(cmd *cobra.Command, args []string) {
		save(content, "add", "env")
	},
}

var removeEvnCmd = &cobra.Command{
	Use:   "remove",
	Short: "移除环境变量",
	Long: `使用命令行生成环境变量删除的 Checklist 文件
示例：coding-cli env remove -c "redis.host=17.0.0.1" -f remove_redis_host
未指定 --output(-o) 将会在当前目录生成文件 YYYY-MM-DD-remove_redis_host.remove.env 文件，使用 -a=true 可以在指定 --output 文件中添加内容`,
	Run: func(cmd *cobra.Command, args []string) {
		save(content, "remove", "env")
	},
}

var modifyEvnCmd = &cobra.Command{
	Use:   "modify",
	Short: "修改环境变量",
	Long: `使用命令行生成环境变量修改的 Checklist 文件
示例：coding-cli env modify -c "redis.host=17.0.0.1" -f modify_redis_host
未指定 --output(-o) 将会在当前目录生成文件 YYYY-MM-DD-modify_redis_host.modify.env 文件，使用 -a=true 可以在指定 --output 文件中添加内容`,
	Run: func(cmd *cobra.Command, args []string) {
		save(content, "modify", "env")
	},
}

func save(text string, ftype string, ext string) {
	fn := filename(ftype, ext)
	if len(output) > 0 {
		fn = output
	}
	if !append {
		ioutil.WriteFile(fn, []byte(text), 0666)
	} else {
		v, err := ioutil.ReadFile(fn)
		if err != nil {
			glog.Exitln("读取文件失败", fn)
		}
		ioutil.WriteFile(fn, []byte(strings.Join([]string{string(v), content}, "\n")), 0666)
	}
}

func filename(ftype string, ext string) string {
	tpl, err := template.New("filename").Parse(fileNameTemplate)
	if err != nil {
		glog.Exitln("生成文件名失败")
	}
	today := time.Now().Format("2006-01-02")
	var buf bytes.Buffer
	err = tpl.Execute(&buf, map[string]string{
		"date":      today,
		featureFlag: feature,
		"type":      ftype,
		"ext":       ext,
	})
	if err != nil {
		glog.Exitln("生成文件名失败")
	}
	return buf.String()
}

func init() {
	evnCmd.AddCommand(addEvnCmd, removeEvnCmd, modifyEvnCmd)
	rootCmd.AddCommand(evnCmd)

	for _, subCmd := range []*cobra.Command{addEvnCmd, removeEvnCmd, modifyEvnCmd} {
		subCmd.Flags().StringVarP(&content, contentFlag, "c", "", "环境变量名")
		subCmd.Flags().StringVarP(&feature, featureFlag, "f", "", "功能名")
		subCmd.Flags().StringVarP(&output, "output", "o", "", "输出文件")
		subCmd.Flags().BoolVarP(&append, "append", "a", false, "是否覆盖，否则添加到文件中")

		subCmd.MarkFlagRequired(contentFlag)
		subCmd.MarkFlagRequired(featureFlag)
	}
}

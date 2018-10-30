package cmd

import (
	"bytes"
	"github.com/golang/glog"
	"github.com/spf13/cobra"
	"text/template"
)

const (
	sqlTemplate = `{{.content}}`
)

var sqlCmd = &cobra.Command{
	Use:   "sql",
	Short: "生成数据库执行的 SQL 文件",
	Long: `使用命令行生成数据库执行的 SQL 文件
示例：coding-cli sql -c " UPDATE sample SET nickname='tom' WHERE id = 1 " -f update_sample_nickname
将会生成 YYYY-MM-DD-update_sample_nickname.sql 文件
`,
	Run: func(cmd *cobra.Command, args []string) {

		tpl, err := template.New("sqlFile").Parse(sqlTemplate)
		if err != nil {
			glog.Exitln("生成 SQL 文件失败")
		}
		var sqlContent bytes.Buffer
		err = tpl.Execute(&sqlContent, map[string]string{
			contentFlag: content,
		})
		if err != nil {
			glog.Exitln("生成 SQL 文件失败")
		}

		save(sqlContent.String(), "update", "sql")
	},
}

func init() {
	rootCmd.AddCommand(sqlCmd)

	sqlCmd.Flags().StringVarP(&content, contentFlag, "c", "", "SQL 内容")
	sqlCmd.Flags().StringVarP(&feature, featureFlag, "f", "", "功能名")
	sqlCmd.Flags().StringVarP(&output, "output", "o", "", "输出文件")

	sqlCmd.MarkFlagRequired(contentFlag)
	sqlCmd.MarkFlagRequired(featureFlag)
}

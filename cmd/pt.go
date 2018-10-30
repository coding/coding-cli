package cmd

import (
	"bytes"
	"github.com/golang/glog"
	"github.com/spf13/cobra"
	"text/template"
)

const (
	tableFlag     = "table"
	alterFlag     = "alter"
	alterTemplate = `ALTER TABLE {{.table}} {{.alter}}`
	ptTeamplate   = `
#!/usr/bin/env bash


DATABASE_USER=
DATABASE_PASSWORD=
DATABASE_HOST=
DATABASE_PORT=
DATABASE=

pt-online-schema-change --user=$DATABASE_USER --password=$DATABASE_PASSWORD --alter "{{.alter}}" D=$DATABASE,t={{.table}} --host=$DATABASE_HOST:$DATABASE_PORT --execute --charset "utf8mb4"
`
)

var table string
var alter string

var ptCmd = &cobra.Command{
	Use:   "pt",
	Short: "生成 pt-online-schema-change 和 sql 文件",
	Long: `使用命令行生成数据库表修改的生成 pt-online-schema-change 和 sql 文件
示例：coding-cli pt -t sample -a "add column nickname varchar(32) default null comment '昵称' after id" -f sample_table_add_nickname_col
将会生成 YYYY-MM-DD-sample_table_add_nickname_col.pt.sh 和 YYYY-MM-DD-sample_table_add_nickname_col.pt.sql 俩个文件
`,
	Run: func(cmd *cobra.Command, args []string) {

		tpl, err := template.New("pt-bash").Parse(ptTeamplate)
		if err != nil {
			glog.Exitln("生成 pt-online-schema-change bash 文件失败")
		}
		var ptContent bytes.Buffer
		err = tpl.Execute(&ptContent, map[string]string{
			alterFlag: alter,
			tableFlag: table,
		})
		if err != nil {
			glog.Exitln("生成 pt-online-schema-change bash 文件失败")
		}

		save(ptContent.String(), "pt", "sh")

		tpl, err = template.New("pt-sql").Parse(alterTemplate)
		if err != nil {
			glog.Exitln("生成 pt-online-schema-change sql 文件失败")
		}
		var sqlContent bytes.Buffer
		err = tpl.Execute(&sqlContent, map[string]string{
			alterFlag: alter,
			tableFlag: table,
		})
		if err != nil {
			glog.Exitln("生成 pt-online-schema-change sql 文件失败")
		}

		save(sqlContent.String(), "pt", "sql")
	},
}

func init() {
	rootCmd.AddCommand(ptCmd)

	ptCmd.Flags().StringVarP(&alter, alterFlag, "a", "", "修改语句")
	ptCmd.Flags().StringVarP(&table, tableFlag, "t", "", "表名")
	ptCmd.Flags().StringVarP(&feature, featureFlag, "f", "", "功能名")
	ptCmd.Flags().StringVarP(&output, "output", "o", "", "输出文件")

	ptCmd.MarkFlagRequired(alterFlag)
	ptCmd.MarkFlagRequired(tableFlag)
	ptCmd.MarkFlagRequired(featureFlag)
}

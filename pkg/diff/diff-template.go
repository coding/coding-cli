package diff

const diffTemplate = `
# 更新日期
{{.Date}}

# ChangeLog

{{range .Changes}}- {{.Title}}{{range .TaskIDs}} #{{.}}{{end}} #{{.MergeID}}
{{end}}

# Diff

{{.CompareURL}}

# 发布类型

{{if .Hotfix}}Hotfix{{else}}常规更新{{end}}

# 负责人

@{{.Principal}}

# 版本规划

{{if .Milestone}}{{.Milestone}}{{else}}无{{end}}

----------

# Staging 更新服务和配置修改

## Staging 更新服务

| 应用名称 | 发布镜像 | 执行顺序 |
| ---------- | ---------- | ---------- |
{{range $index, $element := .Staging.Service}}| {{.Name}} | {{$.Release}} | {{(inc $index)}} |
{{end}}

## Staging 服务配置和数据库更新
{{range .Staging.Service}}
{{if (len .Migration) ge 0}}
### 服务名： {{.Name}}
{{range .Migration}}
###### 文件： {{.ScriptName}}
` + "`" + "`" + "`" + `
{{.Script}}
` + "`" + "`" + "`" + `
{{end}}
{{end}}
{{end}}

-------------

# Production 更新服务和配置修改

## Production 更新服务

| 应用名称 | 发布镜像 | 执行顺序 |
| ---------- | ---------- | ---------- |
{{range $index, $element := .Prod.Service}}| {{.Name}} | {{$.Release}} | {{(inc $index)}} |
{{end}}

## Production 服务配置和数据库更新

{{range $.Prod.Service}}
{{if (len .Migration) ge 0}}
### 服务名： {{.Name}}
{{range .Migration}}
###### 文件： {{.ScriptName}}
` + "`" + "`" + "`" + `
{{.Script}}
` + "`" + "`" + "`" + `
{{end}}
{{end}}
{{end}}

-----------

# 发布后 master 指向

` + "`" + "`" + "`" + `
{{.PostMaster}}
` + "`" + "`" + "`" + `
`

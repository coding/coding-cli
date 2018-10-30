package model

//Context Release 上下文
type Context struct {
	CurrentUser User
	Changes     []ChangeLog
	CompareURL  string
	Hotfix      bool
	Principal   string
	Date        string
	Milestone   string
	Release     string
	Staging     Deploy
	Prod        Deploy
	Master      string
	PostMaster  string
}

//ChangeLog 包含 Merge Request 标题、Merge Request 完整信息以及任务完整信息
type ChangeLog struct {
	Title   string
	MergeID int
	TaskIDs []int
	Merge   Merge
}

//Migration 需要执行的 sql 或者 配置改动
type Migration struct {
	ScriptName string
	Script     string
}

//Service 更新的服务
type Service struct {
	Name        string
	Migrate     string
	ChangeFiles []Path
	Migration   []Migration
}

//Deploy 部署环境
type Deploy struct {
	Name    string
	Service []Service
}

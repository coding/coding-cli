package model

type Refer struct {
	Task []struct {
		TargetProjectID   int    `json:"target_project_id"`
		TargetProjectName string `json:"target_project_name"`
		Code              int    `json:"code"`
		TargetType        string `json:"target_type"`
		TargetID          int    `json:"target_id"`
		Title             string `json:"title"`
		Link              string `json:"link"`
		Status            int    `json:"status"`
	} `json:"Task"`
}

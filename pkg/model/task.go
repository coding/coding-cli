package model

type Task struct {
	Markdown    string      `json:"markdown"`
	Description string      `json:"description"`
	History     TaskHistory `json:"history"`
}

type TaskHistory struct {
	Owner User `json:"owner"`
	Data  struct {
		OwnerID     int    `json:"owner_id"`
		TaskID      int    `json:"task_id"`
		ActivityID  int    `json:"activity_id"`
		CreatedAt   int64  `json:"created_at"`
		Action      int    `json:"action"`
		Description string `json:"description"`
		ID          int    `json:"id"`
	} `json:"data"`
	IsNewest bool `json:"is_newest"`
}

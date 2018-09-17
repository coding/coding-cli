package model

type File struct {
	Data              string    `json:"data"`
	Lang              string    `json:"lang"`
	Size              int       `json:"size"`
	Previewed         bool      `json:"previewed"`
	LastCommitMessage string    `json:"lastCommitMessage"`
	LastCommitDate    int64     `json:"lastCommitDate"`
	LastAuthorDate    int64     `json:"lastAuthorDate"`
	LastCommitID      string    `json:"lastCommitId"`
	LastCommitter     Committer `json:"lastCommitter"`
	Mode              string    `json:"mode"`
	Path              string    `json:"path"`
	Name              string    `json:"name"`
}

type Blob struct {
	Ref     string `json:"ref"`
	File    File   `json:"file"`
	IsHead  bool   `json:"isHead"`
	CanEdit bool   `json:"can_edit"`
}

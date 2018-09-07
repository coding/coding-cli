package model

type Commit struct {
	ShortCommit
	FullMessage string `json:"fullMessage"`
	AllMessage  string `json:"allMessage"`
	Committer   struct {
		Name   string `json:"name"`
		Email  string `json:"email"`
		Avatar string `json:"avatar"`
		Link   string `json:"link"`
	} `json:"committer"`
	NotesCount int    `json:"notesCount"`
	RawMessage string `json:"rawMessage"`
}

type ShortCommit struct {
	ShortMessage string `json:"shortMessage"`
	CommitID     string `json:"commitId"`
	CommitTime   int64  `json:"commitTime"`
}

type ComplexCommit struct {
	CommitComments []interface{} `json:"commitComments"`
	CommitDetail   struct {
		DiffStat     DiffStat `json:"diffStat"`
		FullMessage  string   `json:"fullMessage"`
		ShortMessage string   `json:"shortMessage"`
		AllMessage   string   `json:"allMessage"`
		CommitID     string   `json:"commitId"`
		CommitTime   int64    `json:"commitTime"`
		Committer    User     `json:"committer"`
		NotesCount   int      `json:"notesCount"`
		RawMessage   string   `json:"rawMessage"`
	} `json:"commitDetail"`
	Marks []Mark `json:"marks"`
}

type Mark struct {
	DepotID      int    `json:"depot_id"`
	Sha          string `json:"sha"`
	MarkableType string `json:"markable_type"`
	MarkableID   int    `json:"markable_id"`
	Icon         string `json:"icon"`
	Name         string `json:"name"`
	Description  string `json:"description"`
	URL          string `json:"url"`
	Status       int    `json:"status"`
	CreatedAt    int64  `json:"created_at"`
}

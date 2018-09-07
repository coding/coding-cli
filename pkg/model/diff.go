package model

type Diff struct {
	Ref2Sha  string   `json:"ref2Sha"`
	Commits  []Commit `json:"commits"`
	Ref1Sha  string   `json:"ref1Sha"`
	Ref2     string   `json:"ref2"`
	Ref1     string   `json:"ref1"`
	DiffStat DiffStat `json:"diffStat"`
}

type DiffStat struct {
	Paths []struct {
		ChangeType string `json:"changeType"`
		Insertions int    `json:"insertions"`
		Deletions  int    `json:"deletions"`
		Name       string `json:"name"`
		Path       string `json:"path"`
		Size       int    `json:"size"`
		Mode       int    `json:"mode"`
		ObjectID   string `json:"objectId"`
		CommitID   string `json:"commitId"`
	} `json:"paths"`
	CommitID   string `json:"commitId"`
	OldSha     string `json:"oldSha"`
	NewSha     string `json:"newSha"`
	Insertions int    `json:"insertions"`
	Deletions  int    `json:"deletions"`
}

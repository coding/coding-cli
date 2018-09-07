package model

// Merge 包含 Merge Request 的基本信息以及 Code Review 相关辅助信息
type Merge struct {
	AuthorCanEdit     bool          `json:"author_can_edit"`
	CanEditSrcBranch  bool          `json:"can_edit_src_branch"`
	CanMergeHint      string        `json:"can_merge_hint"`
	SrcBranchExists   bool          `json:"src_branch_exists"`
	TargetIsProtected bool          `json:"target_is_protected"`
	LineNotes         []interface{} `json:"line_notes"`
	IsReviewer        bool          `json:"is_reviewer"`
	CanEdit           bool          `json:"can_edit"`
	MergeRequest      struct {
		MergedSha    string        `json:"merged_sha"`
		DiffStat     DiffStat      `json:"diffStat"`
		HTMLDiff     string        `json:"htmlDiff"`
		Commits      []Commit      `json:"commits"`
		Body         string        `json:"body"`
		BodyPlan     string        `json:"body_plan"`
		SourceSha    string        `json:"source_sha"`
		TargetSha    string        `json:"target_sha"`
		BaseSha      string        `json:"base_sha"`
		Conflicts    []interface{} `json:"conflicts"`
		ID           int           `json:"id"`
		SrcBranch    string        `json:"srcBranch"`
		DesBranch    string        `json:"desBranch"`
		Title        string        `json:"title"`
		Iid          int           `json:"iid"`
		MergeStatus  string        `json:"merge_status"`
		Path         string        `json:"path"`
		CreatedAt    int64         `json:"created_at"`
		UpdatedAt    int64         `json:"updated_at"`
		Author       User          `json:"author"`
		ActionAuthor User          `json:"action_author"`
		ActionAt     int64         `json:"action_at"`
		Granted      int           `json:"granted"`
		GrantedBy    User          `json:"granted_by"`
		CommentCount int           `json:"comment_count"`
		Marks        []interface{} `json:"marks"`
		Reminded     bool          `json:"reminded"`
	} `json:"merge_request"`
	CanMerge bool          `json:"can_merge"`
	CanGrant bool          `json:"can_grant"`
	Labels   []interface{} `json:"labels"`
}

package model

type Branch struct {
	Name            string      `json:"name"`
	LastCommit      ShortCommit `json:"last_commit"`
	IsDefaultBranch bool        `json:"is_default_branch"`
	IsProtected     bool        `json:"is_protected"`
	DenyForcePush   bool        `json:"deny_force_push"`
	ForceSquash     bool        `json:"force_squash"`
}

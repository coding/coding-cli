package model

// User 包含用户的基本信息
type User struct {
	TagsStr          string  `json:"tags_str"`
	Tags             string  `json:"tags"`
	Job              int     `json:"job"`
	Sex              int     `json:"sex"`
	Phone            string  `json:"phone"`
	Birthday         string  `json:"birthday"`
	Location         string  `json:"location"`
	Company          string  `json:"company"`
	Slogan           string  `json:"slogan"`
	Website          string  `json:"website"`
	Introduction     string  `json:"introduction"`
	Avatar           string  `json:"avatar"`
	Gravatar         string  `json:"gravatar"`
	Lavatar          string  `json:"lavatar"`
	CreatedAt        int64   `json:"created_at"`
	LastLoginedAt    int64   `json:"last_logined_at"`
	LastActivityAt   int64   `json:"last_activity_at"`
	GlobalKey        string  `json:"global_key"`
	Name             string  `json:"name"`
	NamePinyin       string  `json:"name_pinyin"`
	UpdatedAt        int64   `json:"updated_at"`
	Path             string  `json:"path"`
	Status           int     `json:"status"`
	Email            string  `json:"email"`
	IsMember         int     `json:"is_member"`
	ID               int     `json:"id"`
	PointsLeft       float64 `json:"points_left"`
	FollowsCount     int     `json:"follows_count"`
	FansCount        int     `json:"fans_count"`
	TweetsCount      int     `json:"tweets_count"`
	PhoneCountryCode string  `json:"phone_country_code"`
	Country          string  `json:"country"`
	Followed         bool    `json:"followed"`
	Follow           bool    `json:"follow"`
	IsPhoneValidated bool    `json:"is_phone_validated"`
	EmailValidation  int     `json:"email_validation"`
	PhoneValidation  int     `json:"phone_validation"`
	TwofaEnabled     int     `json:"twofa_enabled"`
	IsWelcomed       bool    `json:"is_welcomed"`
}

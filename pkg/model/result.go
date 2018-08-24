package model

import "encoding/json"

// Result 包裹着所有 JSON 返回值
type Result struct {
	Code int `json:"code"`
	Data json.RawMessage
	Msg  map[string]string `json:"msg"`
}

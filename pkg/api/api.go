package api

import (
	"crypto/sha1"
	"encoding/json"
	"fmt"
	"io"
	"io/ioutil"
	"net/http"
	"net/url"
	"os/user"
	"path"
	"strings"

	"e.coding.net/codingcorp/coding-cli/pkg/model"
	"github.com/golang/glog"
	"github.com/parnurzeal/gorequest"
)

const (
	host             = "https://codingcorp.coding.net"
	cookieFile       = ".coding_release_rc"
	error2fa         = "two_factor_auth_code_not_empty"
	form             = "form"
	referURI         = "/api/user/codingcorp/project/coding-dev/resource_reference/%d"
	defaultBranchURI = "/api/user/codingcorp/project/coding-dev/git/branches/default"
	commitDetailURI  = "/api/user/codingcorp/project/coding-dev/git/commit/%s"
	diffURI          = "/api/user/codingcorp/project/coding-dev/git/compare_v2?source=%s&target=%s&w=&prefix="
	mergeURI         = "/api/user/codingcorp/project/coding-dev/git/merge/%d"
	gitBlobURI       = "/api/user/codingcorp/project/coding-dev/git/blob/%s"
	currentUserURI   = "/api/current_user"
	diffTemplate     = "/p/coding-dev/git/compare/%s...%s"
	loginURI         = "/api/v2/account/login"
	twoFAAuthURI     = "/api/check_two_factor_auth_code"
)

func bind(str string, v interface{}) error {
	result := model.Result{}
	err := json.Unmarshal([]byte(str), &result)
	if nil != err {
		glog.Exitln("序列化 model.Result 失败", err)
	}
	if result.Code != 0 {
		glog.Exitln("请求失败：", str)
	}

	return json.Unmarshal(result.Data, v)
}

//TwoFACode 俩步验证
func TwoFACode(code string) *model.Result {
	res, body, err := gorequest.
		New().
		Post(apiURL(twoFAAuthURI)).
		AddCookie(readCookie()).
		Type(form).
		SendMap(map[string]interface{}{
			"code": code,
		}).
		End()
	if nil != err {
		glog.Exitln("俩步验证失败")
	}
	model := model.Result{}

	if nil != bind(body, &model) {
		glog.Exitln("序列化 model.Result 失败")
	}
	saveCookie(res)
	return &model
}

//Login 登录
func Login(account string, password string, twoFACode func() (string, error)) {
	res, body, err := gorequest.
		New().
		Post(apiURL(loginURI)).
		Type(form).
		SendMap(map[string]interface{}{
			"account":  account,
			"password": sha1Password(password),
		}).
		End()
	if nil != err {
		glog.Exitln("登录失败", err)
	}
	m := model.Result{}

	if nil != bind(body, &m) {
		glog.Exitln("序列化 model.Result 失败")
	}
	if m.Code != 0 {
		if require2faCode(&m) {
			code, err := twoFACode()
			if nil != err {
				glog.Errorln("读取俩步验证失败")
			}
			TwoFACode(code)
		}
	} else {
		saveCookie(res)
	}
}

//CurrentUser 当前用户
func CurrentUser() *model.User {
	res, body, err := gorequest.
		New().
		Get(apiURL(currentUserURI)).
		AddCookie(readCookie()).
		Type(form).
		End()
	if nil != err {
		glog.Exitln("获取当前用户信息失败", err)
	}
	m := model.User{}

	if nil != bind(body, &m) {
		glog.Exitln("序列化 model.User 失败", err)
	}
	saveCookie(res)
	return &m
}

//CommitID 获取提交 Hash
func CommitID(ref string) string {
	res, body, err := gorequest.
		New().
		Get(apiURL(fmt.Sprintf(commitDetailURI, ref))).
		AddCookie(readCookie()).
		Type(form).
		End()
	if nil != err {
		glog.Exitln("获取 CommitID 失败")
	}
	m := model.ComplexCommit{}

	if nil != bind(body, &m) {
		glog.Exitln("序列化 model.ComplexCommit 失败")
	}
	saveCookie(res)
	return m.CommitDetail.CommitID
}

//Diff 比较俩个提交
func Diff(src string, target string) *model.Diff {
	res, body, err := gorequest.
		New().
		Get(apiURL(fmt.Sprintf(diffURI, src, target))).
		AddCookie(readCookie()).
		Type(form).
		End()
	if nil != err {
		glog.Exitln("获取 Diff 失败")
	}

	m := model.Diff{}

	if nil != bind(body, &m) {
		glog.Exitln("序列化 model.Diff 失败")
	}
	saveCookie(res)
	return &m
}

//DefaultBranchCommitID 获取默认分支当前提交
func DefaultBranchCommitID() string {
	res, body, err := gorequest.
		New().
		Get(apiURL(defaultBranchURI)).
		AddCookie(readCookie()).
		Type(form).
		End()
	if nil != err {
		glog.Exitln("获取 CommitID 失败")
	}

	m := model.Branch{}

	if nil != bind(body, &m) {
		glog.Exitln("序列化 model.Branch 失败")
	}
	saveCookie(res)
	return m.Name
}

//Refer 获取关联资源
func Refer(resourceID int) *model.Refer {
	res, body, err := gorequest.
		New().
		Get(apiURL(fmt.Sprintf(referURI, resourceID))).
		AddCookie(readCookie()).
		Type(form).
		End()
	if nil != err {
		glog.Exitln("获取 Refer 失败")
	}

	m := model.Refer{}

	if nil != bind(body, &m) {
		glog.Exitln("序列化 model.Refer 失败")
	}
	saveCookie(res)
	return &m
}

//Merge 合并请求
func Merge(mergeID int) *model.Merge {
	res, body, err := gorequest.
		New().
		Get(apiURL(fmt.Sprintf(mergeURI, mergeID))).
		AddCookie(readCookie()).
		Type(form).
		End()
	if nil != err {
		glog.Exitln("获取 CommitID 失败")
	}

	m := model.Merge{}

	if nil != bind(body, &m) {
		glog.Exitln("序列化 model.Refer 失败")
	}
	saveCookie(res)
	return &m
}

//Blob 获取文件 Blob
func Blob(commitID string, n string) string {
	encodedParams := url.PathEscape(fmt.Sprintf("%s/%s", commitID, n))
	res, body, err := gorequest.
		New().
		Get(apiURL(fmt.Sprintf(gitBlobURI, encodedParams))).
		AddCookie(readCookie()).
		Type(form).
		End()
	if nil != err {
		glog.Exitln("获取 File 失败")
	}

	m := model.Blob{}

	if nil != bind(body, &m) {
		glog.Exitln("序列化 model.Blob 失败")
	}
	saveCookie(res)
	return m.File.Data
}

//CompareURL 返回有 src 和 target 对应的 commit 组成的 diff 链接
// 形如：https://codingcorp.coding.net/p/coding-dev/git/compare/master...enterprise-saas
func CompareURL(sourceHash string, targetHash string) string {
	s := url.PathEscape(sourceHash)
	t := url.PathEscape(targetHash)
	return host + fmt.Sprintf(diffTemplate, s, t)
}

func cookiePath() string {
	usr, err := user.Current()
	if err != nil {
		glog.Exitln("无法读取用户目录下的 .coding_release_rc 文件")
	}
	return path.Join(usr.HomeDir, cookieFile)
}

func saveCookie(res *http.Response) {
	for _, c := range res.Cookies() {
		if c.Name == "eid" {
			err := ioutil.WriteFile(cookiePath(), []byte(c.Name+"="+c.Value), 0666)
			if err != nil {
				glog.Exitln("session Cookie 不存在")
			}
		}
	}
}

func readCookie() *http.Cookie {
	b, err := ioutil.ReadFile(cookiePath())
	if err != nil {
		glog.Exitln("~/.coding_release_rc Cookie 文件读取失败")
	}
	str := strings.Replace(string(b), "\n", "", -1)
	cookiePair := strings.Split(str, "=")
	if len(cookiePair) != 2 {
		glog.Exitln("~/.coding_release_rc Cookie 不符合Cookie 格式 name=value ")
	}
	return &http.Cookie{
		Name:  cookiePair[0],
		Value: cookiePair[1],
	}
}

func require2faCode(result *model.Result) bool {
	return result.Code > 1 && result.Msg[error2fa] != ""
}

func sha1Password(password string) string {
	h := sha1.New()
	io.WriteString(h, password)
	return fmt.Sprintf("%x", h.Sum(nil))
}

func apiURL(uri string) string {
	return host + uri
}

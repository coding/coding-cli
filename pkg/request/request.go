package request

import (
	"encoding/json"
	"fmt"
	"io"
	"io/ioutil"
	"net/http"
	"net/http/cookiejar"
	"net/url"
	"strings"
	"time"

	"e.coding.net/codingcorp/coding-cli/pkg/model"
	"golang.org/x/net/publicsuffix"
)

const (
	Host = "https://codingcorp.coding.net"
	// host       = "http://127.0.0.1:8080"
	cookieFile = ".cookie"
	error2fa   = "two_factor_auth_code_not_empty"
)

// Request 包含了 HTTP 请求需要的基本参数
type Request struct {
	URL    string                 // URL 请求地址
	Form   *url.Values            // POST 提交的表单数据
	Method string                 // 请求方法 GET/POST/DELETE 等
	On2fa  func() (string, error) // On2fa 需要两步验证验证时调用
}

// NewGet 创建 GET 请求
func NewGet(url string) *Request {
	return &Request{
		URL:    Host + url,
		Method: http.MethodGet,
	}
}

// NewPost 创建 POST 请求
func NewPost(url string, form *url.Values) *Request {
	return &Request{
		URL:    Host + url,
		Method: http.MethodPost,
		Form:   form,
	}
}

// Send 将发送请求并解析 JSON 结果，返回结果 Result 中如果包含 Code > 0 的情况，将视作意外出错情况
// 只有当 Result 返回 Code 为 0 时，请求才会被当做成功
func (r *Request) Send() (*model.Result, error) {
	u, err := url.Parse(r.URL)
	if err != nil {
		return nil, fmt.Errorf("请求 URL 错误: %s, %v", r.URL, err)
	}

	if r.Method == "" {
		r.Method = http.MethodGet
	}
	var data io.Reader
	if r.Method == http.MethodPost {
		data = urlEncodeForm(r.Form)
	}
	req, err := http.NewRequest(
		r.Method,
		r.URL,
		data,
	)
	if err != nil {
		return nil, fmt.Errorf("创建请求失败, 地址: %s, %v", r.URL, err)
	}
	req.Host = "codingcorp.coding.net"
	req = formURLEncoded(req)
	jar, err := newCookieJar()
	if err != nil {
		return nil, err
	}
	cookie, err := readCookie()
	if err != nil {
		// 读取失败也不要紧，继续执行
		fmt.Println(err)
	} else {
		jar.SetCookies(u, []*http.Cookie{cookie})
	}
	client := &http.Client{
		Timeout: 60 * time.Second,
		Jar:     jar,
	}

	resp, err := client.Do(req)
	if err != nil {
		return nil, fmt.Errorf("发送请求失败, 地址: %s, %v", r.URL, err)
	}
	defer resp.Body.Close()

	err = saveCookie(jar.Cookies(u))
	if err != nil {
		// 保存失败也不要紧，继续执行
		fmt.Println(err)
	}

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("失败, 地址: %s, 错误码: %d", r.URL, resp.StatusCode)
	}

	bodyBytes, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("读取响应内容失败, %v", err)
	}
	var result model.Result
	json.Unmarshal(bodyBytes, &result)
	if result.Code != 0 {
		if require2faCode(&result) {
			return r.send2faCode()
		}
		return nil, fmt.Errorf("请求失败, %v", errorMsg(&result))
	}

	return &result, nil
}

func errorMsg(r *model.Result) string {
	msg := make([]string, 0)
	for _, v := range r.Msg {
		msg = append(msg, v)
	}
	return strings.Join(msg, ", ")
}

// SendAndUnmarshal 将发送请求并反序列化 JSON 中的 model.Result.Data 结果
func (r *Request) SendAndUnmarshal(v interface{}) error {
	result, err := r.Send()
	if err != nil {
		return err
	}
	err = json.Unmarshal(result.Data, v)
	if err != nil {
		return err
	}
	return nil
}

func (r *Request) send2faCode() (*model.Result, error) {
	code, err := r.On2fa()
	if code == "" {
		return nil, fmt.Errorf("两步验证码为空")
	}
	if err != nil {
		return nil, err
	}
	form := url.Values{}
	form.Set("code", code)
	req := NewPost("/api/check_two_factor_auth_code", &form)
	result, err := req.Send()
	if err != nil {
		return nil, err
	}
	return result, nil
}

func formURLEncoded(r *http.Request) *http.Request {
	r.Header.Add("Content-Type", "application/x-www-form-urlencoded;charset=UTF-8")
	return r
}

func newCookieJar() (http.CookieJar, error) {
	jar, err := cookiejar.New(&cookiejar.Options{PublicSuffixList: publicsuffix.List})
	if err != nil {
		return nil, fmt.Errorf("无法创建用户保存 Session 的 Cookie Jar, %v", err)
	}
	return jar, nil
}

func urlEncodeForm(form *url.Values) io.Reader {
	return strings.NewReader(form.Encode())
}

func saveCookie(cookies []*http.Cookie) error {
	for _, c := range cookies {
		if c.Name == "sid" || c.Name == "eid" {
			if c == nil {
				return fmt.Errorf("session Cookie 不存在")
			}
			err := ioutil.WriteFile(cookieFile, []byte(c.Name+"="+c.Value), 0666)
			if err != nil {
				return fmt.Errorf("保存 Session Cookie 失败，%v", err)
			}
			return nil
		}
	}
	return nil
}

func readCookie() (*http.Cookie, error) {
	b, err := ioutil.ReadFile(cookieFile)
	if err != nil {
		return nil, fmt.Errorf("读取 Cookie 文件错误，%v", err)
	}
	str := strings.Replace(string(b), "\n", "", -1)
	cookiePair := strings.Split(str, "=")
	if len(cookiePair) != 2 {
		return nil, fmt.Errorf("cookie 文件内容格式错误，文件：%s，%v", cookieFile, cookiePair)
	}
	return &http.Cookie{
		Name:  cookiePair[0],
		Value: cookiePair[1],
	}, nil
}

func require2faCode(result *model.Result) bool {
	return result.Code > 1 && result.Msg[error2fa] != ""
}

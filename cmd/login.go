// Copyright © 2018 彭博 <pengbo@coding.net>
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

package cmd

import (
	"crypto/sha1"
	"encoding/json"
	"fmt"
	"io"
	"io/ioutil"
	"net/http"
	"net/http/cookiejar"
	"net/url"
	"os"
	"strings"
	"syscall"
	"time"

	"e.coding.net/codingcorp/coding-cli/pkg/model"
	"github.com/spf13/cobra"
	"golang.org/x/crypto/ssh/terminal"
	"golang.org/x/net/publicsuffix"
)

const (
	// loginURL = "https://codigncorp.coding.net/api/v2/account/login"
	loginURL       = "http://codingcorp.coding.codingprod.net//api/v2/account/login"
	minAccountSize = 6
	cookieFile     = ".cookie"
)

var username string

// loginCmd represents the login command
var loginCmd = &cobra.Command{
	Use:   "login",
	Short: "登录到企业版",
	Long:  `使用 Coding 企业版用户名和密码登录。`,
	Run: func(cmd *cobra.Command, args []string) {
		if len(username) >= 6 {
			password, err := readPassword()
			if err != nil {
				fmt.Fprintf(os.Stderr, "\n登录失败，%v\n", err)
				return
			}
			err = login(username, sha1Password(password))
			if err != nil {
				fmt.Fprintln(os.Stderr, err)
				return
			}
			return
		}
		fmt.Fprintf(os.Stderr, "用户名至少 6 位\n")
	},
}

func readPassword() (string, error) {
	retry := 0
	maxRetry := 3
	for {
		fmt.Print("密码: ")
		b, err := terminal.ReadPassword(int(syscall.Stdin))
		if err != nil {
			return "", err
		}
		password := string(b)
		if len(password) >= minAccountSize {
			fmt.Println()
			return password, nil
		}
		retry++
		if retry >= 3 {
			return "", fmt.Errorf("密码格式错误")
		}
		fmt.Printf("\n密码至少 6 位，请重试（%d/%d）\n", retry, maxRetry)
	}
}

func init() {
	rootCmd.AddCommand(loginCmd)

	const userNameFlag = "username"
	loginCmd.Flags().StringVarP(&username, userNameFlag, "u", "", "用户名")
	loginCmd.MarkFlagRequired(userNameFlag)
}

func sha1Password(password string) string {
	h := sha1.New()
	io.WriteString(h, password)
	return fmt.Sprintf("%x", h.Sum(nil))
}

func login(username string, sha1Password string) error {
	u, err := url.Parse(loginURL)
	if err != nil {
		return fmt.Errorf("登录请求 URL 错误: %s, %v", loginURL, err)
	}

	req, err := http.NewRequest(
		http.MethodPost,
		loginURL,
		urlEncodeLoginForm(username, sha1Password),
	)
	if err != nil {
		return fmt.Errorf("创建登录请求失败, 地址: %s, %v", loginURL, err)
	}

	req = formURLEncoded(req)
	jar, err := newCookieJar()
	if err != nil {
		return err
	}
	cookie, err := readCookie()
	if err != nil {
		// 读取失败也不要紧，继续执行
		fmt.Println(err)
	} else {
		jar.SetCookies(u, []*http.Cookie{cookie})
	}
	client := &http.Client{
		Timeout: 10 * time.Second,
		Jar:     jar,
	}

	resp, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("发送登录请求失败, 地址: %s, %v", loginURL, err)
	}
	defer resp.Body.Close()

	err = saveCookie(jar.Cookies(u))
	if err != nil {
		// 保存失败也不要紧，继续执行
		fmt.Println(err)
	}

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("登录失败, 地址: %s, 错误码: %d", loginURL, resp.StatusCode)
	}

	bodyBytes, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		return fmt.Errorf("读取响应内容失败, %v", err)
	}
	var result model.Result
	json.Unmarshal(bodyBytes, &result)
	if result.Code != 0 {
		return fmt.Errorf("登录失败 %v", result.Msg)
	}

	fmt.Printf("登录成功\n")
	return nil
}

func formURLEncoded(r *http.Request) *http.Request {
	r.Header.Add("Content-Type", "application/x-www-form-URLencoded")
	return r
}

func newCookieJar() (http.CookieJar, error) {
	jar, err := cookiejar.New(&cookiejar.Options{PublicSuffixList: publicsuffix.List})
	if err != nil {
		return nil, fmt.Errorf("无法创建用户保存 Session 的 Cookie Jar, %v", err)
	}
	return jar, nil
}

func urlEncodeLoginForm(u string, p string) io.Reader {
	form := url.Values{}
	form.Set("account", u)
	form.Set("password", p)
	return strings.NewReader(form.Encode())
}

func saveCookie(cookies []*http.Cookie) error {
	for _, c := range cookies {
		if c.Name == "sid" || c.Name == "eid" {
			if c == nil {
				return fmt.Errorf("Session Cookie 不存在")
			}
			f, err := os.OpenFile(cookieFile, os.O_WRONLY|os.O_CREATE, 0666)
			_, err = f.WriteString(c.Name + "=" + c.Value)
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
	cookiePair := strings.Split(string(b), "=")
	if len(cookiePair) != 2 {
		return nil, fmt.Errorf("Cookie 文件内容格式错误，文件：%s，%v", cookieFile, cookiePair)
	}
	return &http.Cookie{
		Name:  cookiePair[0],
		Value: cookiePair[1],
	}, nil
}

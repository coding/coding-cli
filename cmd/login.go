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
	"fmt"
	"io"
	"net/url"
	"os"
	"syscall"

	"e.coding.net/codingcorp/coding-cli/pkg/request"
	"github.com/spf13/cobra"
	"golang.org/x/crypto/ssh/terminal"
)

const (
	// loginURL       = "/api/v2/account/login"
	loginURI       = "/api/v2/account/login"
	minAccountSize = 6
	cookieFile     = ".cookie"
)

var account string

// loginCmd represents the login command
var loginCmd = &cobra.Command{
	Use:   "login",
	Short: "登录到企业版",
	Long:  `使用 Coding 企业版用户名（邮箱或手机号）和密码登录。`,
	Run: func(cmd *cobra.Command, args []string) {
		if len(account) >= 3 {
			password, err := readPassword()
			if err != nil {
				fmt.Fprintf(os.Stderr, "\n登录失败，%v\n", err)
				return
			}
			err = login(account, sha1Password(password))
			if err != nil {
				fmt.Fprintln(os.Stderr, err)
				return
			}
			return
		}
		fmt.Fprintf(os.Stderr, "用户名至少 3 位\n")
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

	const accountFlag = "account"
	loginCmd.Flags().StringVarP(&account, accountFlag, "u", "", "用户名（邮箱或手机号）")
	loginCmd.MarkFlagRequired(accountFlag)
}

func sha1Password(password string) string {
	h := sha1.New()
	io.WriteString(h, password)
	return fmt.Sprintf("%x", h.Sum(nil))
}

func login(account string, sha1Password string) error {
	form := url.Values{}
	form.Set("account", account)
	form.Set("password", sha1Password)
	req := request.NewPostRequest(loginURI, &form)
	req.On2fa = get2faCode
	_, err := req.Send()
	if err != nil {
		return err
	}
	fmt.Println("登录成功")
	return nil
}

func get2faCode() (string, error) {
	fmt.Print("两步验证码: ")
	b, err := terminal.ReadPassword(int(syscall.Stdin))
	if err != nil {
		return "", fmt.Errorf("读取两步验证码失败, %v", err)
	}
	code := string(b)
	if len(code) == 6 {
		return code, nil
	}
	return "", fmt.Errorf("读取两步验为 6 位数字")
}

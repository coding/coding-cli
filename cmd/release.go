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
	"flag"
	"fmt"

	"e.coding.net/codingcorp/coding-cli/pkg/api"
	"e.coding.net/codingcorp/coding-cli/pkg/diff"
	"github.com/golang/glog"
	"github.com/spf13/cobra"
)

var output string
var rtype string
var product string
var project string
var patch int8
var config string

// releaseCmd represents the release command
var releaseCmd = &cobra.Command{
	Use:   "release 目标分支 或 源分支 目标分支",
	Short: "创建版本发布",
	Long: `创建版本发布
为分支、提交或标签（简称 ref）创建版本发布，版本发布分为常规发布和紧急修复两类。
示例命令：coding-cli release master enterprise-saas  -o release-20181030.1-enterprise.md -l enterprise-saas -t normal -n 1 -c ~/.coding_release.yml
`,
	Args: cobra.MinimumNArgs(1),
	Run: func(cmd *cobra.Command, args []string) {
		if project == "" {
			project = "coding-dev"
		}
		api.SetProject(project)
		source, target, err := parseArgs(args)
		if err != nil {
			glog.Exitln("解析目标分支参数异常，", err)
			return
		}
		diff.Analysis(
			product,
			source,
			target,
			rtype,
			patch,
			output,
			config,
		)
	},
}

func parseArgs(args []string) (source string, target string, err error) {
	argsSize := len(args)
	if argsSize == 0 {
		return "", "", fmt.Errorf("需提供分支名、提交或标签")
	}
	if argsSize == 1 {
		target = args[0]
		source = api.DefaultBranchCommitID()
	}
	if argsSize == 2 {
		source = args[0]
		target = args[1]
	}
	return
}

func init() {
	rootCmd.AddCommand(releaseCmd)
	releaseCmd.Flags().StringVarP(&output, "output", "o", "", "保存到文件")
	releaseCmd.Flags().StringVarP(&rtype, "type", "t", "normal", "发布类型，hotfix - 紧急修复或者 normal - 常规更新")
	releaseCmd.Flags().StringVarP(&product, "line", "l", "enterprise-saas", "产品线，enterprise-saas 或者 professional")
	releaseCmd.Flags().StringVarP(&project, "project", "p", "coding-dev", "项目名称（默认为 coding-dev）")
	releaseCmd.Flags().Int8VarP(&patch, "patch", "n", 1, "patch 序号")
	releaseCmd.Flags().StringVarP(&config, "config", "c", "", "配置文件（默认为用户目录下的 .coding_release.yml 文件）")
	flag.Parse()
}

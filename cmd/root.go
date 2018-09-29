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
	"fmt"
	"os"

	goflag "flag"

	"github.com/golang/glog"
	homedir "github.com/mitchellh/go-homedir"
	"github.com/spf13/cobra"
	"github.com/spf13/viper"

	flag "github.com/spf13/pflag"
)

var cfgFile string

// rootCmd represents the base command when called without any subcommands
var rootCmd = &cobra.Command{
	Use:   "coding-cli",
	Short: "Coding 企业版命令行工具",
	Long: `Coding 企业版命令行工具, 主要功能和目标:

- 使重复性的开发流程自动化：自动编写版本发布文档、合并请求的常规检查等
- 简化项目管理过程：跟踪团队未合并代码进度等
- 其他可以直接使用命令行快速处理的功能
`,
}

// Execute adds all child commands to the root command and sets flags appropriately.
// This is called by main.main(). It only needs to happen once to the rootCmd.
func Execute() {
	if err := rootCmd.Execute(); err != nil {
		glog.Error(err)
		os.Exit(1)
	}
}

func init() {
	cobra.OnInitialize(initConfig)
	// use glog in cobra see https://flowerinthenight.com/blog/2017/12/01/golang-cobra-glog
	flag.CommandLine.AddGoFlagSet(goflag.CommandLine)
}

// initConfig reads in config file and ENV variables if set.
func initConfig() {
	if cfgFile != "" {
		// Use config file from the flag.
		viper.SetConfigFile(cfgFile)
	} else {
		// Find home directory.
		home, err := homedir.Dir()
		if err != nil {
			fmt.Println(err)
			os.Exit(1)
		}

		// Search config in home directory with name ".coding-cli" (without extension).
		viper.AddConfigPath(home)
		viper.SetConfigName(".coding-cli")
	}

	viper.AutomaticEnv() // read in environment variables that match

	// If a config file is found, read it in.
	if err := viper.ReadInConfig(); err == nil {
		fmt.Println("Using config file:", viper.ConfigFileUsed())
	}
}

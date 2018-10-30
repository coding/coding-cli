package config

import (
	"io/ioutil"
	"os/user"
	"path"

	"github.com/golang/glog"
	"gopkg.in/yaml.v2"
)

const defaultConfigFile = ".coding_release.yml"

type Config struct {
	Service []struct {
		Name    string   `yaml:name",omitempty"`
		Migrate string   `yaml:"migrate,omitempty"`
		Source  []string `yaml:source",flow"`
	}
}

func Load(filePath string) *Config {
	file := configPath(filePath)
	b, err := ioutil.ReadFile(file)
	if nil != err {
		glog.Exitln(file, " 文件读取失败")
	}
	conf := Config{}
	err = yaml.Unmarshal(b, &conf)
	if nil != err {
		glog.Exitln(file, " 文件解析失败")
	}
	return &conf
}

func configPath(filePath string) string {
	usr, err := user.Current()
	if err != nil {
		glog.Exitln("无法读取用户目录")
	}
	if len(filePath) <= 0 {
		return path.Join(usr.HomeDir, defaultConfigFile)
	}
	return filePath
}

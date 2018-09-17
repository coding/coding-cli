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
	"bytes"
	"testing"
)

func Test_diffURL(t *testing.T) {
	type args struct {
		src    string
		target string
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{
			name: "测试 diffURl 产出原输入 ref 的 Git 对比链接",
			args: args{
				src:    "master",
				target: "mr/a/b-中文",
			},
			want: "https://codigncorp.coding.net/p/coding-dev/git/compare/master...mr%2Fa%2Fb-%E4%B8%AD%E6%96%87",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := diffURL(tt.args.src, tt.args.target); got != tt.want {
				t.Errorf("diffURL() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_createRelease_save(t *testing.T) {
	type fields struct {
		Changes   []changelog
		Diff      string
		Hotfix    bool
		Principal string
		Milestone string
		Services  []string
		Migration []migration
		Master    string
	}
	tests := []struct {
		name    string
		fields  fields
		wantO   string
		wantErr bool
	}{
	// TODO: Add test cases.
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			release := &createRelease{
				Changes:   tt.fields.Changes,
				Diff:      tt.fields.Diff,
				Hotfix:    tt.fields.Hotfix,
				Principal: tt.fields.Principal,
				Milestone: tt.fields.Milestone,
				Services:  tt.fields.Services,
				Migration: tt.fields.Migration,
				Master:    tt.fields.Master,
			}
			o := &bytes.Buffer{}
			if err := release.save(o); (err != nil) != tt.wantErr {
				t.Errorf("createRelease.save() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if gotO := o.String(); gotO != tt.wantO {
				t.Errorf("createRelease.save() = %v, want %v", gotO, tt.wantO)
			}
		})
	}
}

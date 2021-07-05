<?php

namespace Tests\Feature;

use App\Coding;
use Confluence\Content;
use LaravelFans\Confluence\Facades\Confluence;
use Mockery\MockInterface;
use Tests\TestCase;

class WikiImportCommandTest extends TestCase
{
    public function testHandleIndex()
    {
        $data = json_decode('{
            "results": [],
            "start": 0,
            "limit": 25,
            "size": 0
        }', true);
        $mock = $this->partialMock(Content::class, function (MockInterface $mock) use ($data) {
            $mock->shouldReceive('index')->once()->andReturn($data);
        });
        Confluence::setResource($mock);
        $this->artisan('wiki:import')
            ->expectsQuestion('CODING 团队域名：', $this->faker->domainWord)
            ->expectsQuestion('CODING 项目标识：', $this->faker->slug)
            ->expectsQuestion('CODING Token：', $this->faker->md5)
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'API')
            ->expectsQuestion('Confluence API 链接：', $this->faker->url)
            ->expectsQuestion('Confluence 账号：', $this->faker->userName)
            ->expectsQuestion('Confluence 密码：', $this->faker->password)
            ->expectsOutput("已获得 0 条数据")
            ->assertExitCode(0);
    }

    public function testHandleShow()
    {
        config(['confluence.base_uri' => $this->faker->url]);
        config(['confluence.username' => $this->faker->userName]);
        config(['confluence.password' => $this->faker->password]);
        $codingToken = $this->faker->md5;
        config(['coding.token' => $codingToken]);
        $codingTeamDomain =  $this->faker->domainWord;
        config(['coding.team_domain' => $codingTeamDomain]);
        $codingProjectUri = $this->faker->slug;
        config(['coding.project_uri' => $codingProjectUri]);

        $data = json_decode('{
            "results": [
                {
                    "id": "65619",
                    "type": "page",
                    "status": "current",
                    "title": "hello-world"
                }
            ],
            "start": 0,
            "limit": 25,
            "size": 1
        }', true);

        $content = json_decode('{
            "id": "65619",
            "type": "page",
            "status": "current",
            "title": "hello-world",
            "body": {
                "storage": {
                    "value": "<p>Hello World!</p><h2>\u7b2c\u4e00\u7ae0</h2>h3>1.1&nbsp;What is Lorem Ipsum?</h3>",
                    "representation": "storage",
                    "_expandable": {
                        "content": "/rest/api/content/65619"
                    }
                }
            }
        }', true);
        $mock = $this->partialMock(Content::class, function (MockInterface $mock) use ($data, $content) {
            $mock->shouldReceive('index')->once()->andReturn($data);
            $mock->shouldReceive('show')->once()->andReturn($content);
        });
        Confluence::setResource($mock);

        $codingResponse = json_decode('{
           "Response" : {
              "Data" : {
                 "CanMaintain" : true,
                 "CanRead" : true,
                 "Content" : "foo foo",
                 "CreatedAt" : 1625214079010,
                 "Creator" : {
                    "Avatar" : "https://coding-net-production-static-ci.codehub.cn/2cb665a3-aa00-2b6df3e33edc.jpg",
                    "Email" : "",
                    "GlobalKey" : "KMRnIKgzbV",
                    "Id" : 183478,
                    "Name" : "sinkcup",
                    "Phone" : "",
                    "RequestId" : "",
                    "Status" : "ACTIVE",
                    "TeamId" : 0
                 },
                 "CreatorId" : 0,
                 "CurrentUserRoleId" : 0,
                 "CurrentVersion" : 1,
                 "Editor" : {
                    "Avatar" : "https://coding-net-production-static-ci.codehub.cn/2cb665a3--aa00-2b6df3e33edc.jpg",
                    "Email" : "",
                    "GlobalKey" : "KMRnIKgzbV",
                    "Id" : 183478,
                    "Name" : "sinkcup",
                    "Phone" : "",
                    "RequestId" : "",
                    "Status" : "ACTIVE",
                    "TeamId" : 0
                 },
                 "EditorId" : 0,
                 "HistoriesCount" : 1,
                 "HistoryId" : 2707176,
                 "Html" : "<p>foo foo</p>",
                 "Id" : 1325288,
                 "Iid" : 27,
                 "LastVersion" : 1,
                 "Msg" : "",
                 "Order" : 2,
                 "ParentIid" : 0,
                 "ParentShared" : false,
                 "ParentVisibleRange" : "PUBLIC",
                 "Path" : "27",
                 "Title" : "foo by curl",
                 "UpdatedAt" : 1625214079014,
                 "VisibleRange" : "INHERIT"
              },
              "RequestId" : "a50c8805-8e1f-fc4d-f965-855e5a3cf709"
           }
        }', true);
        $this->mock(Coding::class, function (MockInterface $mock) use (
            $codingToken,
            $codingProjectUri,
            $content,
            $codingResponse
        ) {
            $mock->shouldReceive('createWiki')->once()->withArgs([
                $codingToken,
                $codingProjectUri,
                [
                    'Title' => $content['title'],
                    'Content' => $content['body']['storage']['value'],
                    'ParentIid' => 0,
                ]
            ])->andReturn($codingResponse);
        });

        $this->artisan('wiki:import')
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'API')
            ->expectsOutput("已获得 1 条数据")
            ->expectsOutput("开始导入 CODING：")
            ->expectsOutput("https://${codingTeamDomain}.coding.net/p/$codingProjectUri/wiki/27")
            ->assertExitCode(0);
    }

    public function testHandleConfluenceHtmlFileNotExist()
    {
        $codingToken = $this->faker->md5;
        config(['coding.token' => $codingToken]);
        $codingTeamDomain =  $this->faker->domainWord;
        config(['coding.team_domain' => $codingTeamDomain]);
        $codingProjectUri = $this->faker->slug;
        config(['coding.project_uri' => $codingProjectUri]);

        $this->artisan('wiki:import')
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'HTML')
            ->expectsQuestion('路径：', '~/Downloads/')
            ->expectsOutput('文件不存在：~/Downloads/index.html')
            ->assertExitCode(1);

        $this->artisan('wiki:import')
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'HTML')
            ->expectsQuestion('路径：', '~/Downloads/index.html')
            ->expectsOutput('文件不存在：~/Downloads/index.html')
            ->assertExitCode(1);
    }

    public function testHandleConfluenceHtml()
    {
        $codingToken = $this->faker->md5;
        config(['coding.token' => $codingToken]);
        $codingTeamDomain =  $this->faker->domainWord;
        config(['coding.team_domain' => $codingTeamDomain]);
        $codingProjectUri = $this->faker->slug;
        config(['coding.project_uri' => $codingProjectUri]);

        $path = '/tmp/phpunit-' . $this->faker->word;
        @mkdir($path, 0777, true);
        $html = '<!DOCTYPE html>
<html>
    <head>
        <title>DEMO (Demo)</title>
        <link rel="stylesheet" href="styles/site.css" type="text/css" />
        <META http-equiv="Content-Type" content="text/html; charset=UTF-8">
    </head>

    <body class="theme-default aui-theme-default">
        <div id="page">
            <div id="main" class="aui-page-panel">
                <div id="main-header">
                    <h1 id="title-heading" class="pagetitle">
                        <span id="title-text">Space Details:</span>
                    </h1>
                </div>

                <div id="content">
                    <div id="main-content" class="pageSection">
                    <table class="confluenceTable">
                        <tr>
                            <th class="confluenceTh">Key</th>
                            <td class="confluenceTd">demo</td>
                        </tr>
                        <tr>
                            <th class="confluenceTh">Name</th>
                            <td class="confluenceTd">Demo</td>
                        </tr>
                        <tr>
                            <th class="confluenceTh">Description</th>
                            <td class="confluenceTd"></td>
                        </tr>
                        <tr>
                            <th class="confluenceTh">Created by</th>
                            <td class="confluenceTd">admin (六月 04, 2021)</td>
                        </tr>
                    </table>
                    </div> 
                    <br/>
                    <br/>

                    <div class="pageSection">
                        <div class="pageSectionHeader">
                            <h2 class="pageSectionTitle">Available Pages:</h2>
                        </div>
                            <ul>
                        <li>
                <a href="2021-06-04-Meeting-notes_65601.html">2021-06-04 Meeting notes</a>

                    </li>
                        <li>
                <a href="Demo_65591.html">Demo</a>

         <img src="images/icons/contenttypes/home_page_16.png" height="16" width="16" border="0" align="absmiddle"/>
                <ul>
                    <li>
                <a href="Attachment-Demo_65615.html">Attachment Demo</a>

                    </li>
            </ul>
                    <ul>
                    <li>
                <a href="hello-world_65619.html">hello-world</a>

                    </li>
            </ul>
                    <ul>
                    <li>
                <a href="This-is-a-demo_65609.html">This is a demo</a>

                    </li>
            </ul>
            </li>
            </ul>
                    </div>

                </div>             </div> 
            <div id="footer" role="contentinfo">
                <section class="footer-body">
                    <p>Document generated by Confluence on 七月 05, 2021 18:26</p>
                    <div id="footer-logo"><a href="http://www.atlassian.com/">Atlassian</a></div>
                </section>
            </div>
        </div>
    </body>
</html>
';
        $filePath = $path . '/index.html';
        file_put_contents($filePath, $html);

        $this->artisan('wiki:import')
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'HTML')
            ->expectsQuestion('路径：', $path)
            ->expectsOutput('空间名称：Demo')
            ->expectsOutput('空间标识：demo')
            ->assertExitCode(0);
    }
}

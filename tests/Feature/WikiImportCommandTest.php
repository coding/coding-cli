<?php

namespace Tests\Feature;

use App\Coding;
use Confluence\Content;
use LaravelFans\Confluence\Facades\Confluence;
use Mockery\MockInterface;
use Tests\TestCase;

class WikiImportCommandTest extends TestCase
{
    private array $codingResponse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codingResponse = json_decode(file_get_contents($this->dataDir . 'coding/createWikiResponse.json'), true);
    }

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

        $codingResponse = $this->codingResponse;
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

        $path = $this->dataDir . 'confluence/space-1/';

        $codingResponse = $this->codingResponse;
        $this->mock(Coding::class, function (MockInterface $mock) use (
            $codingToken,
            $codingProjectUri,
            $codingResponse
        ) {
            $mock->shouldReceive('createWiki')->once()->withArgs([
                $codingToken,
                $codingProjectUri,
                [
                    'Title' => 'Text Demo',
                    'Content' => '你好',
                    'ParentIid' => 0,
                ]
            ])->andReturn($codingResponse);
            $mock->shouldReceive('createWiki')->times(1)->andReturn($codingResponse);
        });

        $this->artisan('wiki:import')
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'HTML')
            ->expectsQuestion('路径：', $path)
            ->expectsOutput('空间名称：Demo')
            ->expectsOutput('空间标识：demo')
            ->expectsOutput('发现 2 个一级页面')
            ->expectsOutput("开始导入 CODING：")
            ->expectsOutput('标题：Text Demo')
            ->expectsOutput("https://${codingTeamDomain}.coding.net/p/$codingProjectUri/wiki/27")
            ->expectsOutput('标题：Demo')
            ->expectsOutput("https://${codingTeamDomain}.coding.net/p/$codingProjectUri/wiki/27")
            ->assertExitCode(0);
    }
}

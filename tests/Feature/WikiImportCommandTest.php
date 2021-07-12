<?php

namespace Tests\Feature;

use App\Coding;
use Confluence\Content;
use LaravelFans\Confluence\Facades\Confluence;
use Mockery\MockInterface;
use Tests\TestCase;

class WikiImportCommandTest extends TestCase
{
    private array $createWikiResponse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWikiResponse = json_decode(
            file_get_contents($this->dataDir . 'coding/createWikiResponse.json'),
            true
        )['Response']['Data'];
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

        $codingResponse = $this->createWikiResponse;
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
            ->expectsQuestion('空间导出的 HTML 目录', '~/Downloads/')
            ->expectsOutput('文件不存在：~/Downloads/index.html')
            ->assertExitCode(1);

        $this->artisan('wiki:import')
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'HTML')
            ->expectsQuestion('空间导出的 HTML 目录', '~/Downloads/index.html')
            ->expectsOutput('文件不存在：~/Downloads/index.html')
            ->assertExitCode(1);
    }

    public function testHandleConfluenceHtmlSuccess()
    {
        $codingToken = $this->faker->md5;
        config(['coding.token' => $codingToken]);
        $codingTeamDomain =  $this->faker->domainWord;
        config(['coding.team_domain' => $codingTeamDomain]);
        $codingProjectUri = $this->faker->slug;
        config(['coding.project_uri' => $codingProjectUri]);

        // 注意：不能使用 partialMock
        // https://laracasts.com/discuss/channels/testing/this-partialmock-doesnt-call-the-constructor
        $mock = \Mockery::mock(Coding::class, [])->makePartial();
        $this->instance(Coding::class, $mock);

        $mock->shouldReceive('createWikiByUploadZip')->times(4)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateWikiByZipResponse.json'),
            true
        )['Response']);
        $mock->shouldReceive('getImportJobStatus')->times(4)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'DescribeImportJobStatusResponse.json'),
            true
        )['Response']['Data']);
        $mock->shouldReceive('updateWikiTitle')->times(4)->andReturn(true);

        $this->artisan('wiki:import')
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'HTML')
            ->expectsQuestion('空间导出的 HTML 目录', $this->dataDir . 'confluence/space1/')
            ->expectsOutput('空间名称：空间 1')
            ->expectsOutput('空间标识：space1')
            ->expectsOutput('发现 2 个一级页面')
            ->expectsOutput("开始导入 CODING：")
            ->expectsOutput('标题：Image Demo')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('标题：你好世界')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('发现 2 个子页面')
            ->expectsOutput('标题：Attachment Demo')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('标题：Text Demo')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->assertExitCode(0);
    }
}

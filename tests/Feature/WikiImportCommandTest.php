<?php

namespace Tests\Feature;

use App\Coding\Disk;
use App\Coding\Wiki;
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
            file_get_contents($this->dataDir . 'coding/CreateWikiResponse.json'),
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
        $codingTeamDomain = $this->faker->domainWord;
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
        $this->mock(Wiki::class, function (MockInterface $mock) use (
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

    private function setConfig()
    {
        $codingToken = $this->faker->md5;
        config(['coding.token' => $codingToken]);
        $codingTeamDomain = $this->faker->domainWord;
        config(['coding.team_domain' => $codingTeamDomain]);
        $codingProjectUri = $this->faker->slug;
        config(['coding.project_uri' => $codingProjectUri]);
    }

    public function testHandleConfluenceHtmlDirNotExist()
    {
        $this->setConfig();
        $this->artisan('wiki:import')
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'HTML')
            ->expectsQuestion('空间导出的 HTML zip 文件路径', '/dev/null/')
            ->expectsOutput('文件不存在：/dev/null/index.html')
            ->expectsOutput('报错信息汇总：')
            ->expectsOutput('文件不存在：/dev/null/index.html')
            ->assertExitCode(1);
    }

    public function testHandleConfluenceHtmlFileNotExist()
    {
        $this->setConfig();

        $this->artisan('wiki:import')
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'HTML')
            ->expectsQuestion('空间导出的 HTML zip 文件路径', '/dev/null/index.html')
            ->expectsOutput('页面不存在：/dev/null/index.html')
            ->assertExitCode(1);
    }

    public function testHandleConfluenceHtmlSuccess()
    {
        $this->setConfig();

        // 注意：不能使用 partialMock
        // https://laracasts.com/discuss/channels/testing/this-partialmock-doesnt-call-the-constructor
        $mock = \Mockery::mock(Wiki::class, [])->makePartial();
        $this->instance(Wiki::class, $mock);

        $mock->shouldReceive('createWikiByUploadZip')->times(4)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateWikiByZipResponse.json'),
            true
        )['Response']);
        $mock->shouldReceive('getImportJobStatus')->times(4)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'DescribeImportJobStatusResponse.json'),
            true
        )['Response']['Data']);
        $mock->shouldReceive('updateTitle')->times(4)->andReturn(true);


        $mockDisk = \Mockery::mock(Disk::class, [])->makePartial();
        $this->instance(Disk::class, $mockDisk);
        $mockDisk->shouldReceive('uploadAttachments')->times(4)->andReturn([]);

        $log = "image-demo_65619.html = 27\n"
            . "65591.html = 27\n";
        file_put_contents($this->dataDir . '/confluence/space1/success.log', $log);
        $this->artisan('wiki:import', ['--save-markdown' => true, '--clean' => true])
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'HTML')
            ->expectsQuestion('空间导出的 HTML zip 文件路径', $this->dataDir . 'confluence/space1/')
            ->expectsOutput('空间名称：空间 1')
            ->expectsOutput('空间标识：space1')
            ->expectsOutput('发现 3 个一级页面')
            ->expectsOutput("开始导入 CODING：")
            ->expectsOutput('页面不存在：' . $this->dataDir . 'confluence/space1/not-found.html')
            ->expectsOutput('标题：Image Demo')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('标题：你好世界')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('发现 2 个子页面')
            ->expectsOutput('标题：Attachment Demo')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('标题：Text Demo')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('报错信息汇总：')
            ->expectsOutput('页面不存在：' . $this->dataDir . 'confluence/space1/not-found.html')
            ->assertExitCode(1);
        $this->assertFileExists($this->dataDir . '/confluence/space1/65591.md');
        $this->assertFileExists($this->dataDir . '/confluence/space1/attachment-demo_65615.md');
        $this->assertFileExists($this->dataDir . '/confluence/space1/text-demo_65601.md');
        unlink($this->dataDir . '/confluence/space1/65591.md');
        unlink($this->dataDir . '/confluence/space1/attachment-demo_65615.md');
        unlink($this->dataDir . '/confluence/space1/text-demo_65601.md');
        $log = "image-demo_65619.html = 27\n"
            . "65591.html = 27\n"
            . "attachment-demo_65615.html = 27\n"
            . "text-demo_65601.html = 27\n";
        $this->assertEquals($log, file_get_contents($this->dataDir . '/confluence/space1/success.log'));
    }

    public function testAskNothing()
    {
        $this->setConfig();
        config(['coding.import.provider' => 'Confluence']);
        // TODO config function can set the key not exists, can't test the key not exists in config file
        config(['coding.import.data_type' => 'HTML']);
        config(['coding.import.data_path' => '/dev/null']);
        $this->artisan('wiki:import')
            ->expectsOutput('文件不存在：/dev/null/index.html')
            ->assertExitCode(1);
    }

    public function testHandleConfluenceHtmlZipSuccess()
    {
        $this->setConfig();

        // 注意：不能使用 partialMock
        // https://laracasts.com/discuss/channels/testing/this-partialmock-doesnt-call-the-constructor
        $mock = \Mockery::mock(Wiki::class, [])->makePartial();
        $this->instance(Wiki::class, $mock);

        $mock->shouldReceive('createWikiByUploadZip')->times(5)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateWikiByZipResponse.json'),
            true
        )['Response']);
        $mock->shouldReceive('getImportJobStatus')->times(5)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'DescribeImportJobStatusResponse.json'),
            true
        )['Response']['Data']);
        $mock->shouldReceive('updateTitle')->times(5)->andReturn(true);


        $mockDisk = \Mockery::mock(Disk::class, [])->makePartial();
        $this->instance(Disk::class, $mockDisk);
        $mockDisk->shouldReceive('uploadAttachments')->times(5)->andReturn([]);

        $this->artisan('wiki:import')
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'HTML')
            ->expectsQuestion(
                '空间导出的 HTML zip 文件路径',
                $this->dataDir . 'confluence/Confluence-space-export-231543-81.html.zip'
            )
            ->expectsOutput('空间名称：空间 1')
            ->expectsOutput('空间标识：space1')
            ->expectsOutput('发现 1 个一级页面')
            ->expectsOutput("开始导入 CODING：")
            ->expectsOutput('标题：空间 1 Home')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('发现 2 个子页面')
            ->expectsOutput('标题：hello world')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('发现 2 个子页面')
            ->expectsOutput('标题：hello')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('标题：world')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('标题：你好世界')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->assertExitCode(0);
    }

    public function testHandleConfluenceSingleHtmlSuccess()
    {
        $this->setConfig();

        // 注意：不能使用 partialMock
        // https://laracasts.com/discuss/channels/testing/this-partialmock-doesnt-call-the-constructor
        $mock = \Mockery::mock(Wiki::class, [])->makePartial();
        $this->instance(Wiki::class, $mock);

        $mock->shouldReceive('createWikiByUploadZip')->times(1)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateWikiByZipResponse.json'),
            true
        )['Response']);
        $mock->shouldReceive('getImportJobStatus')->times(1)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'DescribeImportJobStatusResponse.json'),
            true
        )['Response']['Data']);
        $mock->shouldReceive('updateTitle')->times(1)->andReturn(true);


        $this->artisan('wiki:import')
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'HTML')
            ->expectsQuestion('空间导出的 HTML zip 文件路径', $this->dataDir . 'confluence/space1/image-demo_65619.html')
            ->expectsOutput('标题：空间 1 : Image Demo')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->assertExitCode(0);
    }

    public function testHandleConfluenceHtmlContinueSuccess()
    {
        $this->setConfig();

        // 注意：不能使用 partialMock
        // https://laracasts.com/discuss/channels/testing/this-partialmock-doesnt-call-the-constructor
        $mock = \Mockery::mock(Wiki::class, [])->makePartial();
        $this->instance(Wiki::class, $mock);

        $mock->shouldReceive('createWikiByUploadZip')->times(2)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateWikiByZipResponse.json'),
            true
        )['Response']);
        $mock->shouldReceive('getImportJobStatus')->times(2)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'DescribeImportJobStatusResponse.json'),
            true
        )['Response']['Data']);
        $mock->shouldReceive('updateTitle')->times(2)->andReturn(true);


        $mockDisk = \Mockery::mock(Disk::class, [])->makePartial();
        $this->instance(Disk::class, $mockDisk);
        $mockDisk->shouldReceive('uploadAttachments')->times(2)->andReturn([]);

        $log = "image-demo_65619.html = 27\n"
            . "65591.html = 27\n";
        file_put_contents($this->dataDir . '/confluence/space1/success.log', $log);
        $this->artisan('wiki:import')
            ->expectsQuestion('数据来源？', 'Confluence')
            ->expectsQuestion('数据类型？', 'HTML')
            ->expectsQuestion('空间导出的 HTML zip 文件路径', $this->dataDir . 'confluence/space1/')
            ->expectsOutput('空间名称：空间 1')
            ->expectsOutput('空间标识：space1')
            ->expectsOutput('发现 3 个一级页面')
            ->expectsOutput("开始导入 CODING：")
            ->expectsOutput('页面不存在：' . $this->dataDir . 'confluence/space1/not-found.html')
            ->expectsOutput('断点续传，跳过页面：image-demo_65619.html')
            ->expectsOutput('断点续传，跳过页面：65591.html')
            ->expectsOutput('发现 2 个子页面')
            ->expectsOutput('标题：Attachment Demo')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('标题：Text Demo')
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->expectsOutput('报错信息汇总：')
            ->expectsOutput('页面不存在：' . $this->dataDir . 'confluence/space1/not-found.html')
            ->assertExitCode(1);
        $log = "image-demo_65619.html = 27\n"
            . "65591.html = 27\n"
            . "attachment-demo_65615.html = 27\n"
            . "text-demo_65601.html = 27\n";
        $this->assertEquals($log, file_get_contents($this->dataDir . '/confluence/space1/success.log'));
        unlink($this->dataDir . '/confluence/space1/success.log');
    }
}

<?php

namespace Tests\Feature;

use App\Coding\Wiki;
use Tests\TestCase;

class WikiUploadCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $codingToken = $this->faker->md5;
        config(['coding.token' => $codingToken]);
        $codingTeamDomain =  $this->faker->domainWord;
        config(['coding.team_domain' => $codingTeamDomain]);
        $codingProjectUri = $this->faker->slug;
        config(['coding.project_uri' => $codingProjectUri]);
    }

    public function testHandleFileNotExist()
    {
        $filePath = sys_get_temp_dir() . '/nothing-' . $this->faker->uuid;
        $this->artisan('wiki:upload', ['file' => $filePath])
            ->expectsOutput("文件不存在：${filePath}")
            ->assertExitCode(1);
    }

    public function testHandleConfluenceHtmlSuccess()
    {
        $mock = \Mockery::mock(Wiki::class, [])->makePartial();
        $this->instance(Wiki::class, $mock);

        $mock->shouldReceive('createWikiByUploadZip')->times(1)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateWikiByZipResponse.json'),
            true
        )['Response']);

        $filePath = $this->faker->filePath();
        $this->artisan('wiki:upload', ['file' => $filePath])
            ->expectsOutput('上传成功，正在处理，任务 ID：a12353fa-f45b-4af2-83db-666bf9f66615')
            ->assertExitCode(0);
    }
}

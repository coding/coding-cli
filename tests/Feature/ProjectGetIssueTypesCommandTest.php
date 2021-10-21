<?php

namespace Tests\Feature;

use App\Coding\ProjectSetting;
use Tests\TestCase;

class ProjectGetIssueTypesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $codingToken = $this->faker->md5;
        config(['coding.token' => $codingToken]);
        $codingTeamDomain = $this->faker->domainWord;
        config(['coding.team_domain' => $codingTeamDomain]);
        $codingProjectUri = $this->faker->slug;
        config(['coding.project_uri' => $codingProjectUri]);
    }

    public function testCreateSuccess()
    {
        $mock = \Mockery::mock(ProjectSetting::class, [])->makePartial();
        $this->instance(ProjectSetting::class, $mock);

        $mock->shouldReceive('getIssueTypes')->times(1)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'DescribeProjectIssueTypeListResponse.json'),
            true
        )['Response']['IssueTypes']);

        $this->artisan('project:get-issue-types')
            ->expectsOutput('213217 史诗')
            ->expectsOutput('213218 用户故事')
            ->expectsOutput('213220 任务')
            ->expectsOutput('213221 缺陷')
            ->expectsOutput('213222 子工作项')
            ->assertExitCode(0);
    }
}

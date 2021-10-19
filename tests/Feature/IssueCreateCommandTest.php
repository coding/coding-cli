<?php

namespace Tests\Feature;

use App\Coding\Issue;
use Tests\TestCase;

class IssueCreateCommandTest extends TestCase
{
    private string $codingToken;
    private string $codingTeamDomain;
    private string $codingProjectUri;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codingToken = $this->faker->md5;
        config(['coding.token' => $this->codingToken]);
        $this->codingTeamDomain =  $this->faker->domainWord;
        config(['coding.team_domain' => $this->codingTeamDomain]);
        $this->codingProjectUri = $this->faker->slug;
        config(['coding.project_uri' => $this->codingProjectUri]);
    }

    public function testCreateSuccess()
    {
        $mock = \Mockery::mock(Issue::class, [])->makePartial();
        $this->instance(Issue::class, $mock);

        $mock->shouldReceive('create')->times(1)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateIssueResponse.json'),
            true
        )['Response']['Issue']);

        $this->artisan('issue:create')
            ->expectsQuestion('类型：', 'REQUIREMENT')
            ->expectsQuestion('标题：', $this->faker->title)
            ->expectsOutput('创建成功')
            ->expectsOutput("https://$this->codingTeamDomain.coding.net/p/$this->codingProjectUri/all/issues/2742")
            ->assertExitCode(0);
    }

    public function testCreateFailed()
    {
        $mock = \Mockery::mock(Issue::class, [])->makePartial();
        $this->instance(Issue::class, $mock);

        $mock->shouldReceive('create')->times(1)->andThrow(\Exception::class, json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateIssueFailedResponse.json'),
            true
        )['Response']['Error']['Message']);

        $this->artisan('issue:create')
            ->expectsQuestion('类型：', 'REQUIREMENT')
            ->expectsQuestion('标题：', $this->faker->title)
            ->expectsOutput('Error: issue_custom_field_required')
            ->assertExitCode(1);
    }
}

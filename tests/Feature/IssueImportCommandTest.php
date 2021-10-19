<?php

namespace Tests\Feature;

use App\Coding\Issue;
use App\Coding\Project;
use Tests\TestCase;

class IssueImportCommandTest extends TestCase
{
    private string $codingTeamDomain;
    private string $codingProjectUri;

    protected function setUp(): void
    {
        parent::setUp();
        $codingToken = $this->faker->md5;
        config(['coding.token' => $codingToken]);
        $this->codingTeamDomain = $this->faker->domainWord;
        config(['coding.team_domain' => $this->codingTeamDomain]);
        $this->codingProjectUri = $this->faker->slug;
        config(['coding.project_uri' => $this->codingProjectUri]);
    }

    public function testImportSuccess()
    {
        $mock = \Mockery::mock(Project::class, [])->makePartial();
        $this->instance(Project::class, $mock);

        $mock->shouldReceive('getIssueTypes')->times(1)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'DescribeProjectIssueTypeListResponse.json'),
            true
        )['Response']['IssueTypes']);

        $issueMock = \Mockery::mock(Issue::class, [])->makePartial();
        $this->instance(Issue::class, $issueMock);

        $response = json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateIssueResponse.json'),
            true
        )['Response']['Issue'];
        $results = [];
        for ($i = 1; $i <= 21; $i++) {
            $response['Code'] = $i;
            $results[] = $response;
        }
        $issueMock->shouldReceive('create')->times(21)->andReturn(...$results);

        $this->artisan('issue:import', ['file' => $this->dataDir . 'coding/scrum-issues.csv'])
            ->expectsOutput("https://$this->codingTeamDomain.coding.net/p/$this->codingProjectUri/all/issues/1")
            ->expectsOutput("https://$this->codingTeamDomain.coding.net/p/$this->codingProjectUri/all/issues/2")
            ->expectsOutput("https://$this->codingTeamDomain.coding.net/p/$this->codingProjectUri/all/issues/3")
            ->expectsOutput("https://$this->codingTeamDomain.coding.net/p/$this->codingProjectUri/all/issues/20")
            ->expectsOutput("https://$this->codingTeamDomain.coding.net/p/$this->codingProjectUri/all/issues/21")
            ->assertExitCode(0);
    }
}

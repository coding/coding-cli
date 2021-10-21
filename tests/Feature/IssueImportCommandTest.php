<?php

namespace Tests\Feature;

use App\Coding\Issue;
use App\Coding\Iteration;
use App\Coding\Project;
use Tests\TestCase;

class IssueImportCommandTest extends TestCase
{
    private string $token;
    private string $teamDomain;
    private string $projectUri;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->faker->md5;
        config(['coding.token' => $this->token]);
        $this->teamDomain = $this->faker->domainWord;
        config(['coding.team_domain' => $this->teamDomain]);
        $this->projectUri = $this->faker->slug;
        config(['coding.project_uri' => $this->projectUri]);
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
        $iterationMock = \Mockery::mock(Iteration::class, [])->makePartial();
        $this->instance(Iteration::class, $iterationMock);

        $response = json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateIssueResponse.json'),
            true
        )['Response']['Issue'];
        $results = [];
        for ($i = 1; $i <= 21; $i++) {
            $response['Code'] = $i;
            $results[] = $response;
        }
        $iterationMock->shouldReceive('create')->times(2)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateIterationResponse.json'),
            true
        )['Response']['Iteration']);

        $issueMock->shouldReceive('create')->times(21)->andReturn(...$results);

        $this->artisan('issue:import', ['file' => $this->dataDir . 'coding/scrum-issues.csv'])
            ->expectsOutput("https://$this->teamDomain.coding.net/p/$this->projectUri/all/issues/1")
            ->expectsOutput("https://$this->teamDomain.coding.net/p/$this->projectUri/all/issues/2")
            ->expectsOutput("https://$this->teamDomain.coding.net/p/$this->projectUri/all/issues/3")
            ->expectsOutput("https://$this->teamDomain.coding.net/p/$this->projectUri/all/issues/20")
            ->expectsOutput("https://$this->teamDomain.coding.net/p/$this->projectUri/all/issues/21")
            ->assertExitCode(0);
    }

    public function testImportUserStorySuccess()
    {
        $mock = \Mockery::mock(Project::class, [])->makePartial();
        $this->instance(Project::class, $mock);

        $mock->shouldReceive('getIssueTypes')->times(1)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'DescribeProjectIssueTypeListResponse.json'),
            true
        )['Response']['IssueTypes']);

        $issueMock = \Mockery::mock(Issue::class, [])->makePartial();
        $this->instance(Issue::class, $issueMock);
        $iterationMock = \Mockery::mock(Iteration::class, [])->makePartial();
        $this->instance(Iteration::class, $iterationMock);

        $response = json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateIssueResponse.json'),
            true
        )['Response']['Issue'];
        $response['Code'] = $this->faker->randomNumber();
        $result = $response;
        $iterationMock->shouldReceive('create')->times(1)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateIterationResponse.json'),
            true
        )['Response']['Iteration']);

        $issueMock->shouldReceive('create')->times(1)->withArgs([
            $this->token,
            $this->projectUri,
            [
                'Type' => 'REQUIREMENT',
                'IssueTypeId' => 213218,
                'Name' => '用户可通过手机号注册账户',
                'Priority' => "1",
                'IterationCode' => 2746,
                'DueDate' => '2021-10-21',
                'StoryPoint' => '2',
            ]
        ])->andReturn($result);

        $this->artisan('issue:import', ['file' => $this->dataDir . 'coding/scrum-issue-5.csv'])
            ->expectsOutput('标题：用户可通过手机号注册账户')
            ->expectsOutput("https://$this->teamDomain.coding.net/p/$this->projectUri/all/issues/" .
                $result['Code'])
            ->assertExitCode(0);
    }

    public function testImportSubTask()
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

        $parentIssue = $response;
        $issueMock->shouldReceive('create')->times(1)->withArgs([
            $this->token,
            $this->projectUri,
            [
                'Type' => 'REQUIREMENT',
                'IssueTypeId' => 213218,
                'Name' => '用户可通过手机号注册账户',
                'DueDate' => '2021-10-21',
                'StoryPoint' => '2',
            ]
        ])->andReturn($parentIssue);

        $subTask1 = $response;
        $subTask1['Code'] = $this->faker->randomNumber();
        $issueMock->shouldReceive('create')->times(1)->withArgs([
            $this->token,
            $this->projectUri,
            [
                'Type' => 'SUB_TASK',
                'IssueTypeId' => 213222,
                'Name' => '完成手机号注册的短信验证码发送接口',
                'Priority' => "0",
                'ParentCode' => 2742,
            ]
        ])->andReturn($subTask1);

        $subTask2 = $response;
        $subTask2['Code'] = $this->faker->randomNumber();
        $issueMock->shouldReceive('create')->times(1)->withArgs([
            $this->token,
            $this->projectUri,
            [
                'Type' => 'SUB_TASK',
                'IssueTypeId' => 213222,
                'Name' => '完成通过手机号注册用户的接口',
                'Priority' => "1",
                'ParentCode' => 2742,
            ]
        ])->andReturn($subTask2);

        $this->artisan('issue:import', ['file' => $this->dataDir . 'coding/scrum-issues-5-6-7.csv'])
            ->expectsOutput("https://$this->teamDomain.coding.net/p/$this->projectUri/all/issues/2742")
            ->expectsOutput("https://$this->teamDomain.coding.net/p/$this->projectUri/all/issues/" . $subTask1['Code'])
            ->expectsOutput("https://$this->teamDomain.coding.net/p/$this->projectUri/all/issues/" . $subTask2['Code'])
            ->assertExitCode(0);
    }

    public function testImportFailedIssueTypeNotExists()
    {
        $mock = \Mockery::mock(Project::class, [])->makePartial();
        $this->instance(Project::class, $mock);
        $mock->shouldReceive('getIssueTypes')->times(1)->andReturn([]);

        $this->artisan('issue:import', ['file' => $this->dataDir . 'coding/scrum-issues.csv'])
            ->expectsOutput('Error: 「史诗」类型不存在，请在项目设置中添加')
            ->assertExitCode(1);
    }
}

<?php

namespace Tests\Feature;

use App\Coding\Iteration;
use Carbon\Carbon;
use Tests\TestCase;

class IterationCreateCommandTest extends TestCase
{
    private string $teamDomain;
    private string $projectUri;

    protected function setUp(): void
    {
        parent::setUp();
        $codingToken = $this->faker->md5;
        config(['coding.token' => $codingToken]);
        $this->teamDomain = $this->faker->domainWord;
        config(['coding.team_domain' => $this->teamDomain]);
        $this->projectUri = $this->faker->slug;
        config(['coding.project_uri' => $this->projectUri]);
    }

    public function testCreateSuccess()
    {
        $mock = \Mockery::mock(Iteration::class, [])->makePartial();
        $this->instance(Iteration::class, $mock);

        $mock->shouldReceive('create')->times(1)->andReturn(json_decode(
            file_get_contents($this->dataDir . 'coding/' . 'CreateIterationResponse.json'),
            true
        )['Response']['Iteration']);

        $startAt = $this->faker->date();
        $endAt = Carbon::parse($startAt)->addDays($this->faker->randomNumber())->toDateString();
        $this->artisan('iteration:create', [
                '--goal' => $this->faker->text(),
                '--assignee' => $this->faker->randomNumber(),
            ])
            ->expectsQuestion('开始时间：', $startAt)
            ->expectsQuestion('结束时间：', $endAt)
            ->expectsQuestion('标题：', $startAt . '~' . $endAt . ' 迭代')
            ->expectsOutput('创建成功')
            ->expectsOutput("https://$this->teamDomain.coding.net/p/$this->projectUri/iterations/2746/issues")
            ->assertExitCode(0);
    }
}

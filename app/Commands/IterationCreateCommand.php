<?php

namespace App\Commands;

use App\Coding\Iteration as LocalIteration;
use Carbon\Carbon;
use Coding\Iteration;
use LaravelZero\Framework\Commands\Command;

class IterationCreateCommand extends Command
{
    use WithCoding;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'iteration:create
        {--start_at= : 开始时间，格式：2021-10-20}
        {--end_at= : 结束时间，格式：2021-10-30}
        {--name= : 标题}
        {--goal= : 目标}
        {--assignee= : 处理人 ID}
        {--coding_token= : CODING 令牌}
        {--coding_team_domain= : CODING 团队域名，如 xxx.coding.net 即填写 xxx}
        {--coding_project_uri= : CODING 项目标识，如 xxx.coding.net/p/yyy 即填写 yyy}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = '创建迭代';

    /**
     * Execute the console command.
     *
     */
    public function handle(Iteration $iteration): int
    {
        $this->setCodingApi();
        $iteration->setToken($this->codingToken);

        $data = [
            'ProjectName' => $this->codingProjectUri,
        ];
        $startAt = Carbon::parse($this->option('start_at') ?? $this->ask('开始时间：', Carbon::today()->toDateString()));
        $data['StartAt'] = $startAt->toDateString();
        $endAt = Carbon::parse($this->option('end_at') ?? $this->ask(
            '结束时间：',
            Carbon::today()->addDays(14)->toDateString()
        ));
        $data['EndAt'] = $endAt->toDateString();
        $data['Name'] = $this->option('name') ?? $this->ask('标题：', LocalIteration::generateName($startAt, $endAt));
        $data['Goal'] = $this->option('goal');
        $data['Assignee'] = $this->option('assignee');

        $result = $iteration->create($data);

        $this->info('创建成功');
        $this->info("https://{$this->codingTeamDomain}.coding.net/p/{$this->codingProjectUri}" .
            "/iterations/${result['Code']}/issues");

        return 0;
    }
}

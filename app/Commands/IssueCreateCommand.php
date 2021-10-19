<?php

namespace App\Commands;

use App\Coding\Issue;
use LaravelZero\Framework\Commands\Command;

class IssueCreateCommand extends Command
{
    use WithCoding;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'issue:create
        {--type= : 类型（使用英文），如 DEFECT（缺陷）、REQUIREMENT（需求）、MISSION（任务）、EPIC（史诗）、SUB_TASK（子任务）}
        {--name= : 标题}
        {--priority=2 : 优先级，0（低）, 1（中）, 2（高）, 3（紧急）}
        {--coding_token= : CODING 令牌}
        {--coding_team_domain= : CODING 团队域名，如 xxx.coding.net 即填写 xxx}
        {--coding_project_uri= : CODING 项目标识，如 xxx.coding.net/p/yyy 即填写 yyy}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = '创建事项';

    /**
     * Execute the console command.
     *
     */
    public function handle(Issue $codingIssue): int
    {
        $this->setCodingApi();

        $data = [];
        $data['Type'] = $this->option('type') ?? $this->choice(
            '类型：',
            ['DEFECT', 'REQUIREMENT', 'MISSION', 'EPIC', 'SUB_TASK'],
            0
        );
        $data['Name'] = $this->option('name') ?? $this->ask('标题：');
        $data['Priority'] = $this->option('priority') ?? $this->choice(
            '优先级：',
            ['0', '1', '2', '3'],
            0
        );

        try {
            $result = $codingIssue->create($this->codingToken, $this->codingProjectUri, $data);
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }

        $this->info('创建成功');
        $this->info("https://{$this->codingTeamDomain}.coding.net/p/{$this->codingProjectUri}" .
            "/all/issues/${result['Code']}");

        return 0;
    }
}

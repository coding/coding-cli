<?php

namespace App\Commands;

use Coding\ProjectSetting;
use LaravelZero\Framework\Commands\Command;

class ProjectGetIssueTypesCommand extends Command
{
    use WithCoding;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'project:get-issue-types
        {--coding_token= : CODING 令牌}
        {--coding_team_domain= : CODING 团队域名，如 xxx.coding.net 即填写 xxx}
        {--coding_project_uri= : CODING 项目标识，如 xxx.coding.net/p/yyy 即填写 yyy}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = '获取项目下的事项类型';

    /**
     * Execute the console command.
     *
     */
    public function handle(ProjectSetting $projectSetting): int
    {
        $this->setCodingApi();
        $projectSetting->setToken($this->codingToken);

        $result = $projectSetting->getIssueTypes(['ProjectName' => $this->codingProjectUri]);

        foreach ($result as $item) {
            $this->info($item['Id'] . ' ' . $item['Name']);
        }

        return 0;
    }
}

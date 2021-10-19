<?php

namespace App\Commands;

use App\Coding\Issue;
use App\Coding\Project;
use App\Imports\IssuesImport;
use LaravelZero\Framework\Commands\Command;
use Maatwebsite\Excel\Facades\Excel;

class IssueImportCommand extends Command
{
    use WithCoding;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'issue:import
        {file : 文件（支持格式：csv）}
        {--type= : 类型（使用英文），如 DEFECT（缺陷）、REQUIREMENT（需求）、MISSION（任务）、EPIC（史诗）、SUB_TASK（子任务）}
        {--coding_token= : CODING 令牌}
        {--coding_team_domain= : CODING 团队域名，如 xxx.coding.net 即填写 xxx}
        {--coding_project_uri= : CODING 项目标识，如 xxx.coding.net/p/yyy 即填写 yyy}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = '导入事项';

    /**
     * Execute the console command.
     *
     */
    public function handle(Issue $codingIssue, Project $codingProject): int
    {
        $this->setCodingApi();

        $filePath = $this->argument('file');
        if (!file_exists($filePath)) {
            $this->error("文件不存在：$filePath");
            return 1;
        }

        $result = $codingProject->getIssueTypes($this->codingToken, $this->codingProjectUri);
        $issueTypes = [];
        foreach ($result as $item) {
            $issueTypes[$item['Name']] = $item;
        }
        $rows = Excel::toArray(new IssuesImport(), $filePath)[0];
        foreach ($rows as $row) {
            $data = [
                'Type' => $issueTypes[$row['事项类型']]['IssueType'],
                'IssueTypeId' => $issueTypes[$row['事项类型']]['Id'],
                'Name' => $row['标题'],
                'Priority' => \App\Models\Issue::PRIORITY_MAP[$row['优先级']],
            ];
            try {
                $result = $codingIssue->create($this->codingToken, $this->codingProjectUri, $data);
            } catch (\Exception $e) {
                $this->error('Error: ' . $e->getMessage());
                return 1;
            }
            $this->info("https://{$this->codingTeamDomain}.coding.net/p/{$this->codingProjectUri}" .
                "/all/issues/${result['Code']}");
        }

        return 0;
    }
}

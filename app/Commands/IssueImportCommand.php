<?php

namespace App\Commands;

use App\Coding\Issue;
use App\Coding\Iteration;
use App\Coding\Project;
use Exception;
use LaravelZero\Framework\Commands\Command;
use Rap2hpoutre\FastExcel\Facades\FastExcel;

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

    protected array $iterationMap = [];
    protected array $issueTypes = [];
    protected array $issueCodeMap = [];

    /**
     * Execute the console command.
     *
     */
    public function handle(Issue $codingIssue, Project $codingProject, Iteration $iteration): int
    {
        $this->setCodingApi();

        $filePath = $this->argument('file');
        if (!file_exists($filePath)) {
            $this->error("文件不存在：$filePath");
            return 1;
        }

        $rows = FastExcel::import($filePath);
        if (!empty($rows) && isset($rows[0]['ID'])) {
            $rows = $rows->sortBy('ID');
        }
        foreach ($rows as $row) {
            try {
                $issueResult = $this->createIssueByRow($codingProject, $codingIssue, $iteration, $row);
            } catch (Exception $e) {
                $this->error('Error: ' . $e->getMessage());
                return 1;
            }
            $this->info('标题：' . $row['标题']);
            $this->info("https://{$this->codingTeamDomain}.coding.net/p/{$this->codingProjectUri}" .
                "/all/issues/${issueResult['Code']}");
        }

        return 0;
    }

    private function createIssueByRow(Project $codingProject, Issue $issue, Iteration $iteration, array $row)
    {
        if (empty($this->issueTypes)) {
            $result = $codingProject->getIssueTypes($this->codingToken, $this->codingProjectUri);
            foreach ($result as $item) {
                $this->issueTypes[$item['Name']] = $item;
            }
        }
        if (!isset($this->issueTypes[$row['事项类型']])) {
            throw new Exception('「' . $row['事项类型'] . '」类型不存在，请在项目设置中添加');
        }
        $data = [
            'Type' => $this->issueTypes[$row['事项类型']]['IssueType'],
            'IssueTypeId' => $this->issueTypes[$row['事项类型']]['Id'],
            'Name' => $row['标题'],
            'Priority' => \App\Models\Issue::PRIORITY_MAP[$row['优先级']],
            'IterationCode' => $row['所属迭代'] ? $this->getIterationCode($iteration, $row['所属迭代']) : null,
            'ParentCode' => !empty($row['ParentCode']) ? $this->issueCodeMap[$row['ParentCode']] : null,
        ];
        $result = $issue->create($this->codingToken, $this->codingProjectUri, $data);
        if (isset($row['ID'])) {
            $this->issueCodeMap[$row['ID']] = intval($result['Code']);
        }
        return $result;
    }

    private function getIterationCode(Iteration $iteration, string $name)
    {
        if (!isset($this->iterationMap[$name])) {
            $result = $iteration->create($this->codingToken, $this->codingProjectUri, ['name' => $name]);
            $this->iterationMap[$name] = $result['Code'];
        }
        return $this->iterationMap[$name];
    }
}

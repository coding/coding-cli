<?php

namespace App\Commands;

use Coding\Issue;
use Coding\Iteration;
use Coding\ProjectSetting;
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

    protected array $issueCodeMap = [];
    protected array $issueTypes = [];
    protected array $issueTypeStatus = [];
    protected array $iterationMap = [];

    /**
     * Execute the console command.
     *
     */
    public function handle(Issue $codingIssue, ProjectSetting $projectSetting, Iteration $iteration): int
    {
        $this->setCodingApi();
        $codingIssue->setToken($this->codingToken);
        $iteration->setToken($this->codingToken);
        $projectSetting->setToken($this->codingToken);

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
                $issueResult = $this->createIssueByRow($projectSetting, $codingIssue, $iteration, $row);
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

    private function getIssueTypes(ProjectSetting $projectSetting, array $row): void
    {
        if (empty($this->issueTypes)) {
            $result = $projectSetting->getIssueTypes(['ProjectName' => $this->codingProjectUri]);
            foreach ($result as $item) {
                $this->issueTypes[$item['Name']] = $item;
            }
        }
        if (!isset($this->issueTypes[$row['事项类型']])) {
            throw new Exception('「' . $row['事项类型'] . '」类型不存在，请在项目设置中添加');
        }
    }

    private function getStatusId(ProjectSetting $projectSetting, string $issueTypeName, string $statusName): int
    {
        if (!isset($this->issueTypeStatus[$issueTypeName])) {
            $type = $this->issueTypes[$issueTypeName]['IssueType'];
            $typeId = $this->issueTypes[$issueTypeName]['Id'];
            $result = $projectSetting->getIssueStatus([
                'ProjectName' => $this->codingProjectUri,
                'IssueType' => $type,
                'IssueTypeId' => $typeId
            ]);
            foreach ($result as $item) {
                $tmp = $item['IssueStatus'];
                $this->issueTypeStatus[$issueTypeName][$tmp['Name']] = $tmp['Id'];
            }
        }
        if (!isset($this->issueTypeStatus[$issueTypeName][$statusName])) {
            throw new Exception('「' . $statusName . '」不存在，请在设置中添加');
        }
        return intval($this->issueTypeStatus[$issueTypeName][$statusName]);
    }

    private function createIssueByRow(ProjectSetting $projectSetting, Issue $issue, Iteration $iteration, array $row)
    {
        $this->getIssueTypes($projectSetting, $row);
        $data = [
            'ProjectName' => $this->codingProjectUri,
            'Type' => $this->issueTypes[$row['事项类型']]['IssueType'],
            'IssueTypeId' => $this->issueTypes[$row['事项类型']]['Id'],
            'Name' => $row['标题'],
        ];
        if (!empty($row['优先级'])) {
            $data['Priority'] = \App\Models\Issue::PRIORITY_MAP[$row['优先级']];
        }
        if (!empty($row['所属迭代'])) {
            $data['IterationCode'] = $this->getIterationCode($iteration, $row['所属迭代']);
        }
        if (!empty($row['ParentCode'])) {
            $data['ParentCode'] = $this->issueCodeMap[$row['ParentCode']];
        }
        foreach (
            [
            'Description' => '描述',
            'DueDate' => '截止日期',
            'StartDate' => '开始日期',
            'StoryPoint' => '故事点',
            ] as $english => $chinese
        ) {
            if (!empty($row[$chinese])) {
                $data[$english] = $row[$chinese];
            }
        }
        if (!empty($row['状态'])) {
            $data['StatusId'] = $this->getStatusId($projectSetting, $row['事项类型'], $row['状态']);
        }
        $result = $issue->create($data);
        if (isset($row['ID'])) {
            $this->issueCodeMap[$row['ID']] = intval($result['Code']);
        }
        return $result;
    }

    private function getIterationCode(Iteration $iteration, string $name)
    {
        if (!isset($this->iterationMap[$name])) {
            $result = $iteration->create([
                'ProjectName' => $this->codingProjectUri,
                'Name' => $name,
            ]);
            $this->iterationMap[$name] = $result['Code'];
        }
        return $this->iterationMap[$name];
    }
}

<?php

namespace App\Commands;

use App\Coding\Disk;
use App\Coding\Wiki;
use LaravelZero\Framework\Commands\Command;

class WikiUploadCommand extends Command
{
    use WithCoding;

    private Wiki $codingWiki;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'wiki:upload
        {file : Zip 文件需包含 1 个 Markdown 文件及全部引用图片，Markdown 文件名将作为文档标题，图片使用相对路径}
        {--parent_id=0 : 父页面 ID}
        {--coding_token= : CODING 令牌}
        {--coding_team_domain= : CODING 团队域名，如 xxx.coding.net 即填写 xxx}
        {--coding_project_uri= : CODING 项目标识，如 xxx.coding.net/p/yyy 即填写 yyy}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = '上传 Zip 导入 Wiki';

    /**
     * Execute the console command.
     *
     */
    public function handle(Disk $codingDisk, Wiki $codingWiki): int
    {
        $this->codingDisk = $codingDisk;
        $this->codingWiki = $codingWiki;
        $this->setCodingApi();

        $filePath = $this->argument('file');
        if (!file_exists($filePath)) {
            $this->error("文件不存在：$filePath");
            return 1;
        }
        $parentId = intval($this->option('parent_id'));
        $result = $this->codingWiki->createWikiByUploadZip(
            $this->codingToken,
            $this->codingProjectUri,
            $filePath,
            $parentId
        );
        $this->info('上传成功，正在处理，任务 ID：' . $result['JobId']);

        return 0;
    }
}

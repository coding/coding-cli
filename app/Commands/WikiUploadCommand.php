<?php

namespace App\Commands;

use App\Coding;
use LaravelZero\Framework\Commands\Command;

class WikiUploadCommand extends Command
{
    use WithCoding;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'wiki:upload
        {file : Zip 文件需包含 1 个 Markdown 文件及全部引用图片，Markdown 文件名将作为文档标题，图片使用相对路径}
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
    public function handle(Coding $coding): int
    {
        $this->coding = $coding;
        $this->setCodingApi();

        $filePath = $this->argument('file');
        if (!file_exists($filePath)) {
            $this->error("文件不存在：$filePath");
            return 1;
        }
        $result = $this->coding->createWikiByUploadZip($this->codingToken, $this->codingProjectUri, $filePath);
        $this->info('上传成功，正在处理，任务 ID：' . $result['JobId']);

        return 0;
    }
}

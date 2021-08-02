<?php

namespace App\Commands;

use App\Coding\Disk;
use App\Coding\Wiki;
use Confluence\Content;
use DOMDocument;
use Exception;
use Illuminate\Support\Str;
use LaravelFans\Confluence\Facades\Confluence;
use LaravelZero\Framework\Commands\Command;
use ZipArchive;

class WikiImportCommand extends Command
{
    use WithCoding;

    protected Disk $codingDisk;
    protected Wiki $codingWiki;
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'wiki:import
        {--coding_import_provider= : 数据来源，如 Confluence、MediaWiki}
        {--coding_import_data_type= : 数据类型，如 HTML、API}
        {--coding_import_data_path= : 空间导出的 HTML zip 文件路径，如 ./Confluence-space-export-231543-81.html.zip}
        {--confluence_base_uri= : Confluence API URL，如 http://localhost:8090/confluence/rest/api/}
        {--confluence_username=}
        {--confluence_password=}
        {--coding_token= : CODING 令牌}
        {--coding_team_domain= : CODING 团队域名，如 xxx.coding.net 即填写 xxx}
        {--coding_project_uri= : CODING 项目标识，如 xxx.coding.net/p/yyy 即填写 yyy}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = '从 Confluence 导入 Wiki';

    private \App\Confluence $confluence;
    private DOMDocument $document;

    /**
     * Execute the console command.
     *
     */
    public function handle(Disk $codingDisk, Wiki $codingWiki, \App\Confluence $confluence, DOMDocument $document): int
    {
        $this->codingDisk = $codingDisk;
        $this->codingWiki = $codingWiki;
        $this->confluence = $confluence;
        $this->document = $document;
        $this->setCodingApi();

        $provider = $this->option('coding_import_provider');
        if (is_null($provider)) {
            $provider = config('coding.import.provider') ?? $this->choice(
                '数据来源？',
                ['Confluence', 'MediaWiki'],
                0
            );
        }
        if ($provider != 'Confluence') {
            $this->error('TODO');
            return 1;
        }

        $dataType = $this->option('coding_import_data_type');
        if (is_null($dataType)) {
            $dataType = config('coding.import.data_type') ?? $this->choice(
                '数据类型？',
                ['HTML', 'API'],
                0
            );
        }
        switch ($dataType) {
            case 'HTML':
                return $this->handleConfluenceHtml();
            case 'API':
                return $this->handleConfluenceApi();
            default:
                break;
        }
    }

    private function createWiki($data)
    {
        $result = $this->codingWiki->createWiki($this->codingToken, $this->codingProjectUri, $data);
        $path = $result['Path'];
        $this->info("https://{$this->codingTeamDomain}.coding.net/p/{$this->codingProjectUri}/wiki/${path}");
    }

    private function handleConfluenceApi(): int
    {
        $baseUri = $this->option('confluence_base_uri');
        if (is_null($baseUri)) {
            $baseUri = config('confluence.base_uri') ?? $this->ask(
                'Confluence API 链接：',
                'http://localhost:8090/rest/api/'
            );
        }
        config(['confluence.base_uri' => $baseUri]);

        $username = $this->option('confluence_username');
        if (is_null($username)) {
            $username = config('confluence.username') ?? $this->ask('Confluence 账号：', 'admin');
        }
        $password = $this->option('confluence_password');
        if (is_null($password)) {
            $password = config('confluence.password') ?? $this->ask('Confluence 密码：', '123456');
        }
        config(['confluence.auth' => [$username, $password]]);

        $data = Confluence::resource(Content::class)->index();
        $this->info("已获得 ${data['size']} 条数据");
        if ($data['size'] == 0) {
            return 0;
        }
        $this->info("开始导入 CODING：");
        foreach ($data['results'] as $result) {
            $content = Confluence::resource(Content::class)->show($result['id'], ['expand' => 'body.storage']);
            $this->createWiki([
                'Title' => $content['title'],
                'Content' => $content['body']['storage']['value'],
                'ParentIid' => 0,
            ]);
        }
        return 0;
    }

    private function handleConfluenceHtml(): int
    {
        $htmlDir = $this->unzipConfluenceHtml();
        $filePath = $htmlDir . 'index.html';
        if (!file_exists($filePath)) {
            $this->error("文件不存在：$filePath");
            return 1;
        }
        try {
            libxml_use_internal_errors(true);
            $this->document->loadHTMLFile($filePath);
            $mainContent = $this->document->getElementById('main-content');
            $trList = $mainContent->getElementsByTagName('tr');
            $space = [];
            foreach ($trList as $tr) {
                if ($tr->getElementsByTagName('th')[0]->nodeValue == 'Key') {
                    $space['key'] = $tr->getElementsByTagName('td')[0]->nodeValue;
                } elseif ($tr->getElementsByTagName('th')[0]->nodeValue == 'Name') {
                    $space['name'] = $tr->getElementsByTagName('td')[0]->nodeValue;
                }
            }
            $this->info('空间名称：' . $space['name']);
            $this->info('空间标识：' . $space['key']);

            $pages = $this->confluence->parseAvailablePages($filePath);
            if (empty($pages['tree'])) {
                $this->info("未发现有效数据");
                return 0;
            }
            $this->info('发现 ' . count($pages['tree']) . ' 个一级页面');
            $this->info("开始导入 CODING：");
            $this->uploadConfluencePages($htmlDir, $pages['tree'], $pages['titles']);
        } catch (\ErrorException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }

    private function uploadConfluencePages(string $dataPath, array $tree, array $titles, int $parentId = 0): void
    {
        foreach ($tree as $page => $subPages) {
            $title = $titles[$page];
            $this->info('标题：' . $title);
            $markdown = $this->confluence->htmlFile2Markdown($dataPath . $page);
            $attachments = $this->confluence->parseAttachments($dataPath . $page, $markdown);
            $codingAttachments = $this->codingDisk->uploadAttachments(
                $this->codingToken,
                $this->codingProjectUri,
                $dataPath,
                $attachments
            );
            $markdown = $this->codingWiki->replaceAttachments($markdown, $codingAttachments);
            $mdFilename = substr($page, 0, -5) . '.md';
            $zipFilePath = $this->codingWiki->createMarkdownZip($markdown, $dataPath, $mdFilename);
            $result = $this->codingWiki->createWikiByUploadZip(
                $this->codingToken,
                $this->codingProjectUri,
                $zipFilePath,
                $parentId,
            );
            $this->info('上传成功，正在处理，任务 ID：' . $result['JobId']);
            $wikiId = null;
            $waitingTimes = 0;
            while (true) {
                // HACK 如果上传成功立即查询，会报错：invoke function
                sleep(1);
                try {
                    $jobStatus = $this->codingWiki->getImportJobStatus(
                        $this->codingToken,
                        $this->codingProjectUri,
                        $result['JobId']
                    );
                } catch (Exception $e) {
                    $waitingTimes++;
                    continue;
                }
                if (in_array($jobStatus['Status'], ['wait_process', 'processing']) && $waitingTimes < 10) {
                    $waitingTimes++;
                    continue;
                }
                if ($jobStatus['Status'] == 'success') {
                    $wikiId = intval($jobStatus['Iids'][0]);
                    $this->codingWiki->updateWikiTitle($this->codingToken, $this->codingProjectUri, $wikiId, $title);
                }
                break;
            }
            if (empty($wikiId)) {
                $this->warn('导入失败，跳过');
                continue;
            }
            if (!empty($subPages)) {
                $this->info('发现 ' . count($subPages) . ' 个子页面');
                // TODO tests
                $this->uploadConfluencePages($dataPath, $subPages, $titles, $wikiId);
            }
        }
    }

    private function unzipConfluenceHtml(): string
    {
        $dataPath = $this->option('coding_import_data_path');
        if (is_null($dataPath)) {
            $dataPath = config('coding.import.data_path') ?? trim($this->ask(
                '空间导出的 HTML zip 文件路径',
                './confluence/space1.zip'
            ));
        }

        if (str_ends_with($dataPath, '.zip')) {
            $zip = new ZipArchive();
            $zip->open($dataPath);
            $tmpDir = sys_get_temp_dir() . '/confluence-' . Str::uuid();
            mkdir($tmpDir);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                // HACK crash when zip include root path /
                if ($zip->getNameIndex($i) != '/' && $zip->getNameIndex($i) != '__MACOSX/_') {
                    $zip->extractTo($tmpDir, [$zip->getNameIndex($i)]);
                }
            }
            $zip->close();
            return $tmpDir . '/' . scandir($tmpDir, 1)[0] . '/';
        }
        return str_ends_with($dataPath, '/index.html') ? substr($dataPath, 0, -10) : Str::finish($dataPath, '/');
    }
}

<?php

namespace App\Commands;

use App\Coding\Disk;
use App\Coding\Wiki;
use Confluence\Content;
use DOMDocument;
use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use LaravelFans\Confluence\Facades\Confluence;
use LaravelZero\Framework\Commands\Command;
use ZipArchive;

class WikiImportCommand extends Command
{
    use WithCoding;

    protected Disk $codingDisk;
    protected Wiki $codingWiki;
    protected array $errors = [];

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
        {--save-markdown : 本地保留生成的 Markdown 文件}
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
                $this->handleConfluenceHtml();
                break;
            case 'API':
                $this->handleConfluenceApi();
                break;
            default:
                break;
        }
        if (!empty($this->errors)) {
            $this->info('报错信息汇总：');
        }
        foreach ($this->errors as $error) {
            $this->error($error);
        }
        return count($this->errors);
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
        $path = $this->unzipConfluenceHtml();
        if (str_ends_with($path, '.html')) {
            return $this->uploadConfluencePage($path);
        }
        $htmlDir = $path;
        $filePath = $htmlDir . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($filePath)) {
            $message = "文件不存在：$filePath";
            $this->error($message);
            $this->errors[] = $message;
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

    private function uploadConfluencePages(string $htmlDir, array $tree, array $titles, int $parentId = 0): void
    {
        foreach ($tree as $page => $subPages) {
            $title = $titles[$page];
            $wikiId = $this->uploadConfluencePage($htmlDir . DIRECTORY_SEPARATOR . $page, $title, $parentId);
            if ($wikiId && !empty($subPages)) {
                $this->info('发现 ' . count($subPages) . ' 个子页面');
                // TODO tests
                $this->uploadConfluencePages($htmlDir, $subPages, $titles, $wikiId);
            }
        }
    }

    private function uploadConfluencePage(string $filePath, string $title = '', int $parentId = 0): int
    {
        try {
            $markdown = $this->confluence->htmlFile2Markdown($filePath);
        } catch (FileNotFoundException $e) {
            $message = '页面不存在：' . $filePath;
            $this->error($message);
            $this->errors[] = $message;
            return false;
        }
        libxml_use_internal_errors(true);
        $this->document->loadHTMLFile($filePath);
        if (empty($title)) {
            $title = $this->document->getElementsByTagName('title')[0]->nodeValue;
        }
        $this->info('标题：' . $title);

        $htmlDir = dirname($filePath);
        $page = basename($filePath);
        $markdown = $this->dealAttachments($filePath, $markdown);
        $mdFilename = substr($page, 0, -5) . '.md';
        if ($this->option('save-markdown')) {
            file_put_contents($htmlDir . DIRECTORY_SEPARATOR . $mdFilename, $markdown . "\n");
        }
        $zipFilePath = $this->codingWiki->createMarkdownZip($markdown, $htmlDir, $mdFilename, $title);
        $result = $this->codingWiki->createWikiByUploadZip(
            $this->codingToken,
            $this->codingProjectUri,
            $zipFilePath,
            $parentId,
        );
        $this->info('上传成功，正在处理，任务 ID：' . $result['JobId']);
        $wikiId = null;
        try {
            $jobStatus = $this->codingWiki->getImportJobStatusWithRetry(
                $this->codingToken,
                $this->codingProjectUri,
                $result['JobId']
            );
        } catch (Exception $e) {
            $message = '错误：导入失败，跳过 ' . $title . ' ' . $page;
            $this->error($message);
            $this->errors[] = $message;
            return false;
        }
        if ($jobStatus['Status'] == 'success') {
            $wikiId = intval($jobStatus['Iids'][0]);
        }
        if (empty($wikiId)) {
            $message = '错误：导入失败，跳过 ' . $title . ' ' . $page;
            $this->error($message);
            $this->errors[] = $message;
            return false;
        }
        $this->codingWiki->updateTitle($this->codingToken, $this->codingProjectUri, $wikiId, $title);
        return $wikiId;
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
        return rtrim($dataPath, '/');
    }

    private function dealAttachments(string $filePath, string $markdown): string
    {
        $attachments = $this->confluence->parseAttachments($filePath, $markdown);
        $codingAttachments = $this->codingDisk->uploadAttachments(
            $this->codingToken,
            $this->codingProjectUri,
            dirname($filePath),
            $attachments
        );
        foreach ($codingAttachments as $attachmentPath => $codingAttachment) {
            if (empty($codingAttachment)) {
                $message = '错误：文件上传失败 ' . $attachmentPath;
                $this->error($message);
                $this->errors[] = $message;
            }
        }
        return $this->codingWiki->replaceAttachments($markdown, $codingAttachments);
    }
}

<?php

namespace App\Commands;

use App\Coding;
use Confluence\Content;
use Illuminate\Support\Str;
use LaravelFans\Confluence\Facades\Confluence;
use LaravelZero\Framework\Commands\Command;

class WikiImportCommand extends Command
{
    use WithCoding;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'wiki:import
        {--coding_import_provider= : 数据来源，如 Confluence、MediaWiki}
        {--coding_import_data_type= : 数据类型，如 HTML、API}
        {--coding_import_data_path= : 空间导出的 HTML 目录，如 ./confluence/space1/}
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
    private \DOMDocument $document;

    /**
     * Execute the console command.
     *
     */
    public function handle(Coding $coding, \App\Confluence $confluence, \DOMDocument $document): int
    {
        $this->coding = $coding;
        $this->confluence = $confluence;
        $this->document = $document;
        $this->setCodingApi();

        if ($this->option('coding_import_provider')) {
            $provider = $this->option('coding_import_provider');
        } else {
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

        if ($this->option('coding_import_data_type')) {
            $dataType = $this->option('coding_import_data_type');
        } else {
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
        $result = $this->coding->createWiki($this->codingToken, $this->codingProjectUri, $data);
        $path = $result['Path'];
        $this->info("https://{$this->codingTeamDomain}.coding.net/p/{$this->codingProjectUri}/wiki/${path}");
    }

    private function handleConfluenceApi(): int
    {
        if ($this->option('confluence_base_uri')) {
            $baseUri = $this->option('confluence_base_uri');
        } else {
            $baseUri = config('confluence.base_uri') ?? $this->ask(
                'Confluence API 链接：',
                'http://localhost:8090/rest/api/'
            );
        }
        config(['confluence.base_uri' => $baseUri]);

        if ($this->option('confluence_username')) {
            $username = $this->option('confluence_username');
        } else {
            $username = config('confluence.username') ?? $this->ask('Confluence 账号：', 'admin');
        }
        if ($this->option('confluence_password')) {
            $password = $this->option('confluence_password');
        } else {
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
        if ($this->option('coding_import_data_path')) {
            $dataPath = $this->option('coding_import_data_path');
        } else {
            $dataPath = config('coding.import.data_path') ?? trim($this->ask(
                '空间导出的 HTML 目录',
                './confluence/space1/'
            ));
        }
        $dataPath = str_ends_with($dataPath, '/index.html') ? substr($dataPath, 0, -10) : Str::finish($dataPath, '/');
        $filePath = $dataPath . 'index.html';
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
            } else {
                $this->info('发现 ' . count($pages['tree']) . ' 个一级页面');
            }
            $this->info("开始导入 CODING：");
            $this->uploadConfluencePages($dataPath, $pages['tree'], $pages['titles']);
        } catch (\ErrorException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }

    private function uploadConfluencePages(string $dataPath, array $tree, array $titles): void
    {
        foreach ($tree as $page => $subPages) {
            $this->info('标题：' . $titles[$page]);
            $markdown = $this->confluence->htmlFile2Markdown($dataPath . $page);
            $mdFilename = substr($page, 0, -5) . '.md';
            $zipFilePath = $this->coding->createMarkdownZip($markdown, $dataPath, $mdFilename);
            $result = $this->coding->createWikiByUploadZip(
                $this->codingToken,
                $this->codingProjectUri,
                $zipFilePath
            );
            $this->info('上传成功，正在处理，任务 ID：' . $result['JobId']);
            // TODO 指定 parent
            if (!empty($subPages)) {
                $this->info('发现 ' . count($subPages) . ' 个子页面');
                $this->uploadConfluencePages($dataPath, $subPages, $titles);
            }
        }
    }
}

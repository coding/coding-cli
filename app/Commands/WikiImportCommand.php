<?php

namespace App\Commands;

use App\Coding;
use Confluence\Content;
use Illuminate\Support\Str;
use LaravelFans\Confluence\Facades\Confluence;
use LaravelZero\Framework\Commands\Command;

class WikiImportCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'wiki:import
        {--coding_import_provider= : 数据来源，如 Confluence、MediaWiki}
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
    protected $description = 'import wiki from confluence and so on';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Coding $coding)
    {
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
            $this->info('TODO');
            return;
        }

        if ($this->option('confluence_base_uri')) {
            $baseUri = $this->option('confluence_base_uri');
        } else {
            $baseUri = config('confluence.base_uri') ?? $this->ask(
                $provider . ' API 链接：',
                'http://localhost:8090/rest/api/'
            );
        }
        config(['confluence.base_uri' => $baseUri]);

        if ($this->option('confluence_username')) {
            $username = $this->option('confluence_username');
        } else {
            $username = config('confluence.username') ?? $this->ask($provider . ' 账号：', 'admin');
        }
        if ($this->option('confluence_password')) {
            $password = $this->option('confluence_password');
        } else {
            $password = config('confluence.password') ?? $this->ask($provider . ' 密码：', '123456');
        }
        config(['confluence.auth' => [$username, $password]]);

        if ($this->option('coding_token')) {
            $codingToken = $this->option('coding_token');
        } else {
            $codingToken = config('coding.token') ?? $this->ask('CODING Token：');
        }

        if ($this->option('coding_team_domain')) {
            $codingTeamDomain = $this->option('coding_team_domain');
        } else {
            $codingTeamDomain = config('coding.team_domain') ?? $this->ask('CODING 团队域名：');
        }
        $codingTeamDomain = str_replace('.coding.net', '', $codingTeamDomain);

        if ($this->option('coding_project_uri')) {
            $codingProjectUri = $this->option('coding_project_uri');
        } else {
            $codingProjectUri = config('coding.project_uri') ?? $this->ask('CODING 项目标识：');
        }

        $data = Confluence::resource(Content::class)->index();
        $this->info("已获得 ${data['size']} 条数据");
        if ($data['size'] == 0) {
            return;
        }
        $this->info("开始导入 CODING：");
        foreach ($data['results'] as $result) {
            $content = Confluence::resource(Content::class)->show($result['id'], ['expand' => 'body.storage']);
            $result = $coding->createWiki($codingToken, $codingProjectUri, [
                'Title' => $content['title'],
                'Content' => $content['body']['storage']['value'],
                'ParentIid' => 0,
            ]);
            $path = $result['Response']['Data']['Path'];
            $this->info("https://${codingTeamDomain}.coding.net/p/${codingProjectUri}/wiki/${path}");
        }
    }
}

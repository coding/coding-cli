<?php

namespace App\Commands;

use App\Confluence;
use LaravelZero\Framework\Commands\Command;

class ConfluenceHtml2MarkdownCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'confluence:html2markdown
        {html_path : HTML 文件路径，如 ./confluence/space1/231543.html}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Confluence HTML 转 Markdown';

    /**
     * Execute the console command.
     *
     */
    public function handle(Confluence $confluence): int
    {
        $htmlPath = $this->argument('html_path');
        $dataDir = dirname($htmlPath);
        $page = basename($htmlPath);
        $markdown = $confluence->htmlFile2Markdown($htmlPath);
        $mdFilename = substr($page, 0, -5) . '.md';
        $mdPath = $dataDir . DIRECTORY_SEPARATOR . $mdFilename;
        file_put_contents($mdPath, $markdown . "\n");
        $this->info($mdPath);
        return 0;
    }
}

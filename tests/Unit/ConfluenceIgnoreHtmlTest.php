<?php

namespace Tests\Unit;

use App\Confluence;
use Tests\TestCase;

class ConfluenceIgnoreHtmlTest extends TestCase
{
    public function testIgnoreRecentSpaceActivity()
    {
        $confluence = new Confluence();
        $markdown = file_get_contents($this->dataDir . 'confluence/recent-space-activity-demo.md');
        $newMarkdown = $confluence->htmlFile2Markdown($this->dataDir . 'confluence/recent-space-activity-demo.html');
        $this->assertEquals(trim($markdown), $newMarkdown);
    }

    public function testIgnoreSpaceContributors()
    {
        $confluence = new Confluence();
        $markdown = file_get_contents($this->dataDir . 'confluence/space-contributors-demo.md');
        $newMarkdown = $confluence->htmlFile2Markdown($this->dataDir . 'confluence/space-contributors-demo.html');
        $this->assertEquals(trim($markdown), $newMarkdown);
    }

    public function testIgnoreUserLink()
    {
        $confluence = new Confluence();
        $markdown = file_get_contents($this->dataDir . 'confluence/userlink-demo.md');
        $newMarkdown = $confluence->htmlFile2Markdown($this->dataDir . 'confluence/userlink-demo.html');
        $this->assertEquals(trim($markdown), $newMarkdown);
    }
}

<?php

namespace Tests\Unit;

use App\Confluence;
use Tests\TestCase;

class ConfluenceTest extends TestCase
{
    public function testParsePageHtml()
    {
        $confluence = new Confluence();
        $result = $confluence->parsePageHtml($this->dataDir . 'confluence/space1/text-demo_65601.html', '空间 1');
        $this->assertEquals([
            'title' => 'Text Demo',
            'content' => '你好',
        ], $result);
    }

    public function testHtmlFile2Markdown()
    {
        $confluence = new Confluence();
        $markdown = $confluence->htmlFile2Markdown($this->dataDir . 'confluence/space1/text-demo_65601.html');
        $this->assertEquals("你好\n==", $markdown);
    }
}

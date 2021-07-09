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

    public function testParsePagesTree()
    {
        $document = new \DOMDocument();
        $document->loadHTML('<div id="foo">
            <ul>
                <li>
                    <a href="1.html">page 1</a>
                </li>
                <li>
                    <a href="2.html">page 2</a>
                    <ul>
                        <li>
                            <a href="2.1.html">page 2.1</a>
                            <ul>
                                <li>
                                    <a href="2.1.1.html">page 2.1.1</a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                    <ul>
                        <li>
                            <a href="2.2.html">page 2.2</a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>');
        $xpath = new \DOMXPath($document);
        $confluence = new Confluence();
        $tree = $confluence->parsePagesTree($xpath, $document->getElementById('foo'));
        $this->assertEquals([
            '1.html' => [],
            '2.html' => [
                '2.1.html' => [
                    '2.1.1.html' => [],
                ],
                '2.2.html' => [],
            ]
        ], $tree);
    }
}

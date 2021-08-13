<?php

namespace Tests\Unit;

use App\Confluence;
use DOMDocument;
use DOMXPath;
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

    public function testHtmlFile2MarkdownUserLink()
    {
        $confluence = new Confluence();
        $markdown = file_get_contents($this->dataDir . 'confluence/space1/image-demo_65619.md');
        $newMarkdown = $confluence->htmlFile2Markdown($this->dataDir . 'confluence/space1/image-demo_65619.html');
        $this->assertEquals(trim($markdown), $newMarkdown);
    }

    public function testParsePagesTree()
    {
        $document = new DOMDocument();
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
        $xpath = new DOMXPath($document);
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

    public function testParseAttachmentsIgnoreImages()
    {
        $confluence = new Confluence();
        $htmlFilePath = $this->dataDir . 'confluence/space1/image-demo_65619.html';
        $markdown = $confluence->htmlFile2Markdown($htmlFilePath);
        $attachments = $confluence->parseAttachments($htmlFilePath, $markdown);
        $this->assertEquals([], $attachments);
    }

    public function testParseAttachmentsNoIgnoreImages()
    {
        $confluence = new Confluence();
        $htmlFilePath = $this->dataDir . 'confluence/space1/image-demo_65619.html';
        $attachments = $confluence->parseAttachments($htmlFilePath);
        $this->assertEquals([
            'attachments/65619/65623.png' => 'github-ubuntu-16.04.png',
            'attachments/65619/65624.png' => 'coding-logo.png',
        ], $attachments);
    }

    public function testParseAttachmentsSuccess()
    {
        $confluence = new Confluence();
        $htmlFilePath = $this->dataDir . 'confluence/space1/attachment-demo_65615.html';
        $markdown = $confluence->htmlFile2Markdown($htmlFilePath);
        $attachments = $confluence->parseAttachments($htmlFilePath, $markdown);
        $this->assertEquals([
            'attachments/65615/65616.txt' => 'Lorem Ipsum 2021-06-08T10_55_27+0800.txt'
        ], $attachments);
    }

    public function testParseAttachmentsOfIndex()
    {
        $confluence = new Confluence();
        $htmlFilePath = $this->dataDir . 'confluence/space1/index.html';
        $markdown = $confluence->htmlFile2Markdown($htmlFilePath);
        $attachments = $confluence->parseAttachments($htmlFilePath, $markdown);
        $this->assertEquals([], $attachments);
    }

    public function testTable()
    {
        $confluence = new Confluence();
        $markdown = file_get_contents($this->dataDir . 'confluence/table-demo.md');
        $newMarkdown = $confluence->htmlFile2Markdown($this->dataDir . 'confluence/table-demo.html');
        $this->assertEquals(trim($markdown), $newMarkdown);
    }
}

<?php

namespace App;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\File;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;

class Confluence
{
    private DOMDocument $document;
    private HtmlConverter $htmlConverter;
    private array $pageTitles;

    public function __construct(DOMDocument $document = null, HtmlConverter $htmlConverter = null)
    {
        $this->document = $document ?? new DOMDocument();
        $this->htmlConverter = $htmlConverter ?? new HtmlConverter();
        $this->htmlConverter->getConfig()->setOption('strip_tags', true);
        $this->htmlConverter->getEnvironment()->addConverter(new TableConverter());
    }

    public function parsePageHtml(string $filename, string $spaceName): array
    {
        libxml_use_internal_errors(true);
        $this->document->loadHTMLFile($filename);
        $title = trim($this->document->getElementById('title-text')->nodeValue);
        $title = str_replace($spaceName . ' : ', '', $title);

        $content = trim($this->document->getElementById('main-content')->nodeValue);
        return [
            'title' => $title,
            'content' => $content,
        ];
    }

    public function htmlFile2Markdown(string $filename): string
    {
        $html = preg_replace(
            [
                '|<span class="confluence-embedded-file-wrapper">.*</span>|',
                '|<div class="drop-zone-empty-text">.*</div>|s',
                '|<li class="drop-zone-text hidden">.*</li>|s',
                '|<a class="confluence-userlink url fn".*</a>|',
                '|<img .* src="data:.*/>|',
            ],
            '',
            File::get($filename)
        );
        libxml_use_internal_errors(true);
        $this->document->loadHTML($html);

        $contentElement = $this->document->getElementById('main-content');
        $divElements = $contentElement->getElementsByTagName('div');
        $needDeleteElements = [];
        foreach ($divElements as $divElement) {
            if (
                in_array($divElement->getAttribute('class'), [
                'recently-updated recently-updated-social',
                'plugin-contributors',
                ])
            ) {
                $needDeleteElements[] = $divElement->parentNode;
            }
        }
        for ($i = count($needDeleteElements); $i > 0; $i--) {
            $element = $needDeleteElements[$i - 1];
            $element->parentNode->removeChild($element);
        }
        $html = $this->document->saveHTML($contentElement);
        $markdown = $this->htmlConverter->convert($html);
        $markdown = preg_replace("/[ ]*\n/s", "\n", $markdown);
        return preg_replace("/\n\n\n/s", "\n", $markdown);
    }

    /**
     * parse attachments. if markdown is not empty, ignore images in it.
     */
    public function parseAttachments($htmlFilename, $markdownContent = ''): array
    {
        libxml_use_internal_errors(true);
        $this->document->loadHTMLFile($htmlFilename);
        $divElements = $this->document->getElementById('content')->getElementsByTagName('div');
        $attachmentDivElement = null;
        foreach ($divElements as $divElement) {
            if ($divElement->getAttribute('class') != 'pageSection group') {
                continue;
            }
            $h2Element = $divElement->getElementsByTagName('h2')[0];
            if (!empty($h2Element) && $h2Element->getAttribute('id') == 'attachments') {
                $attachmentDivElement = $divElement;
                break;
            }
        }
        if (empty($attachmentDivElement)) {
            return [];
        }
        $aElements = $attachmentDivElement->getElementsByTagName('a');
        $attachments = [];
        foreach ($aElements as $aElement) {
            $filePath = $aElement->getAttribute('href');
            $filename = $aElement->nodeValue;
            if (!str_contains($markdownContent, "![](${filePath}")) {
                $attachments[$filePath] = $filename;
            }
        }
        return $attachments;
    }

    /**
     * @return array ['tree' => "array", 'titles' => "array"]
     */
    public function parseAvailablePages(string $filename): array
    {
        $this->document->loadHTMLFile($filename);
        $divElements = $this->document->getElementById('content')->getElementsByTagName('div');
        $divElement = null;
        foreach ($divElements as $divElement) {
            if ($divElement->getAttribute('class') != 'pageSection') {
                continue;
            }
            $h2Element = $divElement->getElementsByTagName('h2')[0];
            if (!empty($h2Element) && $h2Element->nodeValue == 'Available Pages:') {
                break;
            }
        }
        if (empty($divElement)) {
            return [
                'tree' => [],
                'titles' => [],
            ];
        }
        $xpath = new DOMXPath($this->document);
        return [
            'tree' => $this->parsePagesTree($xpath, $divElement),
            'titles' => $this->pageTitles,
        ];
    }

    public function parsePagesTree(DOMXPath $xpath, \DOMElement $parentElement)
    {
        $liElements = $xpath->query('ul/li', $parentElement);
        if ($liElements->count() == 0) {
            return [];
        }

        $tree = [];
        foreach ($liElements as $liElement) {
            $aElement = $xpath->query('a', $liElement)->item(0);
            $href = $aElement->getAttribute('href');
            $this->pageTitles[$href] = $aElement->nodeValue;
            $tree[$href] = $this->parsePagesTree($xpath, $liElement);
        }
        return $tree;
    }
}

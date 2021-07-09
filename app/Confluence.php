<?php

namespace App;

use JetBrains\PhpStorm\ArrayShape;
use League\HTMLToMarkdown\HtmlConverter;
use phpDocumentor\Reflection\Types\Array_;

class Confluence
{
    private \DOMDocument $document;
    private HtmlConverter $htmlConverter;

    public function __construct(\DOMDocument $document = null, HtmlConverter $htmlConverter = null)
    {
        $this->document = $document ?? new \DOMDocument();
        $this->htmlConverter = $htmlConverter ?? new HtmlConverter();
        $this->htmlConverter->getConfig()->setOption('strip_tags', true);
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

    public function htmlFile2Markdown(string $filename)
    {
        libxml_use_internal_errors(true);
        $this->document->loadHTMLFile($filename);

        $html = $this->document->saveHTML($this->document->getElementById('main-content'));
        return $this->htmlConverter->convert($html);
    }

    /**
     * @param \DOMDocument $document
     * @return array ['tree' => "array", 'titles' => "array"]
     * @todo document 对象和本类别的方法不一致
     */
    public function parseAvailablePages(\DOMDocument $document): array
    {
        $pages = [
            'tree' => [],
            'titles' => [],
        ];
        $divElements = $document->getElementById('content')->getElementsByTagName('div');
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
            return $pages;
        }

        $xpath = new \DOMXPath($document);
        $firstLevelLiElements = $xpath->query('ul/li', $divElement);
        if ($firstLevelLiElements->count() == 0) {
            return $pages;
        }

        foreach ($firstLevelLiElements as $firstLevelLiElement) {
            $aElement = $xpath->query('a', $firstLevelLiElement)->item(0);
            $pages['tree'][] = $aElement->getAttribute('href');
            $pages['titles'][$aElement->getAttribute('href')] = $aElement->nodeValue;
        }
        return $pages;
    }
}

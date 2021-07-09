<?php

namespace App;

use JetBrains\PhpStorm\ArrayShape;
use League\HTMLToMarkdown\HtmlConverter;
use phpDocumentor\Reflection\Types\Array_;

class Confluence
{
    private \DOMDocument $document;
    private HtmlConverter $htmlConverter;
    private array $pageTitles;

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
        $xpath = new \DOMXPath($this->document);
        return [
            'tree' => $this->parsePagesTree($xpath, $divElement),
            'titles' => $this->pageTitles,
        ];
    }

    public function parsePagesTree(\DOMXPath $xpath, \DOMElement $parentElement)
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

<?php

namespace App;

use League\HTMLToMarkdown\HtmlConverter;

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
}

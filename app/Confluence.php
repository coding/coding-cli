<?php

namespace App;

class Confluence
{
    private \DOMDocument $document;

    public function __construct(\DOMDocument $document = null)
    {
        $this->document = $document ?? new \DOMDocument();
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
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfluenceHtml2MarkdownCommandTest extends TestCase
{
    public function testHandleConfluenceHtmlSuccess()
    {
        $this->artisan('confluence:html2markdown', [
            'html_path' => $this->dataDir . 'confluence/space1/text-demo_65601.html'
        ])
            ->expectsOutput($this->dataDir . 'confluence/space1/text-demo_65601.md')
            ->assertExitCode(0);
        $this->assertEquals("你好\n==\n", file_get_contents($this->dataDir . 'confluence/space1/text-demo_65601.md'));
    }
}

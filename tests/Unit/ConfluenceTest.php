<?php

namespace Tests\Unit;

use App\Confluence;
use Tests\TestCase;

class ConfluenceTest extends TestCase
{
    public function testParsePageHtml()
    {
        $confluence = new Confluence();
        $result = $confluence->parsePageHtml($this->dataDir . 'confluence/space-1/text-demo_65601.html', 'Demo');
        $this->assertEquals([
            'title' => 'Text Demo',
            'content' => '你好',
        ], $result);
    }
}

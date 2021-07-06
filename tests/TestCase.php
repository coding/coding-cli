<?php

namespace Tests;

use Illuminate\Foundation\Testing\WithFaker;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use WithFaker;

    protected string $dataDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataDir = __DIR__ . '/data/';
    }
}

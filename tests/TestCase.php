<?php

namespace Tests;

use Illuminate\Foundation\Testing\WithFaker;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use WithFaker;

    protected string $dataDir = __DIR__ . '/data/';
}

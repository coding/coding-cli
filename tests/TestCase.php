<?php

namespace Tests;

use Illuminate\Foundation\Testing\WithFaker;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use WithFaker;
}

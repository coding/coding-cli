<?php

namespace Tests\Unit;

use App\Coding\Iteration;
use Carbon\Carbon;
use Tests\TestCase;

class CodingIterationTest extends TestCase
{
    public function testGenerateName()
    {
        $startAt = Carbon::parse('2021-10-20');
        $endAt = Carbon::parse('2021-10-30');
        $result = Iteration::generateName($startAt, $endAt);
        $this->assertEquals("2021/10/20-10/30 迭代", $result);

        $startAt = Carbon::parse('2021-12-27');
        $endAt = Carbon::parse('2022-01-07');
        $result = Iteration::generateName($startAt, $endAt);
        $this->assertEquals("2021/12/27-2022/01/07 迭代", $result);
    }
}

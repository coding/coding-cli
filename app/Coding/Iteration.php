<?php

namespace App\Coding;

use Carbon\Carbon;

class Iteration extends Base
{
    public static function generateName(Carbon $startAt, Carbon $endAt): string
    {
        $endFormat = $startAt->year == $endAt->year ? 'm/d' : 'Y/m/d';
        return $startAt->format('Y/m/d') . '-' . $endAt->format($endFormat) . ' 迭代';
    }
}

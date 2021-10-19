<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class IssuesImport implements WithHeadingRow
{
    public function __construct()
    {
        HeadingRowFormatter::default('none');
    }
}

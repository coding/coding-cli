<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'name',
        'priority',
    ];

    public const PRIORITY_MAP = [
        '低' => '0',
        '中' => '1',
        '高' => '2',
        '紧急' => '3',
    ];
}

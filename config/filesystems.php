<?php

return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => getcwd(),
        ],
        'cos' => [
            'driver' => 'cos',
            'region'          => env('COS_REGION', 'ap-guangzhou'),
            'credentials'     => [
                'appId'     => env('COS_APP_ID'),
                'secretId'  => env('COS_SECRET_ID'),
                'secretKey' => env('COS_SECRET_KEY'),
                'token'     => env('COS_TOKEN'),
            ],
            'timeout'         => env('COS_TIMEOUT', 60),
            'connect_timeout' => env('COS_CONNECT_TIMEOUT', 60),
            'bucket'          => env('COS_BUCKET'),
            'cdn'             => env('COS_CDN'),
            'scheme'          => env('COS_SCHEME', 'https'),
            'read_from_cdn'   => env('COS_READ_FROM_CDN', false),
        ],
    ],
];

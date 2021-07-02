<?php

return [
    'base_uri' => env('CONFLUENCE_BASE_URI'),
    'auth' => [
        env('CONFLUENCE_AUTH_USERNAME', 'admin'),
        env('CONFLUENCE_AUTH_PASSWORD', '123456'),
    ]
];

<?php

return [
    'token' => env('CODING_TOKEN'),
    'team_domain' => env('CODING_TEAM_DOMAIN'),
    'project_uri' => env('CODING_PROJECT_URI'),
    'import' => [
        'provider' => env('CODING_IMPORT_PROVIDER'),
        'data_type' => env('CODING_IMPORT_DATA_TYPE'),
        'data_path' => env('CODING_IMPORT_DATA_PATH'),
    ],
];

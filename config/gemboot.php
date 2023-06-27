<?php

return [

    'auth' => [
        'base_url' => env('GEMBOOT_AUTH_BASE_URL', 'http://192.168.0.12:3000'),
        'base_api' => env('GEMBOOT_AUTH_BASE_API', 'http://192.168.0.12:3000/api/auth'),
    ],

    'file_handler' => [
        'base_url' => env('GEMBOOT_FILE_HANDLER_BASE_URL', 'http://192.168.0.12:3000/file-handler'),
    ],

    'gateway' => [
        'base_url' => env('GEMBOOT_GW_BASE_URL', 'http://192.168.0.12/'),
        'base_url_auth' => env('GEMBOOT_GW_BASE_URL_AUTH', 'http://192.168.0.12/auth/'),
    ],

];

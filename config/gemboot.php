<?php

return [

    'auth' => [
        'base_url' => env('GEMBOOT_AUTH_BASE_URL', 'YOUR GEMBOOT AUTH BASE URL HERE'),
        'base_api' => env('GEMBOOT_AUTH_BASE_API', 'YOUR GEMBOOT AUTH BASE API HERE'),
    ],

    'file_handler' => [
        'base_url' => env('GEMBOOT_FILE_HANDLER_BASE_URL', 'YOUR GEMBOOT FILE HANDLER BASE URL HERE'),
    ],

    'gateway' => [
        'base_url' => env('GEMBOOT_GW_BASE_URL', 'YOUR GEMBOOT GW BASE URL HERE'),
        'base_url_auth' => env('GEMBOOT_GW_BASE_URL_AUTH', 'YOUR GEMBOOT GW BASE URL AUTH HERE'),
    ],

    'notifications' => [
        'enable' => env('GEMBOOT_NOTIFICATIONS_ENABLE', true),

        'telegram' => [
            'chat_id' => env('GEMBOOT_TELEGRAM_CHAT_ID', 'YOUR TELEGRAM CHAT ID HERE'),
            'token' => env('GEMBOOT_TELEGRAM_BOT_TOKEN', 'YOUR BOT TOKEN HERE'),
        ],
    ],

    'response' => [
        'compressed' => env('GEMBOOT_RESPONSE_COMPRESSED', true),
    ],

];

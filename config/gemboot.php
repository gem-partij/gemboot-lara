<?php

return [

    'auth' => [
        // Not read by Gemboot; kept for backward compatibility, removed in 9.0.
        'base_url' => env('GEMBOOT_AUTH_BASE_URL'),
        'base_api' => env('GEMBOOT_AUTH_BASE_API'),

        // Seconds to cache auth service answers per token (me, validate-token,
        // has-role, has-permission-to). 0 disables the cache. A revoked token keeps
        // working until its entries expire; AuthLibrary::logout() clears them.
        'cache_ttl' => env('GEMBOOT_AUTH_CACHE_TTL', 0),

        // Not read by Gemboot; kept for backward compatibility, removed in 9.0.
        'fallback' => [
            'base_url' => env('GEMBOOT_AUTH_BASE_URL_FALLBACK'),
            'base_api' => env('GEMBOOT_AUTH_BASE_API_FALLBACK'),
        ],
    ],

    'sso' => [
        'auth_service_url' => env('GEMBOOT_AUTH_SERVICE_URL'),
        'user_service_url' => env('GEMBOOT_USER_SERVICE_URL'),
        'validate_token_url' => env('GEMBOOT_SSO_VALIDATE_TOKEN_URL'),
        'get_user_url' => env('GEMBOOT_SSO_GET_USER_URL'),
        'cache_ttl' => env('GEMBOOT_SSO_CACHE_TTL', 300),

        'fallback' => [
            'auth_service_url' => env('GEMBOOT_AUTH_SERVICE_URL_FALLBACK'),
            'user_service_url' => env('GEMBOOT_USER_SERVICE_URL_FALLBACK'),
            'validate_token_url' => env('GEMBOOT_SSO_VALIDATE_TOKEN_URL_FALLBACK'),
            'get_user_url' => env('GEMBOOT_SSO_GET_USER_URL_FALLBACK'),
        ],
    ],

    'file_handler' => [
        'base_url' => env('GEMBOOT_FILE_HANDLER_BASE_URL'),
    ],

    'gateway' => [
        'base_url' => env('GEMBOOT_GW_BASE_URL'),
        'base_url_auth' => env('GEMBOOT_GW_BASE_URL_AUTH'),
    ],

    'notifications' => [
        'enable' => env('GEMBOOT_NOTIFICATIONS_ENABLE', true),

        'telegram' => [
            'chat_id' => env('GEMBOOT_TELEGRAM_CHAT_ID'),
            'token' => env('GEMBOOT_TELEGRAM_BOT_TOKEN'),
        ],
    ],

    // Outgoing HTTP calls to the auth service (AuthLibrary).
    'http' => [
        // true, false, or a path to a CA bundle. Set false only for local development
        // against a self-signed certificate.
        'verify' => env('GEMBOOT_HTTP_VERIFY', true),
        'timeout' => env('GEMBOOT_HTTP_TIMEOUT', 30),
        'connect_timeout' => env('GEMBOOT_HTTP_CONNECT_TIMEOUT', 10),
    ],

    'pagination' => [
        // Upper limit for ?page_len. null removes the limit.
        'max_page_len' => env('GEMBOOT_MAX_PAGE_LEN', 1000),
    ],

    'response' => [
        // Deprecated, removed in 9.0. Compression through ob_gzhandler; leave
        // compression to the web server. It also misbehaves under Octane and FrankenPHP.
        'compressed' => env('GEMBOOT_RESPONSE_COMPRESSED', false),
        'send_header_error' => env('GEMBOOT_SEND_HEADER_ERROR', true),
    ],

];

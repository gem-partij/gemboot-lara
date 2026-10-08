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

        // Authorization values longer than this, or with characters a token can't
        // contain, are rejected (401) without calling the auth service.
        'max_token_length' => env('GEMBOOT_AUTH_MAX_TOKEN_LENGTH', 8192),

        // Failed attempts (rejected or malformed tokens, failed logins) allowed per
        // client IP within decay_seconds; then 429 without calling the auth
        // service. Requests without a token don't count. max = 0 turns it off.
        // Behind a proxy, configure Laravel's TrustProxies.
        'failed_attempts' => [
            'max' => env('GEMBOOT_AUTH_MAX_FAILED_ATTEMPTS', 60),
            'decay_seconds' => env('GEMBOOT_AUTH_FAILED_ATTEMPTS_DECAY', 60),
        ],

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

    // Deprecated since 8.8, removed in 9.0: forward errors with a Laravel log
    // channel instead (see docs/RESPONSES.md, "Error alerts").
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

    'query' => [
        // Also accept the standard parameter names ?sort=-name,price, ?per_page=,
        // and ?filter[field]=value next to order/atoz, page_len, and search.
        // Off by default in 8.x (clients may already send these names); on in 9.0.
        'standard_parameters' => env('GEMBOOT_STANDARD_QUERY_PARAMETERS', false),
    ],

    'pagination' => [
        // Upper limit for ?page_len. null removes the limit.
        'max_page_len' => env('GEMBOOT_MAX_PAGE_LEN', 1000),
    ],

    'response' => [
        // Headers added to every Gemboot JSON response. Set
        // GEMBOOT_SECURITY_HEADERS=false to turn them off, or publish this file
        // and change the list (e.g. to allow caching of public endpoints).
        'security_headers' => env('GEMBOOT_SECURITY_HEADERS', true) ? [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ] : [],

        // Deprecated, removed in 9.0. Compression through ob_gzhandler; leave
        // compression to the web server. It also misbehaves under Octane and FrankenPHP.
        'compressed' => env('GEMBOOT_RESPONSE_COMPRESSED', false),
        'send_header_error' => env('GEMBOOT_SEND_HEADER_ERROR', true),
    ],

];

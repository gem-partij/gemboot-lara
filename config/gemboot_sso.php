<?php

return [
    'auth_service_url' => env('GEMBOOT_AUTH_SERVICE_URL'),
    'user_service_url' => env('GEMBOOT_USER_SERVICE_URL'),
    'cache_ttl' => env('GEMBOOT_SSO_CACHE_TTL', 300),
];

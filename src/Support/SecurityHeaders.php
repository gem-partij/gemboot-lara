<?php

namespace Gemboot\Support;

/**
 * Headers added to every JSON response Gemboot builds.
 */
final class SecurityHeaders
{
    public const DEFAULTS = [
        // Proxies and browsers must not keep copies of API responses, which
        // usually contain personal data.
        'Cache-Control' => 'no-store',
        // Browsers must treat the body as JSON, never guess another type.
        'X-Content-Type-Options' => 'nosniff',
    ];

    /**
     * The configured headers (gemboot.response.security_headers). A config
     * published before 8.3 lacks the key and gets the defaults; an empty
     * array turns the headers off.
     */
    public static function get(): array
    {
        $headers = config('gemboot.response.security_headers', self::DEFAULTS);

        return is_array($headers) ? $headers : self::DEFAULTS;
    }
}

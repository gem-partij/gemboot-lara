<?php

namespace Gemboot\Support;

/**
 * Cheap local check of an Authorization header, before anything is sent to the
 * auth service. Rejects values that can't be a token, so floods of junk tokens
 * don't turn every Gemboot service into an amplifier against the auth service.
 */
final class TokenFormat
{
    public const DEFAULT_MAX_LENGTH = 8192;

    /**
     * An optional scheme name ("Bearer", or none for auth services that take the
     * raw token) followed by a token in the RFC 6750 b64token character set.
     */
    private const PATTERN = '/^(?:[A-Za-z][A-Za-z0-9_-]*\s+)?[A-Za-z0-9\-._~+\/]+=*$/';

    public static function isPlausible(?string $authorization): bool
    {
        $value = trim((string) $authorization);
        if ($value === '') {
            return false;
        }

        $max = (int) config('gemboot.auth.max_token_length', self::DEFAULT_MAX_LENGTH);
        if ($max > 0 && strlen($value) > $max) {
            return false;
        }

        return (bool) preg_match(self::PATTERN, $value);
    }
}

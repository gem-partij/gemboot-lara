<?php

namespace Gemboot\Support;

use Illuminate\Support\Facades\Context;

/**
 * The ID of the current request, shared by every service one user action passes
 * through. The AssignRequestId middleware sets it; Gemboot's calls to the auth
 * service, the SSO user service, and the file handler send it along.
 */
final class RequestId
{
    /**
     * Key in Laravel's Context. Context values are added to every log entry and
     * carried into queued jobs dispatched during the request.
     */
    public const CONTEXT_KEY = 'request_id';

    public const DEFAULT_HEADER = 'X-Request-Id';

    /**
     * Letters, digits, and . _ : - only, at most 128 characters. Covers UUIDs,
     * ULIDs, and the IDs proxies generate, and keeps line breaks and other
     * characters that could forge log lines out of the logs.
     */
    private const PATTERN = '/^[A-Za-z0-9._:-]{1,128}$/';

    public static function header(): string
    {
        $header = config('gemboot.request_id.header');

        return is_string($header) && $header !== '' ? $header : self::DEFAULT_HEADER;
    }

    public static function current(): ?string
    {
        $id = Context::get(self::CONTEXT_KEY);

        return self::isValid($id) ? $id : null;
    }

    /**
     * Headers for an outgoing call: the request ID if there is one, otherwise none.
     *
     * @return array<string, string>
     */
    public static function headers(): array
    {
        $id = self::current();

        return $id === null ? [] : [self::header() => $id];
    }

    public static function isValid(mixed $id): bool
    {
        return is_string($id) && preg_match(self::PATTERN, $id) === 1;
    }
}

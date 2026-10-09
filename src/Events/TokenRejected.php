<?php

namespace Gemboot\Events;

use Gemboot\Support\RequestId;

/**
 * A request's token was refused: malformed (rejected locally, without asking
 * the auth service) or rejected by the auth service. Requests without a token
 * don't fire it. The token itself is never included.
 */
class TokenRejected
{
    public const MALFORMED = 'malformed';
    public const REJECTED = 'rejected';

    public function __construct(
        /** TokenRejected::MALFORMED or TokenRejected::REJECTED. */
        public readonly string $reason,
        /** Where: "token-validated", "token-validated:client", "gemboot-guard", or "sso-guard". */
        public readonly string $source,
        /** The client IP (as Laravel sees it; configure TrustProxies behind a proxy). */
        public readonly ?string $ip,
        /** The request ID (AssignRequestId middleware), if any. */
        public readonly ?string $requestId,
    ) {
    }

    /**
     * Fire the event for the current request.
     *
     * @internal
     */
    public static function dispatch(string $reason, string $source): void
    {
        event(new self($reason, $source, request()->ip(), RequestId::current()));
    }
}

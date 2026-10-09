<?php

namespace Gemboot\Events;

/**
 * The auth service (or the SSO guard's user service) gave no usable answer:
 * no connection, or a 5xx reply. Fired once per failed call.
 *
 * Listen to it for alerts and metrics, e.g. notify the team after several
 * outages within a minute.
 */
class AuthServiceUnavailable
{
    public function __construct(
        /** The endpoint called, e.g. "me", "has-role", or "user/me" (SSO guard). */
        public readonly string $endpoint,
        /** HTTP status of the reply; 0 when no connection could be made. */
        public readonly int $status,
        /** True when the request was served with the last good answer (outage grace period). */
        public readonly bool $servedFromLastGoodAnswer,
        /** The request ID (AssignRequestId middleware), if any. */
        public readonly ?string $requestId,
    ) {
    }
}

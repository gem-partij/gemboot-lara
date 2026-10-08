<?php

namespace Gemboot\Support;

use Gemboot\Testing\FakeAuthService;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Limits failed authentication attempts (rejected or malformed tokens, failed
 * logins) per client IP. Over the limit, Gemboot answers 429 without asking the
 * auth service. Requests without any token don't count.
 *
 * Behind a proxy or load balancer, configure Laravel's TrustProxies, otherwise
 * every client shares the proxy's IP.
 */
final class FailedAuthLimiter
{
    public const DEFAULT_MAX = 60;

    public const DEFAULT_DECAY_SECONDS = 60;

    public static function tooManyAttempts(): bool
    {
        return self::enabled() && RateLimiter::tooManyAttempts(self::key(), self::max());
    }

    public static function hit(): void
    {
        if (self::enabled()) {
            RateLimiter::hit(self::key(), self::decaySeconds());
        }
    }

    /**
     * Seconds until the client may try again.
     */
    public static function availableIn(): int
    {
        return max(1, RateLimiter::availableIn(self::key()));
    }

    /**
     * The 429 answer, in the Gemboot format, or as the short {"status": ...} body
     * of token-validated:client.
     */
    public static function response(bool $short = false)
    {
        $body = $short
            ? ['status' => 'Too Many Requests']
            : ['status' => 429, 'message' => 'Too Many Requests', 'data' => ['error' => 'Too many failed authentication attempts']];

        return response()->json($body, 429)
            ->withHeaders(SecurityHeaders::get())
            ->header('Retry-After', (string) self::availableIn());
    }

    public static function max(): int
    {
        return (int) config('gemboot.auth.failed_attempts.max', self::DEFAULT_MAX);
    }

    private static function decaySeconds(): int
    {
        return max(1, (int) config('gemboot.auth.failed_attempts.decay_seconds', self::DEFAULT_DECAY_SECONDS));
    }

    private static function enabled(): bool
    {
        // Off while GembootAuth::fake() is active, so test suites can't hit 429.
        return self::max() > 0 && !app()->bound(FakeAuthService::class);
    }

    private static function key(): string
    {
        return 'gemboot-auth-failed:' . sha1((string) request()->ip());
    }
}

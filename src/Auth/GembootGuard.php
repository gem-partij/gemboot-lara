<?php

namespace Gemboot\Auth;

use Gemboot\Events\TokenRejected;
use Gemboot\Exceptions\TooManyRequestsException;
use Gemboot\Support\FailedAuthLimiter;
use Gemboot\Support\TokenFormat;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The "gemboot" guard driver: the bearer token's user, as answered by the bound
 * TokenVerifier (by default the auth service's `me` endpoint).
 *
 *     // config/auth.php
 *     'guards' => ['api' => ['driver' => 'gemboot']],
 */
class GembootGuard implements Guard
{
    use GuardHelpers;

    /** True when $user was resolved from the request's token (not set with setUser()). */
    protected bool $userFromToken = false;

    /** Token already rejected for this request; counted once toward the limit. */
    protected ?string $rejectedToken = null;

    public function __construct(
        protected TokenVerifier $verifier,
        protected Request $request,
    ) {
    }

    /**
     * The default guard, if it uses the gemboot driver.
     *
     * @internal
     */
    public static function fromDefault(): ?self
    {
        $name = config('auth.defaults.guard');
        if (!is_string($name) || config("auth.guards.{$name}.driver") !== 'gemboot') {
            return null;
        }

        $guard = Auth::guard($name);

        return $guard instanceof self ? $guard : null;
    }

    /**
     * The default gemboot guard's user, only if it is already resolved, so
     * callers never trigger an extra call to the auth service.
     *
     * @internal
     */
    public static function resolvedUser(): ?GembootUser
    {
        $guard = self::fromDefault();

        return $guard !== null && $guard->user instanceof GembootUser ? $guard->user : null;
    }

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->bearerToken();
        if (!$token || $token === $this->rejectedToken) {
            return null;
        }

        if (FailedAuthLimiter::tooManyAttempts()) {
            throw new TooManyRequestsException('Too many failed authentication attempts');
        }

        if (!TokenFormat::isPlausible('Bearer ' . $token)) {
            return $this->reject($token, TokenRejected::MALFORMED);
        }

        // Throws ServiceUnavailableException (503) when the answer is unknown.
        $user = $this->verifier->verify($token);
        if ($user === null) {
            return $this->reject($token, TokenRejected::REJECTED);
        }

        $this->user = $user;
        $this->userFromToken = true;

        return $user;
    }

    /**
     * Not used: this guard works with tokens.
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    /**
     * Set the user by hand, e.g. actingAs() in tests. Kept across requests.
     */
    public function setUser(Authenticatable $user)
    {
        $this->user = $user;
        $this->userFromToken = false;

        return $this;
    }

    /**
     * Set the user already verified for this request's token (token-validated
     * does this), so it isn't verified a second time. Cleared on the next request.
     *
     * @internal
     */
    public function setUserFromToken(GembootUser $user): static
    {
        $this->user = $user;
        $this->userFromToken = true;

        return $this;
    }

    /**
     * Use a new request. A user resolved from the previous request's token is
     * resolved again from the new one; a user set with setUser() stays.
     */
    public function setRequest(Request $request): static
    {
        $this->request = $request;
        $this->rejectedToken = null;

        if ($this->userFromToken) {
            $this->user = null;
            $this->userFromToken = false;
        }

        return $this;
    }

    /**
     * Remember a rejected token for this request and count it toward the
     * per-IP limit of failed attempts.
     */
    private function reject(string $token, string $reason): null
    {
        $this->rejectedToken = $token;
        FailedAuthLimiter::hit();
        TokenRejected::dispatch($reason, 'gemboot-guard');

        return null;
    }
}

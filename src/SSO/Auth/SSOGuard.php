<?php

namespace Gemboot\SSO\Auth;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Gemboot\Events\AuthServiceUnavailable;
use Gemboot\Events\TokenRejected;
use Gemboot\Exceptions\ServiceUnavailableException;
use Gemboot\Exceptions\TooManyRequestsException;
use Gemboot\Support\FailedAuthLimiter;
use Gemboot\Support\RequestId;
use Gemboot\Support\TokenFormat;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class SSOGuard implements Guard
{
    protected $request;
    protected $provider;
    protected $user;

    /** True when $user was resolved from the request's token (not set with setUser()). */
    protected $userFromToken = false;

    /** Token already rejected for this request; counted once toward the limit. */
    protected $rejectedToken = null;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    /**
     * Whether a user is authenticated.
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }

    /**
     * Whether the request is a guest (no user logged in).
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * The ID of the authenticated user.
     */
    public function id()
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * Validate credentials. Not used: this guard works with tokens.
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    /**
     * Set the user by hand (rarely needed with SSO; actingAs() in tests uses it).
     */
    public function setUser(Authenticatable $user)
    {
        $this->user = $user;
        $this->userFromToken = false;
        return $this;
    }

    /**
     * The authenticated user.
     */
    public function user(): ?Authenticatable
    {
        if ($this->user) return $this->user;

        // The token from the Authorization header.
        $token = $this->request->bearerToken();
        if (!$token) return null;

        // user() can be called several times per request; count a rejection once.
        if ($token === $this->rejectedToken) {
            return null;
        }

        if (FailedAuthLimiter::tooManyAttempts()) {
            throw new TooManyRequestsException('Too many failed authentication attempts');
        }

        if (!TokenFormat::isPlausible('Bearer ' . $token)) {
            return $this->reject($token, TokenRejected::MALFORMED);
        }

        $cacheKey = 'sso_token_' . sha1($token);

        $cached = Cache::get($cacheKey);
        if ($cached) {
            $this->user = new SSOUser($cached);
            $this->userFromToken = true;
            return $this->user;
        }

        $getUserUrl = config('gemboot.sso.get_user_url') ?? config('gemboot.sso.user_service_url') . "/user/me";

        // The fallback is used only when it is configured. Without this check
        // the URL became "/user/me" and an invalid token ended in a 500.
        $fallbackGetUserUrl = config('gemboot.sso.fallback.get_user_url');
        if (empty($fallbackGetUserUrl) && !empty(config('gemboot.sso.fallback.user_service_url'))) {
            $fallbackGetUserUrl = config('gemboot.sso.fallback.user_service_url') . "/user/me";
        }

        // Ask the user service for the user. Null means it couldn't be reached.
        $userResponse = $this->requestUserOrNull($getUserUrl, $token);

        if (!$userResponse || !$userResponse->ok()) {
            // A definite "no" (4xx) from the primary; an outage is no answer at all.
            $primaryRejected = $userResponse !== null && !$userResponse->serverError();

            if (!empty($fallbackGetUserUrl)) {
                $userResponse = $this->requestUserOrNull($fallbackGetUserUrl, $token);
            }

            if (!$userResponse || !$userResponse->ok()) {
                $unavailable = !$userResponse || $userResponse->serverError();

                // Nobody could tell whether the token is valid: answer 503, like the
                // auth middleware, so clients don't log users out during an outage.
                if ($unavailable && !$primaryRejected) {
                    throw new ServiceUnavailableException('Auth service unavailable');
                }

                return $this->reject($token);
            }
        }

        $userResponseJSON = $userResponse->json();
        if (is_array($userResponseJSON) && array_key_exists('data', $userResponseJSON)) {
            $userResponseJSON = $userResponseJSON['data'];
        }
        $userData = isset($userResponseJSON['user']) ? $userResponseJSON['user'] : $userResponseJSON;

        // A 200 reply without a user object (an HTML page, {"data": null}, ...)
        // must not authenticate the request or be cached.
        if (!is_array($userData) || empty($userData)) {
            return $this->reject($token);
        }
        $userData['roles'] = isset($userResponseJSON['roles']) ? $userResponseJSON['roles'] : null;
        $userData['permissions'] = isset($userResponseJSON['permissions']) ? $userResponseJSON['permissions'] : null;

        $cacheTTL = (int) config('gemboot.sso.cache_ttl', 300);
        Cache::put($cacheKey, $userData, now()->addSeconds($cacheTTL));

        $this->user = new SSOUser($userData);
        $this->userFromToken = true;
        return $this->user;
    }

    /**
     * Use a new request. A user resolved from the previous request's token is
     * resolved again from the new one; a user set with setUser() (e.g. actingAs()
     * in tests) stays.
     */
    public function setRequest(Request $request)
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
     * Remove the cached user of the current request's token.
     *
     * The user is cached for gemboot.sso.cache_ttl seconds, so a token revoked at
     * the auth service keeps working until then. Call this on logout, e.g.
     * Auth::guard('api')->forgetCachedUser().
     */
    public function forgetCachedUser(): void
    {
        $token = $this->request->bearerToken();
        if ($token) {
            Cache::forget('sso_token_' . sha1($token));
        }

        $this->user = null;
    }

    /**
     * Remember a rejected token for this request and count it toward the
     * per-IP limit of failed attempts.
     */
    private function reject(string $token, string $reason = TokenRejected::REJECTED): ?Authenticatable
    {
        $this->rejectedToken = $token;
        FailedAuthLimiter::hit();
        TokenRejected::dispatch($reason, 'sso-guard');

        return null;
    }

    /**
     * requestUser(), with null instead of an exception when there's no connection.
     */
    private function requestUserOrNull(string $url, string $token)
    {
        try {
            return $this->requestUser($url, $token);
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * Fetch the current user from the user service with the given token.
     */
    private function requestUser(string $url, string $token)
    {
        try {
            $response = Http::withToken($token)
                ->withHeaders(RequestId::headers())
                ->get($url, [
                    'showRoles' => 'true',
                    'showPermissions' => 'true',
                ]);
        } catch (ConnectionException $e) {
            event(new AuthServiceUnavailable('user/me', 0, false, RequestId::current()));
            throw $e;
        }

        if ($response->serverError()) {
            event(new AuthServiceUnavailable('user/me', $response->status(), false, RequestId::current()));
        }

        return $response;
    }

    /**
     * Whether a user has been resolved already.
     */
    public function hasUser(): bool
    {
        return !is_null($this->user);
    }
}

<?php

namespace Gemboot\Middleware;

use Closure;
use Illuminate\Http\Response;
use Gemboot\Traits\JSONResponses;
use Gemboot\Libraries\AuthLibrary;
use Gemboot\Support\FailedAuthLimiter;
use Gemboot\Support\SecurityHeaders;

class TokenValidated
{
    use JSONResponses;

    /**
     * Request attribute set when the current user was merged into the input as
     * "user_login", so Gemboot can keep it out of the data store() and update() save.
     *
     * @internal
     */
    public const USER_LOGIN_MERGED = 'gemboot.user_login_merged';

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $validationType = null)
    {
        $auth = new AuthLibrary();
        $hasToken = trim((string) $request->header('Authorization')) !== '';

        // Too many failed attempts from this client: don't ask the auth service.
        if ($hasToken && FailedAuthLimiter::tooManyAttempts()) {
            return FailedAuthLimiter::response($validationType == 'client');
        }

        if ($validationType == 'client') {
            $response = $auth->validateTokenClient($request);
            if (!$response) {
                if ($hasToken && !$auth->isAuthServiceUnavailable()) {
                    FailedAuthLimiter::hit();
                }
                $status = $auth->isAuthServiceUnavailable()
                    ? Response::HTTP_SERVICE_UNAVAILABLE
                    : Response::HTTP_UNAUTHORIZED;
                $statusText = Response::$statusTexts[$status];
                return response()->json(['status' => $statusText], $status)
                    ->withHeaders(SecurityHeaders::get());
            }

            return $next($request);
        } else {
            $response = $auth->me(false, $request);
            if (!$response) {
                if ($hasToken && !$auth->isAuthServiceUnavailable()) {
                    FailedAuthLimiter::hit();
                }
            if ($auth->isAuthServiceUnavailable()) {
                // The auth service did not answer: not the user's fault, so no 401/403.
                return $this->responseHttpError(Response::HTTP_SERVICE_UNAVAILABLE, ['error' => 'Auth service unavailable'], null, 'Auth service unavailable');
            }
                return $this->responseUnauthorized();
            }

            $request->merge(['user_login' => (array) $response]);
            $request->attributes->set(self::USER_LOGIN_MERGED, true);
            return $next($request);
        }
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (!$request->expectsJson()) {
            return route('login');
        }
    }
}

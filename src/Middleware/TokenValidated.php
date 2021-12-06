<?php

namespace Gemboot\Middleware;

use Closure;
use Gemboot\GembootResponse;
use Gemboot\Libraries\AuthLibrary;

class TokenValidated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $auth = new AuthLibrary();
        $response = $auth->me(false, $request);
        if (! $response) {
            return GembootResponse::responseUnauthorized();
        }

        $request->merge(['user_login' => $response]);
        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
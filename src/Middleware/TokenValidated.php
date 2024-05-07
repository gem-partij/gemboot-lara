<?php

namespace Gemboot\Middleware;

use Closure;
use Illuminate\Http\Response;
use Gemboot\Traits\JSONResponses;
use Gemboot\Libraries\AuthLibrary;

class TokenValidated
{
    use JSONResponses;

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

        if ($validationType == 'client') {
            $response = $auth->validateTokenClient($request);
            if (!$response) {
                $status = Response::HTTP_UNAUTHORIZED;
                $statusText = Response::$statusTexts[$status];
                return response()->json(['status' => $statusText], $status);
            }

            return $next($request);
        } else {
            $response = $auth->me(false, $request);
            if (!$response) {
                return $this->responseUnauthorized();
            }

            $request->merge(['user_login' => (array)$response]);
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

<?php

namespace Gemboot\Middleware;

use Closure;
use Illuminate\Http\Response;
use Gemboot\Traits\JSONResponses;
use Gemboot\Libraries\AuthLibrary;

class HasRole
{
    use JSONResponses;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $role_name)
    {
        $auth = new AuthLibrary();
        $response = $auth->hasRole($role_name, false, $request);
        if (!$response && $auth->isAuthServiceUnavailable()) {
            // The auth service did not answer: not the user's fault, so no 403.
            return $this->responseHttpError(Response::HTTP_SERVICE_UNAVAILABLE, ['error' => 'Auth service unavailable'], null, 'Auth service unavailable');
        }

        if (!$response || ($response && !$response['has_role'])) {
            return $this->responseForbidden();
        }

        return $next($request);
    }
}

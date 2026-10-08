<?php

namespace Gemboot\Middleware;

use Closure;
use Illuminate\Http\Response;
use Gemboot\Traits\JSONResponses;
use Gemboot\Auth\GembootGuard;
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
        // Answer from the gemboot guard's user when it already knows its roles.
        $user = GembootGuard::resolvedUser();
        if ($user !== null && $user->roles() !== null) {
            return $user->hasRole(explode('|', $role_name)) ? $next($request) : $this->responseForbidden();
        }

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

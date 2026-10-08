<?php

namespace Gemboot\Middleware;

use Closure;
use Illuminate\Http\Response;
use Gemboot\Traits\JSONResponses;
use Gemboot\Auth\GembootGuard;
use Gemboot\Libraries\AuthLibrary;

class HasPermissionTo
{
    use JSONResponses;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $permission_name)
    {
        // Answer from the gemboot guard's user when it already knows its permissions.
        $user = GembootGuard::resolvedUser();
        if ($user !== null && $user->permissions() !== null) {
            return $user->hasPermissionTo($permission_name) ? $next($request) : $this->responseForbidden();
        }

        $auth = new AuthLibrary();
        $response = $auth->hasPermissionTo($permission_name, false, $request);
        if (!$response && $auth->isAuthServiceUnavailable()) {
            // The auth service did not answer: not the user's fault, so no 403.
            return $this->responseHttpError(Response::HTTP_SERVICE_UNAVAILABLE, ['error' => 'Auth service unavailable'], null, 'Auth service unavailable');
        }

        if (!$response || ($response && !$response['has_permission_to'])) {
            return $this->responseForbidden();
        }

        return $next($request);
    }
}

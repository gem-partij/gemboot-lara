<?php

namespace Gemboot\Middleware;

use Closure;
use Gemboot\Traits\JSONResponses;
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
        $auth = new AuthLibrary();
        $response = $auth->hasPermissionTo($permission_name, false, $request);
        if (!$response || ($response && !$response['has_permission_to'])) {
            return $this->responseForbidden();
        }

        return $next($request);
    }
}

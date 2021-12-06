<?php

namespace Gemboot\Middleware;

use Closure;
use Gemboot\GembootResponse;
use Gemboot\Libraries\AuthLibrary;

class HasPermissionTo
{
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
        if (!$response || ($response && !$response->has_permission_to)) {
            return GembootResponse::responseForbidden($response);
        }

        return $next($request);
    }

}

<?php

namespace Gemboot\Middleware;

use Closure;
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
        if (!$response || ($response && !$response->has_role)) {
            return $this->responseUnauthorized();
        }

        return $next($request);
    }

}

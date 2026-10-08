<?php

namespace Gemboot\Middleware;

use Closure;
use Gemboot\Support\RequestId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Gives every request an ID, so one user action can be followed through the
 * logs of every service it passes through.
 *
 * The ID comes from the incoming X-Request-Id header (set by a gateway, a proxy,
 * or the Gemboot service that called this one), or is generated. It is stored in
 * Laravel's Context, so it appears in every log entry and queued job, sent with
 * Gemboot's outgoing calls, and returned in the response header.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next)
    {
        $header = RequestId::header();
        $incoming = $request->headers->get($header);

        $id = config('gemboot.request_id.accept_incoming', true) && RequestId::isValid($incoming)
            ? $incoming
            : (string) Str::uuid();

        Context::add(RequestId::CONTEXT_KEY, $id);

        $response = $next($request);
        $response->headers->set($header, $id);

        return $response;
    }
}

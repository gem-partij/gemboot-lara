<?php

namespace Gemboot\Traits;

trait GembootRequest
{
    public function getRequestToken($request = null, $without_bearer = false)
    {
        if (empty($request)) {
            $request = request();
        }
        $headers = $request->header();
        $token = null;

        if (isset($headers['authorization']) && !empty($headers['authorization'])) {
            $token = $headers['authorization'][0];
        }

        if ($without_bearer) {
            $token = str_replace(["Bearer ", "bearer "], "", $token);
        }

        return $token;
    }

    public function buildJsonResponse($httpClientResponse)
    {
        $status = (int) ($httpClientResponse->info->http_code ?? 0);

        // Without a connection the code is 0, which is not a valid HTTP status and
        // made the route fail with a 500. Answer 503, like the auth middleware.
        if ($status < 100) {
            return response()->json([
                'status' => 503,
                'message' => 'Service Unavailable',
                'data' => ['error' => 'Auth service unavailable'],
            ], 503);
        }

        return response()->json($httpClientResponse->data, $status);
    }
}

<?php
namespace Gemboot\Traits;

trait GembootRequest
{
    public function getRequestToken($request = null) {
        if(empty($request)) {
            $request = request();
        }
        $headers = $request->header();
        $token = null;

        if(isset($headers['authorization']) && !empty($headers['authorization'])) {
            $token = $headers['authorization'][0];
        }

        // return str_replace(["Bearer ", "bearer "], "", $token);
        return $token;
    }

    public function buildJsonResponse($httpClientResponse) {
        return response()->json($httpClientResponse->data, $httpClientResponse->info->http_code);
    }
}

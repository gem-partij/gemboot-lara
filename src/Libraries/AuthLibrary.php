<?php

namespace Gemboot\Libraries;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Gemboot\Libraries\HttpClient;
use Gemboot\Traits\GembootRequest;

class AuthLibrary
{

    use GembootRequest;

    protected $baseUrlAuth;
    protected $httpClient;

    /** HTTP status of the last auth service call (0 = no connection). */
    protected $lastHttpCode = null;

    public function __construct($baseUrlAuth = null)
    {
        $this->setBaseUrlAuth($baseUrlAuth);
        $this->httpClient = new HttpClient($this->baseUrlAuth);
        $this->httpClient->withHeaders([
            'Accept' => 'application/json',
        ]);
    }

    public function getHttpClient()
    {
        return $this->httpClient;
    }

    public function setBaseUrlAuth($baseUrlAuth = null)
    {
        if (empty($baseUrlAuth)) {
            $baseUrlAuth = app('config')->get('gemboot.auth.base_api');
            // Placeholder from an older published config counts as not set.
            if ($baseUrlAuth === 'YOUR GEMBOOT AUTH BASE API HERE') {
                $baseUrlAuth = null;
            }
            // backward compatibility with gemboot version 3.x and below
            if (empty($baseUrlAuth)) {
                $baseUrlAuth = app('config')->get('gemboot_auth.base_api');
            }
        }

        // Ensure trailing slash exists for correct Guzzle base_uri concatenation
        if (!empty($baseUrlAuth)) {
            $baseUrlAuth = rtrim($baseUrlAuth, '/') . '/';
        }

        $this->baseUrlAuth = $baseUrlAuth;
        return $this;
    }

    public function setToken($token)
    {
        $this->httpClient->setToken($token);
        return $this;
    }

    /**
     * True when the last call could not get an answer from the auth service:
     * no connection, or a 5xx reply. Middleware uses it to answer 503 instead
     * of 401/403, so clients do not log users out during an outage.
     */
    public function isAuthServiceUnavailable(): bool
    {
        return $this->lastHttpCode !== null
            && ($this->lastHttpCode === 0 || $this->lastHttpCode >= 500);
    }

    protected function trackResponse($response)
    {
        $this->lastHttpCode = (int) ($response->info->http_code ?? 0);
        return $response;
    }

    /**
     * GET an auth service endpoint, optionally cached per token.
     *
     * Caching is off unless gemboot.auth.cache_ttl is above 0. Only definite answers
     * (200 and 4xx) are cached, never outages. A revoked token keeps working until
     * its entries expire; logout() invalidates them for this token.
     */
    protected function authGet(string $endpoint, array $query, $token)
    {
        $ttl = (int) config('gemboot.auth.cache_ttl', 0);
        if ($ttl <= 0 || empty($token)) {
            return $this->trackResponse($this->httpClient->setToken($token)->get($endpoint, $query));
        }

        $key = $this->authCacheKey($token, $endpoint, $query);
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['code'])) {
            return $this->trackResponse((object) [
                'info' => (object) ['http_code' => $cached['code']],
                'data' => $cached['data'],
            ]);
        }

        $response = $this->trackResponse($this->httpClient->setToken($token)->get($endpoint, $query));
        if ($this->lastHttpCode === 200 || ($this->lastHttpCode >= 400 && $this->lastHttpCode < 500)) {
            Cache::put($key, ['code' => $this->lastHttpCode, 'data' => $response->data ?? null], $ttl);
        }

        return $response;
    }

    protected function authCacheKey($token, string $endpoint, array $query): string
    {
        $tokenHash = sha1($this->baseUrlAuth . '|' . $token);
        $version = (int) Cache::get('gemboot_auth_v_' . $tokenHash, 0);

        return 'gemboot_auth_' . $tokenHash . '_' . $version . '_' . sha1($endpoint . '?' . http_build_query($query));
    }

    protected function forgetCachedAuth($token)
    {
        if ((int) config('gemboot.auth.cache_ttl', 0) <= 0 || empty($token)) {
            return;
        }

        // Bumping the version makes every cached entry of this token unreachable.
        $versionKey = 'gemboot_auth_v_' . sha1($this->baseUrlAuth . '|' . $token);
        Cache::forever($versionKey, (int) Cache::get($versionKey, 0) + 1);
    }


    /**
     * Helper Safe Response Check
     * Mengambil HTTP Code dengan aman dari object response
     */
    protected function isSuccess($response)
    {
        if (!$response)
            return false;
        if (isset($response->info->http_code)) {
            return $response->info->http_code == 200;
        }
        return false;
    }

    /**
     * Helper Get Data Safe
     * Mengambil data payload dengan aman
     */
    protected function getData($response)
    {
        if (!$response)
            return null;

        // Response data usually contains 'data' wrapper from API standard
        // But HttpClient puts response body into ->data
        $body = $response->data ?? [];

        if (is_array($body) && isset($body['data'])) {
            return $body['data'];
        }

        return $body;
    }


    public function login($npp, $password, $response_json = false, ?Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $response = $this->trackResponse($this->httpClient->post("login", [
            'npp' => $npp,
            'password' => $password,
            'hwid' => ($request && $request->has('hwid')) ? $request->hwid : null,
        ]));
        // dd($response);

        if ($response_json) {
            return $this->buildJsonResponse($response);
        }

        if ($this->isSuccess($response)) {
            return $this->getData($response);
        }

        return false;
    }

    public function me($response_json = false, ?Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $token = $this->getRequestToken($request);

        if ($response_json) {
            $response = $this->trackResponse($this->httpClient->setToken($token)->get("me"));
            return $this->buildJsonResponse($response);
        }

        $response = $this->authGet("me", [], $token);

        if ($this->isSuccess($response)) {
            return $this->getData($response);
        }

        return false;
    }

    public function validateToken($response_json = false, ?Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $token = $this->getRequestToken($request);

        if ($response_json) {
            $response = $this->trackResponse($this->httpClient->setToken($token)->get("validate-token"));
            return $this->buildJsonResponse($response);
        }

        $response = $this->authGet("validate-token", [], $token);

        if ($this->isSuccess($response)) {
            return $this->getData($response);
        }

        return false;
    }

    public function validateTokenClient(?Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $response = $this->authGet("validate-token", [], $this->getRequestToken($request));

        if ($this->isSuccess($response)) {
            return true;
        }

        return false;
    }

    public function hasRole($role_name, $response_json = false, ?Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $token = $this->getRequestToken($request);

        if ($response_json) {
            $response = $this->trackResponse($this->httpClient->setToken($token)->get("has-role", [
                'role_name' => $role_name,
            ]));
            return $this->buildJsonResponse($response);
        }

        $response = $this->authGet("has-role", ['role_name' => $role_name], $token);

        if ($this->isSuccess($response)) {
            return $this->getData($response);
        }

        return false;
    }

    public function hasPermissionTo($permission_name, $response_json = false, ?Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $token = $this->getRequestToken($request);

        if ($response_json) {
            $response = $this->trackResponse($this->httpClient->setToken($token)->get("has-permission-to", [
                'permission_name' => $permission_name,
            ]));
            return $this->buildJsonResponse($response);
        }

        $response = $this->authGet("has-permission-to", ['permission_name' => $permission_name], $token);

        if ($this->isSuccess($response)) {
            return $this->getData($response);
        }

        return false;
    }

    public function logout($response_json = false, ?Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $token = $this->getRequestToken($request);
        $response = $this->trackResponse($this->httpClient->setToken($token)->post("logout"));
        $this->forgetCachedAuth($token);

        if ($response_json) {
            return $this->buildJsonResponse($response);
        }

        if ($this->isSuccess($response)) {
            return $this->getData($response);
        }

        return false;
    }

    public function generateRandomPassword($length = 6, $difficulty = 'medium')
    {
        if ($difficulty == 'easy') {
            $alphabet = '1234567890';
        } elseif ($difficulty == 'medium') {
            $alphabet = 'abcdefghijklmnopqrstuvwxyz1234567890';
        } elseif ($difficulty == 'hard') {
            $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        }

        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < $length; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }
}

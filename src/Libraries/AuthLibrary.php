<?php

namespace Gemboot\Libraries;

use Illuminate\Http\Request;
use Gemboot\Libraries\HttpClient;
use Gemboot\Traits\GembootRequest;

class AuthLibrary
{

    use GembootRequest;

    protected $baseUrlAuth;
    protected $httpClient;

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


    public function login($npp, $password, $response_json = false, Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $response = $this->httpClient->post("login", [
            'npp' => $npp,
            'password' => $password,
            'hwid' => ($request && $request->has('hwid')) ? $request->hwid : null,
        ]);
        // dd($response);

        if ($response_json) {
            return $this->buildJsonResponse($response);
        }

        if ($this->isSuccess($response)) {
            return $this->getData($response);
        }

        return false;
    }

    public function me($response_json = false, Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $token = $this->getRequestToken($request);
        $response = $this->httpClient->setToken($token)->get("me");

        if ($response_json) {
            return $this->buildJsonResponse($response);
        }

        if ($this->isSuccess($response)) {
            return $this->getData($response);
        }

        return false;
    }

    public function validateToken($response_json = false, Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $token = $this->getRequestToken($request);
        $response = $this->httpClient->setToken($token)->get("validate-token");

        if ($response_json) {
            return $this->buildJsonResponse($response);
        }

        if ($this->isSuccess($response)) {
            return $this->getData($response);
        }

        return false;
    }

    public function validateTokenClient(Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $response = $this->httpClient->withTokenBearer($request)->get("validate-token");

        if ($this->isSuccess($response)) {
            return true;
        }

        return false;
    }

    public function hasRole($role_name, $response_json = false, Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $token = $this->getRequestToken($request);
        $response = $this->httpClient->setToken($token)->get("has-role", [
            'role_name' => $role_name,
        ]);

        if ($response_json) {
            return $this->buildJsonResponse($response);
        }

        if ($this->isSuccess($response)) {
            return $this->getData($response);
        }

        return false;
    }

    public function hasPermissionTo($permission_name, $response_json = false, Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $token = $this->getRequestToken($request);
        $response = $this->httpClient->setToken($token)->get("has-permission-to", [
            'permission_name' => $permission_name,
        ]);

        if ($response_json) {
            return $this->buildJsonResponse($response);
        }

        if ($this->isSuccess($response)) {
            return $this->getData($response);
        }

        return false;
    }

    public function logout($response_json = false, Request $request = null)
    {
        if (empty($request)) {
            $request = request();
        }

        $token = $this->getRequestToken($request);
        $response = $this->httpClient->setToken($token)->post("logout");

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

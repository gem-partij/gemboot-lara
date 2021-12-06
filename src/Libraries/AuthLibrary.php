<?php
namespace Gemboot\Libraries;

use Gemboot\Libraries\HttpClient;
use Gemboot\Traits\GembootRequest;

class AuthLibrary {

    use GembootRequest;

    protected $baseUrlAuth;
    protected $httpClient;

    public function __construct($baseUrlAuth = null) {
        $this->setBaseUrlAuth($baseUrlAuth);
        $this->httpClient = new HttpClient($this->baseUrlAuth);
    }

    public function getHttpClient() {
        return $this->httpClient;
    }

    public function setBaseUrlAuth($baseUrlAuth = null) {
        if(empty($baseUrlAuth)) {
            $baseUrlAuth = app('config')->get('gemboot_auth.base_api');
        }
        $this->baseUrlAuth = $baseUrlAuth;
        return $this;
    }

    public function setToken($token) {
        $this->httpClient->setToken($token);
        return $this;
    }


    public function login($npp, $password, $response_json = false, $request = null) {
        $response = $this->httpClient->post("/login", [
            'npp' => $npp,
            'password' => $password,
        ]);

        if($response_json) {
            return $this->buildJsonResponse($response);
        }

        if($response->info->http_code == 200) {
            $response_data = $response->data;
            return $response_data->data;
        }

        return false;
    }

    public function me($response_json = false, $request = null) {
        $token = $this->getRequestToken($request);
        $response = $this->httpClient->setToken($token)->get("/me");

        if($response_json) {
            return $this->buildJsonResponse($response);
        }

        if($response->info->http_code == 200) {
            $response_data = $response->data;
            return $response_data->data;
        }

        return false;
    }

    public function validateToken($response_json = false, $request = null) {
        $token = $this->getRequestToken($request);
        $response = $this->httpClient->setToken($token)->get("/validate-token");

        if($response_json) {
            return $this->buildJsonResponse($response);
        }

        if($response->info->http_code == 200) {
            $response_data = $response->data;
            return $response_data->data;
        }

        return false;
    }

    public function hasRole($role_name, $response_json = false, $request = null) {
        $token = $this->getRequestToken($request);
        $response = $this->httpClient->setToken($token)->get("/has-role", [
            'role_name' => $role_name,
        ]);

        if($response_json) {
            return $this->buildJsonResponse($response);
        }

        if($response->info->http_code == 200) {
            $response_data = $response->data;
            return $response_data->data;
        }

        return false;
    }

    public function hasPermissionTo($permission_name, $response_json = false, $request = null) {
        $token = $this->getRequestToken($request);
        $response = $this->httpClient->setToken($token)->get("/has-permission-to", [
            'permission_name' => $permission_name,
        ]);

        if($response_json) {
            return $this->buildJsonResponse($response);
        }

        if($response->info->http_code == 200) {
            $response_data = $response->data;
            return $response_data->data;
        }

        return false;
    }

    public function logout($response_json = false, $request = null) {
        $token = $this->getRequestToken($request);
        $response = $this->httpClient->setToken($token)->post("/logout");

        if($response_json) {
            return $this->buildJsonResponse($response);
        }

        if($response->info->http_code == 200) {
            $response_data = $response->data;
            return $response_data->data;
        }

        return false;
    }
}
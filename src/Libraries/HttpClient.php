<?php
namespace Gemboot\Libraries;

class HttpClient {

    protected $baseUrl;
    protected $token;

    public function __construct($baseUrl = null, $token = null) {
        $this->baseUrl = $baseUrl;
        $this->token = $token;
    }

    public function setBaseUrl($baseUrl) {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    public function setToken($token) {
        $this->token = $token;
        return $this;
    }

    public function get($url = "", $data = [], $absolute_url = false) {
        try {
            $query = http_build_query($data);

            $ch = curl_init();

            $request_url = $this->baseUrl.$url;
            if($absolute_url) {
                $request_url = $url;
            }

            // set url
            curl_setopt($ch, CURLOPT_URL, $request_url.'?'.$query);
            // set header
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: '.$this->token,
            ));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            //return the transfer as a string
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            // $output contains the output string
            $output = curl_exec($ch);

            // close curl resource to free up system resources
            // echo curl_errno($ch);
            // echo curl_error($ch);

            // Check the return value of curl_exec(), too
            if ($output === false || curl_errno($ch)) {
                throw new \Exception(curl_error($ch), curl_errno($ch));
            }

            $info = curl_getinfo($ch);

            // close curl resource to free up system resources
            curl_close($ch);

            $response = json_decode($output, true);
            return json_decode(json_encode([
                'info' => $info,
                'data' => $response,
            ]));
        } catch(\Exception $e) {
            throw $e;
        }
    }

    public function post($url = "", $data = [], $absolute_url = false) {
        try {
            $request_url = $this->baseUrl.$url;
            if($absolute_url) {
                $request_url = $url;
            }
            // $query = http_build_query($data);

            $ch = curl_init();

            // set url
            curl_setopt($ch, CURLOPT_URL, $request_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            // set header
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: '.$this->token,
            ));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            //return the transfer as a string
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            // $output contains the output string
            $output = curl_exec($ch);
            // close curl resource to free up system resources
            // echo curl_errno($ch);
            // echo curl_error($ch);

            // Check the return value of curl_exec(), too
            if ($output === false || curl_errno($ch)) {
                throw new \Exception(curl_error($ch), curl_errno($ch));
            }

            $info = curl_getinfo($ch);

            curl_close($ch);

            $response = json_decode($output, true);
            return json_decode(json_encode([
                'info' => $info,
                'data' => $response,
            ]));
        } catch(\Exception $e) {
            throw $e;
            // trigger_error(sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()), E_USER_ERROR);
            // throw new \Exception(sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()));
        }
    }


}

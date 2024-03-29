<?php
namespace Gemboot\Libraries;

use Gemboot\Traits\GembootRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;

class HttpClient {

    use GembootRequest;

    protected $baseUrl;
    protected $token;
    protected $headers = [];
    protected $throwOnHttpError;

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

    public function throwOnHttpError($throwOnHttpError = true) {
        $this->throwOnHttpError = $throwOnHttpError;
        return $this;
    }

    public function withTokenBearer($request = null) {
        return $this->setToken($this->getRequestToken($request));
    }

    public function withHeaders(array $headers = []) {
        $this->headers = $headers;
        return $this;
    }

    public function get($url = "", $data = [], $absolute_url = false) {
        try {
            $query = http_build_query($data);
            $first4 = substr($url, 0, 4);

            $ch = curl_init();

            $request_url = $this->baseUrl.$url;
            if($absolute_url || $first4 == 'http') {
                $request_url = $url;
            }

            // set url
            curl_setopt($ch, CURLOPT_URL, $request_url.'?'.$query);
            // set header
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
                'Authorization: '.$this->token,
            ], $this->headers));
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

            if($this->throwOnHttpError && $info['http_code'] >= 400) {
                throw new HttpException($info['http_code'], Response::$statusTexts[$info['http_code']]);
            }

            $response = json_decode($output, true);
            return json_decode(json_encode([
                'info' => $info,
                'data' => $response,
            ]));
        } catch(HttpException $he) {
            throw $he;
        } catch(\Exception $e) {
            throw $e;
        }
    }

    public function post($url = "", $data = [], $absolute_url = false) {
        try {
            $first4 = substr($url, 0, 4);

            $request_url = $this->baseUrl.$url;
            if($absolute_url || $first4 == 'http') {
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
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
                'Authorization: '.$this->token,
            ], $this->headers));
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

            if($this->throwOnHttpError && $info['http_code'] >= 400) {
                throw new HttpException($info['http_code'], Response::$statusTexts[$info['http_code']]);
            }

            $response = json_decode($output, true);
            return json_decode(json_encode([
                'info' => $info,
                'data' => $response,
            ]));
        } catch(HttpException $he) {
            throw $he;
        } catch(\Exception $e) {
            throw $e;
            // trigger_error(sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()), E_USER_ERROR);
            // throw new \Exception(sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()));
        }
    }


}

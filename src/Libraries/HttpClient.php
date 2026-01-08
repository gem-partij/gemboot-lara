<?php

namespace Gemboot\Libraries;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Gemboot\Traits\GembootRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Log;

class HttpClient
{
    use GembootRequest;

    /** @var Client */
    protected $client;

    protected $baseUrl;
    protected $token;
    protected $headers = [];
    protected $throwOnHttpError = false;

    public function __construct($baseUrl = null, $token = null)
    {
        $this->baseUrl = $baseUrl;
        $this->token = $token;
        $this->initClient();
    }

    protected function initClient()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'verify' => false, // Mengatasi masalah SSL lokal/docker environment
            'http_errors' => false, // Kita handle error manual agar konsisten
        ]);
    }

    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->initClient(); // Re-init client saat base URL berubah
        return $this;
    }

    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    public function throwOnHttpError($throwOnHttpError = true)
    {
        $this->throwOnHttpError = $throwOnHttpError;
        return $this;
    }

    public function withTokenBearer($request = null)
    {
        return $this->setToken($this->getRequestToken($request));
    }

    public function withHeaders(array $headers = [])
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Unified Request Method
     * Mengembalikan object standar:
     * ->info (object: http_code)
     * ->data (mixed: response body)
     */
    protected function request($method, $url, $options = [])
    {
        try {
            // Merge headers
            $headers = $this->headers;
            if ($this->token) {
                $headers['Authorization'] = $this->token;
            }

            $defaultOptions = [
                'headers' => $headers,
            ];

            $response = $this->client->request($method, $url, array_merge($defaultOptions, $options));

            $statusCode = $response->getStatusCode();
            $bodyContent = (string) $response->getBody();
            $data = json_decode($bodyContent, true); // Decode as array

            // dd([
            //     'base_uri' => $this->baseUrl,
            //     'method' => $method,
            //     'url' => $url,
            //     'options' => $options,
            //     'headers' => $headers,
            //     'defaultOptions' => $defaultOptions,
            //     'response' => $response,
            //     'statusCode' => $statusCode,
            //     'bodyContent' => $bodyContent,
            //     'data' => $data,
            // ]);

            // Error Handling jika throwOnHttpError aktif
            if ($this->throwOnHttpError && $statusCode >= 400) {
                throw new HttpException($statusCode, $response->getReasonPhrase());
            }

            // Return Standard Object Structure (Compatible with legacy Gemboot code)
            return (object) [
                'info' => (object) [
                    'http_code' => $statusCode,
                    'content_type' => $response->getHeaderLine('Content-Type'),
                ],
                'data' => $data, // Body response (usually array from JSON)
                'raw_body' => $bodyContent
            ];

        } catch (RequestException $e) {
            // Handle connection errors, DNS errors, etc.
            if ($this->throwOnHttpError) {
                throw $e;
            }

            Log::error("HttpClient Error: " . $e->getMessage());

            // Return structure error
            return (object) [
                'info' => (object) [
                    'http_code' => 0, // 0 indicates connection failure
                ],
                'data' => null,
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            if ($this->throwOnHttpError) {
                throw $e;
            }

            Log::error("HttpClient Exception: " . $e->getMessage());

            return (object) [
                'info' => (object) [
                    'http_code' => 500,
                ],
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    public function get($url = "", $data = [])
    {
        return $this->request('GET', $url, ['query' => $data]);
    }

    public function post($url = "", $data = [])
    {
        // Gunakan 'json' untuk body request JSON standard
        return $this->request('POST', $url, ['json' => $data]);
    }

    // Tambahkan method lain jika perlu (put, delete, patch)
}
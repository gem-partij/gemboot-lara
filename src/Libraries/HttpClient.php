<?php

namespace Gemboot\Libraries;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Gemboot\Traits\GembootRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;

class HttpClient
{
    use GembootRequest;

    protected $client;
    protected $baseUrl;
    protected $token;
    protected $headers = [];
    protected $throwOnHttpError = false;

    public function __construct($baseUrl = null, $token = null)
    {
        $this->baseUrl = $baseUrl;
        $this->token = $token;

        // Inisialisasi Guzzle Client
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 30,
            'verify' => false, // Optional: matikan SSL verify jika dev environment bermasalah (hati-hati di prod)
        ]);
    }

    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        // Re-init client jika base URL berubah
        $this->client = new Client(['base_uri' => $baseUrl, 'timeout' => 30, 'verify' => false]);
        return $this;
    }

    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    public function throwOnHttpError($throw = true)
    {
        $this->throwOnHttpError = $throw;
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
     * Helper untuk merge headers default + auth
     */
    protected function getMergedHeaders()
    {
        $headers = $this->headers;
        if ($this->token) {
            $headers['Authorization'] = $this->token;
        }
        return $headers;
    }

    /**
     * Unified Request Handler
     */
    protected function request($method, $url, $options = [])
    {
        try {
            // Guzzle throws exception by default on 4xx/5xx
            // Kita set 'http_errors' => false dulu agar bisa handle manual jika throwOnHttpError = false
            $defaultOptions = [
                'headers' => $this->getMergedHeaders(),
                'http_errors' => $this->throwOnHttpError,
            ];

            $finalOptions = array_merge($defaultOptions, $options);

            $response = $this->client->request($method, $url, $finalOptions);

            $body = json_decode((string) $response->getBody(), true);

            return (object) [
                'info' => [
                    'http_code' => $response->getStatusCode(),
                    'content_type' => $response->getHeaderLine('Content-Type'),
                ],
                'data' => $body,
                'status' => $response->getStatusCode(), // Shortcut
            ];

        } catch (RequestException $e) {
            // Guzzle Exception Handling
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $statusText = $response->getReasonPhrase();

                // Jika throwOnHttpError true, lempar HttpException agar kompatibel dengan Gemboot Exception Handler
                if ($this->throwOnHttpError) {
                    throw new HttpException($statusCode, $statusText, $e);
                }

                // Jika tidak throw, return object error
                return (object) [
                    'info' => ['http_code' => $statusCode],
                    'data' => json_decode((string) $response->getBody(), true),
                    'error' => $e->getMessage()
                ];
            }

            // Connection error / no response
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function get($url = "", $data = [])
    {
        return $this->request('GET', $url, ['query' => $data]);
    }

    public function post($url = "", $data = [])
    {
        // Guzzle membedakan 'json' (raw body) dan 'form_params' (form-data)
        // Asumsi default API modern pakai JSON
        return $this->request('POST', $url, ['json' => $data]);
    }

    // Tambahkan PUT, DELETE, PATCH jika perlu
}
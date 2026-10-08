<?php

namespace Gemboot\Libraries;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Gemboot\Support\RequestId;
use Gemboot\Testing\FakeAuthService;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
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

    /**
     * Guzzle clients shared per base URL and settings. Reusing one client lets
     * Guzzle keep connections to the auth service open between calls (several
     * middleware in one request, and every request of an Octane worker).
     *
     * @var array<string, Client>
     */
    private static array $sharedClients = [];

    public function __construct($baseUrl = null, $token = null)
    {
        $this->baseUrl = $baseUrl;
        $this->token = $token;
        $this->initClient();
    }

    protected function initClient()
    {
        // Defaults apply when a published config/gemboot.php predates these keys.
        $config = [
            'base_uri' => $this->baseUrl,
            'timeout' => (float) config('gemboot.http.timeout', 30),
            'connect_timeout' => (float) config('gemboot.http.connect_timeout', 10),
            // true, false, or a path to a CA bundle. Turn off only for local development.
            'verify' => config('gemboot.http.verify', true),
            'http_errors' => false, // Errors are handled below, so every caller gets the same result shape.
        ];

        // In tests, GembootAuth::fake() answers instead of the real auth service.
        if (app()->bound(FakeAuthService::class)) {
            $config['handler'] = HandlerStack::create(app(FakeAuthService::class)->handler());
            $this->client = new Client($config);

            return;
        }

        $key = sha1(serialize([$this->baseUrl, $config]));
        $this->client = self::$sharedClients[$key] ??= new Client($config);
    }

    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->initClient(); // The client is bound to its base URL.
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
     * Sends a request and returns the standard result object:
     * ->info (object: http_code, content_type)
     * ->data (mixed: the decoded response body)
     */
    protected function request($method, $url, $options = [])
    {
        // Lazy init: make sure a client exists before sending.
        if (!$this->client) {
            $this->initClient();
        }

        try {
            // The request ID first, so headers set with withHeaders() can replace it.
            $headers = array_merge(RequestId::headers(), $this->headers);
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

            // Throw on 4xx and 5xx only when throwOnHttpError() is on.
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

        } catch (TransferException $e) {
            // Handle connection errors, DNS errors, timeouts, etc. ConnectException is a
            // TransferException but not a RequestException, so it is caught here too.
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
        // Send the body as JSON.
        return $this->request('POST', $url, ['json' => $data]);
    }
}
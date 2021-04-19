<?php
namespace Gemboot\Gateway\Middleware;

use Closure;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client as GuzzleClient;
use Gemboot\Traits\JSONResponses;

class CheckToken
{
    use JSONResponses;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->header('Authorization')) {
            // validate token to auth service
            try {
                if ($this->validateToken($request)) {
                    // if token is valid allow request flow
                    return $next($request);
                } else {
                    // else, response Unauthorized
                    return $this->responseUnauthorized();
                }
            } catch (GuzzleException $ge) {
                \Log::error($ge->getMessage());
                \Log::error($ge->getTraceAsString());

                $response = $ge->getResponse();

                $statusCode = $response->getStatusCode();
                $reasonPhrase = $response->getReasonPhrase();

                $responseBodyAsString = "";
                if (env('APP_DEBUG')) {
                    $responseBodyAsString = $response->getBody()->getContents();
                }
                return $this->response($statusCode, [
                    'response_body' => $responseBodyAsString
                ], $reasonPhrase);
            } catch (\Exception $e) {
                return $this->responseException($e);
            }
        } else {
            return $this->responseBadRequest([], 'Not a valid API request.');
        }
    }

    protected function validateToken(Request $request)
    {
        $url_auth = app('config')->get('gemboot_gw.base_url_auth', 'https://tirta.pdamkotasmg.co.id:8443/gateway/auth/');
        $url = $url_auth.'api/auth/me';

        $headers = $request->headers->all();
        $headers_formatted = [];
        if (isset($headers['authorization'])) {
            $headers_formatted['authorization'] = $headers['authorization'][0];
        }

        $client = new GuzzleClient([
            'verify' => false
        ]);

        $response = $client->request('GET', $url, [
            'headers' => $headers_formatted
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody();
        $contents = $body->getContents();

        $decoded = json_decode($contents);

        if (isset($decoded->data) && $decoded->data->user) {
            session(['auth_user' => $decoded->data->user]);
            return true;
        }

        return false;
    }
}

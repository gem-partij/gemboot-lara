<?php

namespace Gemboot\Testing;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

/**
 * In-memory stand-in for the central auth service, for tests of apps that use
 * Gemboot. Start it with GembootAuth::fake().
 *
 * While active, every call AuthLibrary makes (through the auth middleware,
 * GembootPermission, or directly) is answered here instead of over the network.
 * Calls of the SSO guard to its user service are answered too.
 *
 * Any request with a bearer token counts as the fake user; requests without a
 * token are rejected, like a real auth service would.
 */
class FakeAuthService
{
    private bool $outage = false;

    private bool $rejectTokens = false;

    /** @var array<int, array{endpoint: string, query: array, token: string}> */
    private array $recorded = [];

    public function __construct(
        private array $user = ['id' => 1],
        private array $roles = [],
        private array $permissions = [],
    ) {
    }

    /**
     * Simulate an auth service that can't be reached (protected routes answer 503).
     */
    public function outage(bool $outage = true): static
    {
        $this->outage = $outage;

        return $this;
    }

    /**
     * Reject every token (protected routes answer 401), e.g. to test an expired token.
     */
    public function rejectTokens(bool $reject = true): static
    {
        $this->rejectTokens = $reject;

        return $this;
    }

    public function withUser(array $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function withRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function withPermissions(array $permissions): static
    {
        $this->permissions = $permissions;

        return $this;
    }

    /**
     * Guzzle handler HttpClient uses while the fake is active.
     *
     * @internal
     */
    public function handler(): callable
    {
        return function (RequestInterface $request, array $options) {
            parse_str($request->getUri()->getQuery(), $query);
            $token = $request->getHeaderLine('Authorization');
            $endpoint = $this->record($request->getUri()->getPath(), $query, $token);

            if ($this->outage) {
                return Create::rejectionFor(new ConnectException('Gemboot fake: auth service unavailable', $request));
            }

            [$status, $body] = $this->answer($endpoint, $query, $token);

            return Create::promiseFor(new Response($status, ['Content-Type' => 'application/json'], json_encode($body)));
        };
    }

    /**
     * Answer the SSO guard's user service calls. Requests to other URLs pass
     * through untouched.
     *
     * @internal
     */
    public function registerSsoStub(): void
    {
        Http::fake(function (HttpClientRequest $request) {
            if (!$this->isSsoUserUrl($request->url())) {
                return null;
            }

            $this->record((string) parse_url($request->url(), PHP_URL_PATH), $request->data(), $request->header('Authorization')[0] ?? '');

            if ($this->outage) {
                return Http::failedConnection();
            }
            if (!$this->isLoggedIn($request->header('Authorization')[0] ?? '')) {
                return Http::response(['message' => 'Unauthenticated'], 401);
            }

            return Http::response(['data' => [
                'user' => $this->user,
                'roles' => $this->roles,
                'permissions' => $this->permissions,
            ]]);
        });
    }

    /**
     * Assert that an auth service endpoint was called, optionally an exact number of times.
     * Endpoints: me, validate-token, has-role, has-permission-to, login, logout, user/me (SSO).
     */
    public function assertCalled(string $endpoint, ?int $times = null): static
    {
        $count = count($this->requestsTo($endpoint));

        if ($times === null) {
            Assert::assertGreaterThan(0, $count, "The fake auth service did not receive a [{$endpoint}] request.");
        } else {
            Assert::assertSame($times, $count, "The fake auth service received {$count} [{$endpoint}] request(s), expected {$times}.");
        }

        return $this;
    }

    public function assertNotCalled(string $endpoint): static
    {
        Assert::assertCount(0, $this->requestsTo($endpoint), "The fake auth service received an unexpected [{$endpoint}] request.");

        return $this;
    }

    /**
     * Every request the fake received, oldest first.
     *
     * @return array<int, array{endpoint: string, query: array, token: string}>
     */
    public function requests(): array
    {
        return $this->recorded;
    }

    private function requestsTo(string $endpoint): array
    {
        return array_values(array_filter($this->recorded, fn ($r) => $r['endpoint'] === trim($endpoint, '/')));
    }

    private function record(string $path, array $query, string $token): string
    {
        $path = trim($path, '/');
        // "api/auth/has-role" -> "has-role"; the SSO guard's ".../user/me" -> "user/me".
        $endpoint = str_ends_with($path, 'user/me') ? 'user/me' : (string) last(explode('/', $path));
        $this->recorded[] = ['endpoint' => $endpoint, 'query' => $query, 'token' => $token];

        return $endpoint;
    }

    private function isLoggedIn(string $authorization): bool
    {
        return trim($authorization) !== '' && !$this->rejectTokens;
    }

    private function answer(string $endpoint, array $query, string $token): array
    {
        if ($endpoint === 'login') {
            return [200, ['data' => ['token' => 'gemboot-fake-token', 'user' => $this->user]]];
        }
        if ($endpoint === 'logout') {
            return [200, ['data' => null]];
        }
        if (!$this->isLoggedIn($token)) {
            return [401, ['message' => 'Unauthenticated']];
        }

        switch ($endpoint) {
            case 'me':
                return [200, ['data' => $this->user]];
            case 'validate-token':
                return [200, ['data' => ['valid' => true]]];
            case 'has-role':
                $names = $this->names($query['role_name'] ?? '');

                return [200, ['data' => ['has_role' => (bool) array_intersect($names, $this->roles)]]];
            case 'has-permission-to':
                $names = $this->names($query['permission_name'] ?? '');
                $granted = array_intersect($names, $this->permissions);

                return [200, ['data' => [
                    'has_permission_to' => $names !== [] && count($granted) === count($names),
                    'has_any_permission' => $granted !== [],
                ]]];
            default:
                return [404, ['message' => 'Not Found']];
        }
    }

    /**
     * Several names joined with "|", as Gemboot sends them.
     */
    private function names(string $joined): array
    {
        return array_values(array_filter(explode('|', $joined), fn ($n) => $n !== ''));
    }

    private function isSsoUserUrl(string $url): bool
    {
        $candidates = array_filter([
            config('gemboot.sso.get_user_url'),
            config('gemboot.sso.user_service_url') ? config('gemboot.sso.user_service_url') . '/user/me' : null,
            config('gemboot.sso.fallback.get_user_url'),
            config('gemboot.sso.fallback.user_service_url') ? config('gemboot.sso.fallback.user_service_url') . '/user/me' : null,
        ]);
        $path = strtok($url, '?');

        return in_array($path, $candidates, true);
    }
}

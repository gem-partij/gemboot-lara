<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Facades\GembootAuthFacade as GembootAuth;
use Gemboot\Libraries\AuthLibrary;
use Gemboot\Middleware\TokenValidated;
use Gemboot\Tests\Support\UsesFakeAuthServer;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthAbuseProtectionTest extends TestCase
{
    use UsesFakeAuthServer;

    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('gemboot.auth.base_api', static::$fakeAuthUrl);
        config()->set('gemboot.auth.failed_attempts.max', 3);
        $this->clearFakeAuthLog();

        $this->app['router']->get('/me', fn () => 'ok')->middleware(TokenValidated::class);
        $this->app['router']->get('/client', fn () => 'ok')->middleware(TokenValidated::class . ':client');
    }

    private function me(?string $authorization)
    {
        return $this->getJson('/me', $authorization === null ? [] : ['Authorization' => $authorization]);
    }

    function test_malformed_tokens_are_rejected_without_calling_the_auth_service()
    {
        config()->set('gemboot.auth.failed_attempts.max', 0);

        $this->me('Bearer <script>alert(1)</script>')->assertStatus(401);
        $this->me('Bearer has spaces inside')->assertStatus(401);
        $this->me('Bearer ' . str_repeat('a', 9000))->assertStatus(401);

        $this->assertCount(0, $this->fakeAuthRequests());
    }

    function test_plausible_tokens_still_reach_the_auth_service()
    {
        $this->me('Bearer good')->assertOk();
        // Auth services that take the raw token without "Bearer" keep working.
        $this->me('eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOjF9.c2ln')->assertStatus(401);

        $this->assertCount(2, $this->fakeAuthRequests());
    }

    function test_too_many_failed_attempts_answer_429_without_calling_the_auth_service()
    {
        foreach (range(1, 3) as $_) {
            $this->me('Bearer bad')->assertStatus(401);
        }

        $this->me('Bearer bad')
            ->assertStatus(429)
            ->assertJsonPath('data.error', 'Too many failed authentication attempts')
            ->assertHeader('Retry-After');
        // The whole client IP is blocked for the moment, also with a valid token.
        $this->me('Bearer good')->assertStatus(429);

        $this->assertCount(3, $this->fakeAuthRequests());
    }

    function test_requests_without_a_token_and_outages_do_not_count()
    {
        foreach (range(1, 5) as $_) {
            $this->me(null)->assertStatus(401);
        }

        config()->set('gemboot.auth.base_api', static::$fakeAuthUrl . 'broken/');
        foreach (range(1, 5) as $_) {
            $this->me('Bearer good')->assertStatus(503);
        }

        config()->set('gemboot.auth.base_api', static::$fakeAuthUrl);
        $this->me('Bearer good')->assertOk();
    }

    function test_limit_can_be_turned_off()
    {
        config()->set('gemboot.auth.failed_attempts.max', 0);

        foreach (range(1, 6) as $_) {
            $this->me('Bearer bad')->assertStatus(401);
        }
    }

    function test_client_mode_gets_the_short_429_answer()
    {
        foreach (range(1, 3) as $_) {
            $this->getJson('/client', ['Authorization' => 'Bearer bad'])->assertStatus(401);
        }

        $this->getJson('/client', ['Authorization' => 'Bearer bad'])
            ->assertStatus(429)
            ->assertExactJson(['status' => 'Too Many Requests']);
    }

    function test_failed_logins_count()
    {
        $auth = new AuthLibrary();
        foreach (range(1, 3) as $_) {
            $this->assertSame(401, $auth->login('npp', 'wrong', true)->getStatusCode());
        }

        $blocked = $auth->login('npp', 'secret', true);

        $this->assertSame(429, $blocked->getStatusCode());
        $this->assertCount(3, $this->fakeAuthRequests());
    }

    function test_sso_guard_counts_each_request_once_and_rejects_malformed_tokens_locally()
    {
        config()->set('gemboot.auth.failed_attempts.max', 2);
        config()->set('gemboot.sso.get_user_url', 'https://users.example.test/user/me');
        config()->set('auth.guards.api', ['driver' => 'gemboot-sso-token', 'provider' => 'gemboot-sso']);
        config()->set('auth.providers.gemboot-sso', ['driver' => 'gemboot-sso-provider']);
        Http::fake(['users.example.test/*' => Http::response(['message' => 'Unauthenticated'], 401)]);
        // The guard is asked several times per request (middleware, then the route).
        $this->app['router']->get('/sso', fn () => [auth('api')->user(), auth('api')->user(), auth('api')->check()])->middleware('auth:api');

        $this->getJson('/sso', ['Authorization' => 'Bearer bad'])->assertStatus(401);
        $this->getJson('/sso', ['Authorization' => 'Bearer bad'])->assertStatus(401);
        $this->getJson('/sso', ['Authorization' => 'Bearer bad'])->assertStatus(429);
        Http::assertSentCount(2);
    }

    function test_sso_guard_does_not_call_the_user_service_for_malformed_tokens()
    {
        config()->set('gemboot.sso.get_user_url', 'https://users.example.test/user/me');
        config()->set('auth.guards.api', ['driver' => 'gemboot-sso-token', 'provider' => 'gemboot-sso']);
        config()->set('auth.providers.gemboot-sso', ['driver' => 'gemboot-sso-provider']);
        Http::fake();
        $this->app['router']->get('/sso', fn () => 'ok')->middleware('auth:api');

        $this->getJson('/sso', ['Authorization' => 'Bearer a<b'])->assertStatus(401);
        Http::assertNothingSent();
    }

    function test_the_fake_auth_service_turns_the_limit_off()
    {
        config()->set('gemboot.auth.failed_attempts.max', 1);
        GembootAuth::fake()->rejectTokens();

        foreach (range(1, 4) as $_) {
            $this->me('Bearer x')->assertStatus(401);
        }
    }
}

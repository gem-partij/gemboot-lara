<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Events\AuthServiceUnavailable;
use Gemboot\Events\TokenRejected;
use Gemboot\Facades\GembootAuthFacade as GembootAuth;
use Gemboot\Facades\GembootPermissionFacade as GembootPermission;
use Gemboot\Middleware\AssignRequestId;
use Gemboot\Middleware\TokenValidated;
use Gemboot\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class GembootAuthEventsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('gemboot.auth.base_api', 'http://auth.test/api/auth/');
        config()->set('auth.guards.api', ['driver' => 'gemboot']);
        Event::fake([AuthServiceUnavailable::class, TokenRejected::class]);

        $router = $this->app['router'];
        $router->get('/me', fn () => 'ok')->middleware([AssignRequestId::class, TokenValidated::class]);
        $router->get('/client', fn () => 'ok')->middleware(TokenValidated::class . ':client');
        $router->get('/guard', fn () => 'ok')->middleware('auth:api');
        $router->get('/sso', fn () => 'ok')->middleware('auth:sso');
    }

    function test_an_outage_fires_auth_service_unavailable()
    {
        GembootAuth::fakeOutage();

        $this->get('/me', ['Authorization' => 'Bearer t1', 'X-Request-Id' => 'abc-1'])->assertStatus(503);

        Event::assertDispatchedTimes(AuthServiceUnavailable::class, 1);
        Event::assertDispatched(AuthServiceUnavailable::class, fn ($e) => $e->endpoint === 'me'
            && $e->status === 0
            && $e->servedFromLastGoodAnswer === false
            && $e->requestId === 'abc-1');
        Event::assertNotDispatched(TokenRejected::class);
    }

    function test_permission_checks_report_outages_too()
    {
        GembootAuth::fakeOutage();
        request()->headers->set('Authorization', 'Bearer t1');

        $this->assertFalse(GembootPermission::hasRole('admin'));

        Event::assertDispatched(AuthServiceUnavailable::class, fn ($e) => $e->endpoint === 'has-role');
    }

    function test_a_rejected_token_fires_token_rejected()
    {
        GembootAuth::fake()->rejectTokens();

        $this->get('/me', ['Authorization' => 'Bearer t1', 'X-Request-Id' => 'abc-1'])->assertStatus(401);

        Event::assertDispatched(TokenRejected::class, fn ($e) => $e->reason === TokenRejected::REJECTED
            && $e->source === 'token-validated'
            && $e->ip === '127.0.0.1'
            && $e->requestId === 'abc-1');
        Event::assertNotDispatched(AuthServiceUnavailable::class);
    }

    function test_a_malformed_token_is_reported_as_malformed()
    {
        GembootAuth::fake();

        $this->get('/me', ['Authorization' => 'Bearer not<a>token'])->assertStatus(401);
        $this->getJson('/guard', ['Authorization' => 'Bearer not<a>token'])->assertStatus(401);

        Event::assertDispatched(TokenRejected::class, fn ($e) => $e->reason === TokenRejected::MALFORMED && $e->source === 'token-validated');
        Event::assertDispatched(TokenRejected::class, fn ($e) => $e->reason === TokenRejected::MALFORMED && $e->source === 'gemboot-guard');
    }

    function test_requests_without_a_token_fire_nothing()
    {
        GembootAuth::fake();

        $this->get('/me')->assertStatus(401);
        $this->getJson('/guard')->assertStatus(401);

        Event::assertNotDispatched(TokenRejected::class);
    }

    function test_client_tokens_and_the_gemboot_guard_report_rejections()
    {
        GembootAuth::fake()->rejectTokens();

        $this->get('/client', ['Authorization' => 'Bearer t1'])->assertStatus(401);
        $this->getJson('/guard', ['Authorization' => 'Bearer t1'])->assertStatus(401);

        Event::assertDispatched(TokenRejected::class, fn ($e) => $e->source === 'token-validated:client');
        Event::assertDispatched(TokenRejected::class, fn ($e) => $e->source === 'gemboot-guard' && $e->reason === TokenRejected::REJECTED);
    }

    function test_the_sso_guard_reports_rejections_and_outages()
    {
        config()->set('auth.guards.sso', ['driver' => 'gemboot-sso-token', 'provider' => 'gemboot-sso']);
        config()->set('auth.providers.gemboot-sso', ['driver' => 'gemboot-sso-provider']);
        config()->set('gemboot.sso.get_user_url', 'https://users.test/user/me');
        Http::fake([
            'users.test/*' => Http::sequence()
                ->push(['message' => 'Unauthenticated'], 401)
                ->push(['message' => 'Down'], 502),
        ]);

        $this->getJson('/sso', ['Authorization' => 'Bearer t1'])->assertStatus(401);
        $this->getJson('/sso', ['Authorization' => 'Bearer t2']);

        Event::assertDispatched(TokenRejected::class, fn ($e) => $e->source === 'sso-guard' && $e->reason === TokenRejected::REJECTED);
        Event::assertDispatched(AuthServiceUnavailable::class, fn ($e) => $e->endpoint === 'user/me' && $e->status === 502);
    }

    function test_events_never_carry_the_token()
    {
        GembootAuth::fake()->rejectTokens();

        $this->get('/me', ['Authorization' => 'Bearer secret-token-123'])->assertStatus(401);

        Event::assertDispatched(TokenRejected::class, fn ($e) => !str_contains(serialize($e), 'secret-token-123'));
    }
}

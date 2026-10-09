<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Events\TokenRejected;
use Gemboot\Exceptions\ServiceUnavailableException;
use Gemboot\Facades\GembootAuthFacade as GembootAuth;
use Gemboot\SSO\Auth\SSOGuard;
use Gemboot\SSO\Auth\SSOUserProvider;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class GembootSsoOutageTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('gemboot.sso.get_user_url', 'https://primary.test/user/me');
        config()->set('gemboot.sso.fallback.get_user_url', null);
        config()->set('gemboot.sso.fallback.user_service_url', null);
        config()->set('auth.guards.sso', ['driver' => 'gemboot-sso-token', 'provider' => 'gemboot-sso']);
        config()->set('auth.providers.gemboot-sso', ['driver' => 'gemboot-sso-provider']);
        Event::fake([TokenRejected::class]);

        $this->app['router']->get('/sso/me', fn (Request $r) => response()->json(['id' => $r->user()->getAuthIdentifier()]))->middleware('auth:sso');
    }

    private function guard(): SSOGuard
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer token-123');

        return new SSOGuard(new SSOUserProvider(), $request);
    }

    private function assertUnavailable(): void
    {
        try {
            $this->guard()->user();
            $this->fail('Expected a ServiceUnavailableException.');
        } catch (ServiceUnavailableException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }

        // An outage is not the client's fault: no rejection is counted.
        Event::assertNotDispatched(TokenRejected::class);
    }

    function test_an_unreachable_user_service_answers_503_on_a_route()
    {
        // The boilerplate's case: GembootAuth::fakeOutage() on an auth:<sso guard> route.
        GembootAuth::fakeOutage();

        $this->getJson('/sso/me', ['Authorization' => 'Bearer x'])->assertStatus(503);
    }

    function test_an_unreachable_user_service_without_fallback_is_unavailable()
    {
        Http::fake(['primary.test/*' => Http::failedConnection()]);

        $this->assertUnavailable();
    }

    function test_a_5xx_without_fallback_is_unavailable_not_a_rejection()
    {
        // Before 8.14.1 this returned no user (401), which logs users out during an outage.
        Http::fake(['primary.test/*' => Http::response(['message' => 'Down'], 502)]);

        $this->assertUnavailable();
    }

    function test_both_services_unreachable_is_unavailable()
    {
        config()->set('gemboot.sso.fallback.get_user_url', 'https://fallback.test/user/me');
        Http::fake([
            'primary.test/*' => Http::failedConnection(),
            'fallback.test/*' => Http::failedConnection(),
        ]);

        $this->assertUnavailable();
    }

    function test_a_rejection_by_the_primary_stays_a_rejection_when_the_fallback_is_down()
    {
        config()->set('gemboot.sso.fallback.get_user_url', 'https://fallback.test/user/me');
        Http::fake([
            'primary.test/*' => Http::response(['message' => 'Unauthenticated'], 401),
            'fallback.test/*' => Http::failedConnection(),
        ]);

        $this->assertNull($this->guard()->user());
        Event::assertDispatched(TokenRejected::class);
    }

    function test_a_rejection_by_the_fallback_after_a_primary_outage_is_a_rejection()
    {
        config()->set('gemboot.sso.fallback.get_user_url', 'https://fallback.test/user/me');
        Http::fake([
            'primary.test/*' => Http::response(['message' => 'Down'], 503),
            'fallback.test/*' => Http::response(['message' => 'Unauthenticated'], 401),
        ]);

        $this->assertNull($this->guard()->user());
    }

    function test_the_fallback_still_answers_during_a_primary_outage()
    {
        config()->set('gemboot.sso.fallback.get_user_url', 'https://fallback.test/user/me');
        Http::fake([
            'primary.test/*' => Http::response(['message' => 'Down'], 500),
            'fallback.test/*' => Http::response(['data' => ['id' => 9]]),
        ]);

        $this->assertSame(9, $this->guard()->user()->getAuthIdentifier());
    }
}

<?php

namespace Gemboot\Tests\Unit;

use Gemboot\SSO\Auth\SSOGuard;
use Gemboot\SSO\Auth\SSOUserProvider;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GembootSSOGuardTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('gemboot.sso.get_user_url', 'https://primary.test/user/me');
        config()->set('gemboot.sso.fallback.get_user_url', null);
        config()->set('gemboot.sso.fallback.user_service_url', null);
    }

    protected function makeGuard(string $token = 'token-123'): SSOGuard
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', "Bearer {$token}");

        return new SSOGuard(new SSOUserProvider(), $request);
    }

    function test_valid_token_returns_user_and_caches_it()
    {
        Http::fake(['primary.test/*' => Http::response(['data' => ['user' => ['id' => 7, 'name' => 'Ana']]])]);

        $user = $this->makeGuard()->user();

        $this->assertSame(7, $user->getAuthIdentifier());
        $this->assertNotNull(Cache::get('sso_token_' . sha1('token-123')));
    }

    function test_invalid_token_without_fallback_returns_null()
    {
        // Before the fix the unconfigured fallback URL became "/user/me" and this threw.
        Http::fake(['primary.test/*' => Http::response(['message' => 'Unauthenticated'], 401)]);

        $this->assertNull($this->makeGuard()->user());
        Http::assertSentCount(1);
    }

    function test_unreachable_primary_uses_fallback()
    {
        // Before the fix a connection error was rethrown without trying the fallback.
        config()->set('gemboot.sso.fallback.get_user_url', 'https://fallback.test/user/me');
        Http::fake([
            'primary.test/*' => Http::failedConnection(),
            'fallback.test/*' => Http::response(['data' => ['id' => 9, 'name' => 'Budi']]),
        ]);

        $this->assertSame(9, $this->makeGuard()->user()->getAuthIdentifier());
    }

    function test_rejected_token_still_tries_configured_fallback()
    {
        // Unchanged behavior: a configured fallback is tried when the primary says no.
        config()->set('gemboot.sso.fallback.get_user_url', 'https://fallback.test/user/me');
        Http::fake([
            'primary.test/*' => Http::response([], 401),
            'fallback.test/*' => Http::response([], 401),
        ]);

        $this->assertNull($this->makeGuard()->user());
        Http::assertSentCount(2);
    }

    function test_ok_reply_without_user_object_does_not_authenticate()
    {
        // An HTML page or {"data": null} used to produce a logged-in user and was cached.
        foreach (['<html>app</html>', json_encode(['data' => null])] as $i => $body) {
            Http::fake(['primary.test/*' => Http::response($body, 200)]);
            $token = "token-{$i}";

            $this->assertNull($this->makeGuard($token)->user());
            $this->assertNull(Cache::get('sso_token_' . sha1($token)));
        }
    }
}

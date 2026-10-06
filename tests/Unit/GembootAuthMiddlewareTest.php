<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Libraries\AuthLibrary;
use Gemboot\Middleware\HasPermissionTo;
use Gemboot\Middleware\HasRole;
use Gemboot\Middleware\TokenValidated;
use Gemboot\Tests\Support\UsesFakeAuthServer;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Request;

class GembootAuthMiddlewareTest extends TestCase
{
    use UsesFakeAuthServer;

    public function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('gemboot.auth.base_api', static::$fakeAuthUrl);
        config()->set('gemboot.auth.cache_ttl', 0);
        $this->clearFakeAuthLog();

        $router = $this->app['router'];
        $router->get('/mw/token', fn () => 'ok')->middleware(TokenValidated::class);
        $router->get('/mw/client', fn () => 'ok')->middleware(TokenValidated::class . ':client');
        $router->get('/mw/role', fn () => 'ok')->middleware(HasRole::class . ':admin');
        $router->get('/mw/permission', fn () => 'ok')->middleware(HasPermissionTo::class . ':user.read');
    }

    protected function useBrokenAuthService(): void
    {
        config()->set('gemboot.auth.base_api', static::$fakeAuthUrl . 'broken/');
    }

    protected function useUnreachableAuthService(): void
    {
        config()->set('gemboot.auth.base_api', 'http://127.0.0.1:1/');
    }

    function test_token_validated()
    {
        $this->getJson('/mw/token', ['Authorization' => 'Bearer good'])->assertStatus(200);
        $this->getJson('/mw/token', ['Authorization' => 'Bearer bad'])->assertStatus(401);
    }

    function test_auth_service_outage_returns_503_not_401()
    {
        // Before 8.1.0 an outage answered 401, so clients logged their users out.
        $this->useBrokenAuthService();
        $this->getJson('/mw/token', ['Authorization' => 'Bearer good'])
            ->assertStatus(503)
            ->assertJsonPath('data.error', 'Auth service unavailable');

        $this->useUnreachableAuthService();
        $this->getJson('/mw/token', ['Authorization' => 'Bearer good'])->assertStatus(503);
    }

    function test_client_mode_outage_returns_503()
    {
        $this->getJson('/mw/client', ['Authorization' => 'Bearer bad'])
            ->assertStatus(401)
            ->assertExactJson(['status' => 'Unauthorized']);

        $this->useBrokenAuthService();
        $this->getJson('/mw/client', ['Authorization' => 'Bearer good'])
            ->assertStatus(503)
            ->assertExactJson(['status' => 'Service Unavailable']);
    }

    function test_role_and_permission_outage_returns_503_not_403()
    {
        $this->getJson('/mw/role', ['Authorization' => 'Bearer good'])->assertStatus(200);
        $this->getJson('/mw/permission', ['Authorization' => 'Bearer good'])->assertStatus(200);

        $this->useBrokenAuthService();
        $this->getJson('/mw/role', ['Authorization' => 'Bearer good'])->assertStatus(503);
        $this->getJson('/mw/permission', ['Authorization' => 'Bearer good'])->assertStatus(503);
    }

    function test_no_cache_by_default()
    {
        $this->getJson('/mw/token', ['Authorization' => 'Bearer good'])->assertStatus(200);
        $this->getJson('/mw/token', ['Authorization' => 'Bearer good'])->assertStatus(200);

        $this->assertCount(2, $this->fakeAuthRequests());
    }

    function test_cache_ttl_caches_answers_per_token()
    {
        config()->set('gemboot.auth.cache_ttl', 60);

        $this->getJson('/mw/token', ['Authorization' => 'Bearer good'])->assertStatus(200);
        $this->getJson('/mw/token', ['Authorization' => 'Bearer good'])->assertStatus(200);
        $this->getJson('/mw/token', ['Authorization' => 'Bearer bad'])->assertStatus(401);
        $this->getJson('/mw/token', ['Authorization' => 'Bearer bad'])->assertStatus(401);

        // One call per token: the 200 and the 401 are both definite answers.
        $this->assertCount(2, $this->fakeAuthRequests());
    }

    function test_cache_never_stores_outages()
    {
        config()->set('gemboot.auth.cache_ttl', 60);

        // The fake service answers 500 to the first "flaky" request, then 200.
        $this->getJson('/mw/token', ['Authorization' => 'Bearer flaky'])->assertStatus(503);
        $this->getJson('/mw/token', ['Authorization' => 'Bearer flaky'])->assertStatus(200);
        $this->getJson('/mw/token', ['Authorization' => 'Bearer flaky'])->assertStatus(200);

        $this->assertCount(2, $this->fakeAuthRequests());
    }

    function test_logout_clears_cached_answers_for_that_token()
    {
        config()->set('gemboot.auth.cache_ttl', 60);
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer good');

        $auth = new AuthLibrary();
        $this->assertNotFalse($auth->me(false, $request));
        $this->assertNotFalse($auth->me(false, $request));
        $this->assertCount(1, $this->fakeAuthRequests());

        $auth->logout(false, $request);
        $this->assertNotFalse($auth->me(false, $request));

        // me, logout, me again (the cache entry was invalidated).
        $this->assertCount(3, $this->fakeAuthRequests());
    }
}

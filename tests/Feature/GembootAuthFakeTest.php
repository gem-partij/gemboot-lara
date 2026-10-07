<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Facades\GembootAuthFacade as GembootAuth;
use Gemboot\GembootPermission;
use Gemboot\Libraries\AuthLibrary;
use Gemboot\Middleware\HasPermissionTo;
use Gemboot\Middleware\HasRole;
use Gemboot\Middleware\TokenValidated;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Tests written the way an app using Gemboot would test its own API.
 */
class GembootAuthFakeTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        // No real auth service anywhere: the fake must answer everything.
        config()->set('gemboot.auth.base_api', null);

        $router = $this->app['router'];
        $router->get('/me', fn (Request $r) => response()->json($r->user_login))->middleware(TokenValidated::class);
        $router->get('/admin', fn () => 'ok')->middleware([TokenValidated::class, HasRole::class . ':admin']);
        $router->get('/reports', fn () => 'ok')->middleware([TokenValidated::class, HasPermissionTo::class . ':report.read']);
    }

    private function bearer(): array
    {
        return ['Authorization' => 'Bearer any-token'];
    }

    function test_the_fake_user_is_logged_in()
    {
        GembootAuth::fake(user: ['id' => 7, 'name' => 'Ana']);

        $this->getJson('/me', $this->bearer())->assertOk()->assertJson(['id' => 7, 'name' => 'Ana']);
    }

    function test_requests_without_a_token_are_rejected()
    {
        GembootAuth::fake();

        $this->getJson('/me')->assertStatus(401);
    }

    function test_roles_and_permissions()
    {
        GembootAuth::fake(roles: ['admin'], permissions: ['report.read']);
        $this->getJson('/admin', $this->bearer())->assertOk();
        $this->getJson('/reports', $this->bearer())->assertOk();

        GembootAuth::fake(roles: ['editor'], permissions: []);
        $this->getJson('/admin', $this->bearer())->assertStatus(403);
        $this->getJson('/reports', $this->bearer())->assertStatus(403);
    }

    function test_outage_and_rejected_tokens()
    {
        GembootAuth::fakeOutage();
        $this->getJson('/me', $this->bearer())->assertStatus(503);

        GembootAuth::fake()->rejectTokens();
        $this->getJson('/me', $this->bearer())->assertStatus(401);
    }

    function test_gemboot_permission_and_auth_library()
    {
        GembootAuth::fake(user: ['id' => 7], roles: ['admin'], permissions: ['a', 'b']);
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer any-token');
        $this->app->instance('request', $request);

        $permission = new GembootPermission();
        $this->assertTrue($permission->hasRole(['editor', 'admin']));
        $this->assertTrue($permission->hasPermissionTo('a'));
        $this->assertFalse($permission->hasPermissionTo('c'));
        // An array means "any of these".
        $this->assertTrue($permission->hasPermissionTo(['c', 'b']));

        $this->assertSame(['id' => 7], (new AuthLibrary)->me());
        $this->assertSame(200, (new AuthLibrary)->login('npp', 'secret', true)->getStatusCode());
    }

    function test_assertions_on_calls()
    {
        $fake = GembootAuth::fake(roles: ['admin']);

        $this->getJson('/admin', $this->bearer())->assertOk();

        $fake->assertCalled('me', 1)->assertCalled('has-role')->assertNotCalled('has-permission-to');
        $this->assertSame('admin', $fake->requests()[1]['query']['role_name']);
    }

    function test_sso_guard_is_answered_too()
    {
        config()->set('gemboot.sso.get_user_url', 'https://users.example.test/user/me');
        config()->set('auth.guards.api', ['driver' => 'gemboot-sso-token', 'provider' => 'gemboot-sso']);
        config()->set('auth.providers.gemboot-sso', ['driver' => 'gemboot-sso-provider']);
        $this->app['router']->get('/sso-me', fn () => response()->json([
            'id' => auth('api')->user()->getAuthIdentifier(),
            'roles' => auth('api')->user()->roles,
        ]))->middleware('auth:api');

        $fake = GembootAuth::fake(user: ['id' => 9], roles: ['admin']);

        $this->getJson('/sso-me', ['Authorization' => 'Bearer sso-token'])
            ->assertOk()
            ->assertJson(['id' => 9, 'roles' => ['admin']]);
        $this->getJson('/sso-me')->assertStatus(401);
        $fake->assertCalled('user/me');
    }

    function test_other_http_calls_are_not_affected()
    {
        // The SSO stub only answers the configured user service URL.
        Http::fake(['https://other.example.test/*' => Http::response(['ok' => true])]);
        GembootAuth::fake();

        $this->assertTrue(Http::get('https://other.example.test/ping')->json('ok'));
    }
}

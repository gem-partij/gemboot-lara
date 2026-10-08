<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Auth\GembootUser;
use Gemboot\Auth\TokenVerifier;
use Gemboot\Facades\GembootAuthFacade as GembootAuth;
use Gemboot\Middleware\HasPermissionTo;
use Gemboot\Middleware\HasRole;
use Gemboot\Middleware\TokenValidated;
use Gemboot\Tests\Support\StaticTokenVerifier;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GembootGuardTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('gemboot.auth.base_api', 'http://auth.test/api/auth/');
        config()->set('auth.guards.api', ['driver' => 'gemboot']);

        $whoami = fn (Request $r) => response()->json([
            'class' => $r->user() ? get_class($r->user()) : null,
            'id' => $r->user()?->getAuthIdentifier(),
            'user' => $r->user(),
        ]);

        $router = $this->app['router'];
        $router->get('/guard/me', $whoami)->middleware('auth:api');
        $router->get('/token-validated/me', fn () => response()->json(['id' => auth()->id()]))->middleware(TokenValidated::class);
        $router->get('/guard/admin', fn () => 'ok')->middleware(['auth:api', HasRole::class . ':admin']);
        $router->get('/admin-only', fn () => 'ok')->middleware(HasRole::class . ':admin');
        $router->get('/guard/report', fn () => 'ok')->middleware(['auth:api', HasPermissionTo::class . ':report.read']);
        $router->get('/guard/report-export', fn () => 'ok')->middleware(['auth:api', HasPermissionTo::class . ':report.read|report.export']);
        $router->get('/guard/gate', fn () => response()->json(['allowed' => Gate::allows('see-reports')]))->middleware('auth:api');
    }

    private function bindVerifier(array $users): StaticTokenVerifier
    {
        $verifier = new StaticTokenVerifier($users);
        $this->app->instance(TokenVerifier::class, $verifier);

        return $verifier;
    }

    function test_a_valid_token_gives_the_auth_service_user()
    {
        GembootAuth::fake(user: ['id' => 7, 'name' => 'Ana']);

        $this->getJson('/guard/me', ['Authorization' => 'Bearer t1'])
            ->assertOk()
            ->assertJsonPath('class', GembootUser::class)
            ->assertJsonPath('id', 7)
            ->assertJsonPath('user.name', 'Ana');
    }

    function test_a_rejected_token_is_401_and_asked_once()
    {
        $fake = GembootAuth::fake()->rejectTokens();

        $this->getJson('/guard/me', ['Authorization' => 'Bearer t1'])->assertStatus(401);

        $fake->assertCalled('me', 1);
    }

    function test_a_malformed_token_never_reaches_the_auth_service()
    {
        $fake = GembootAuth::fake();

        $this->getJson('/guard/me', ['Authorization' => 'Bearer not<a>token'])->assertStatus(401);

        $fake->assertNotCalled('me');
    }

    function test_an_outage_answers_503()
    {
        GembootAuth::fakeOutage();

        $this->getJson('/guard/me', ['Authorization' => 'Bearer t1'])->assertStatus(503);
    }

    function test_too_many_failed_attempts_answer_429()
    {
        config()->set('gemboot.auth.failed_attempts.max', 1);

        // Malformed: rejected locally, counted, no auth service needed.
        $this->getJson('/guard/me', ['Authorization' => 'Bearer not<a>token'])->assertStatus(401);
        $this->getJson('/guard/me', ['Authorization' => 'Bearer t1'])->assertStatus(429);
    }

    function test_the_guard_follows_each_request()
    {
        $this->bindVerifier([
            'ana' => new GembootUser(['id' => 1]),
            'budi' => new GembootUser(['id' => 2]),
        ]);

        $this->getJson('/guard/me', ['Authorization' => 'Bearer ana'])->assertJsonPath('id', 1);
        $this->getJson('/guard/me', ['Authorization' => 'Bearer budi'])->assertJsonPath('id', 2);
        $this->getJson('/guard/me')->assertStatus(401);
    }

    function test_acting_as_keeps_its_user()
    {
        $fake = GembootAuth::fake();
        $this->actingAs(new GembootUser(['id' => 9]), 'api');

        $this->getJson('/guard/me')->assertJsonPath('id', 9);
        $this->getJson('/guard/me')->assertJsonPath('id', 9);

        $fake->assertNotCalled('me');
    }

    function test_token_validated_hands_its_user_to_a_gemboot_default_guard()
    {
        config()->set('auth.defaults.guard', 'api');
        $fake = GembootAuth::fake(user: ['id' => 7]);

        $this->getJson('/token-validated/me', ['Authorization' => 'Bearer t1'])->assertJsonPath('id', 7);

        // One me call: the guard didn't verify the token again.
        $fake->assertCalled('me', 1);
    }

    function test_token_validated_leaves_other_default_guards_alone()
    {
        GembootAuth::fake(user: ['id' => 7]);

        $this->getJson('/token-validated/me', ['Authorization' => 'Bearer t1'])->assertJsonPath('id', null);
    }

    function test_role_answers_locally_when_the_verifier_knows_the_roles()
    {
        // The fake auth service would deny: proves the answer is local.
        $fake = GembootAuth::fake(roles: []);
        $this->bindVerifier([
            'admin' => new GembootUser(['id' => 1], roles: ['admin']),
            'editor' => new GembootUser(['id' => 2], roles: ['editor']),
        ]);

        $this->get('/guard/admin', ['Authorization' => 'Bearer admin'])->assertOk();
        $this->get('/guard/admin', ['Authorization' => 'Bearer editor'])->assertStatus(403);

        $fake->assertNotCalled('has-role');
    }

    function test_acting_as_with_known_roles_needs_no_auth_service()
    {
        $fake = GembootAuth::fake(roles: ['admin']);
        $this->actingAs(new GembootUser(['id' => 9], roles: ['editor']), 'api');

        $this->get('/guard/admin')->assertStatus(403);

        $fake->assertNotCalled('has-role');
    }

    function test_role_asks_the_auth_service_when_roles_are_unknown()
    {
        $fake = GembootAuth::fake(roles: ['admin']);

        $this->get('/guard/admin', ['Authorization' => 'Bearer t1'])->assertOk();

        $fake->assertCalled('has-role', 1);
    }

    function test_role_never_resolves_the_user_just_to_check_it()
    {
        config()->set('auth.defaults.guard', 'api');
        $fake = GembootAuth::fake(roles: ['admin']);

        $this->get('/admin-only', ['Authorization' => 'Bearer t1'])->assertOk();

        $fake->assertNotCalled('me');
        $fake->assertCalled('has-role', 1);
    }

    function test_permission_answers_locally_with_the_same_rules()
    {
        $fake = GembootAuth::fake(permissions: []);
        $this->bindVerifier(['t1' => new GembootUser(['id' => 1], permissions: ['report.read'])]);

        $this->get('/guard/report', ['Authorization' => 'Bearer t1'])->assertOk();
        // "a|b" needs both, as with the auth service.
        $this->get('/guard/report-export', ['Authorization' => 'Bearer t1'])->assertStatus(403);

        $fake->assertNotCalled('has-permission-to');
    }

    function test_gates_receive_the_gemboot_user()
    {
        GembootAuth::fake(user: ['id' => 7]);
        Gate::define('see-reports', fn (GembootUser $user) => $user->id === 7);

        $this->getJson('/guard/gate', ['Authorization' => 'Bearer t1'])->assertJsonPath('allowed', true);
    }
}

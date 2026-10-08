<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Facades\GembootAuthFacade as GembootAuth;
use Gemboot\Middleware\TokenValidated;
use Gemboot\Tests\Controllers\TestUserController;
use Gemboot\Tests\Controllers\TestUserPolicyController;
use Gemboot\Tests\Controllers\TestUserPolicySharedCacheController;
use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Policies\TestUserPolicy;
use Gemboot\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GembootPolicyAuthorizationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        Gate::policy(TestUser::class, TestUserPolicy::class);

        $router = $this->app['router'];
        $router->apiResource('policy/users', TestUserPolicyController::class);
        $router->apiResource('plain/users', TestUserController::class)->only(['show']);
        $router->apiResource('shared/users', TestUserPolicySharedCacheController::class)->only(['show']);
        $router->middleware(TokenValidated::class)->group(function () use ($router) {
            $router->apiResource('token/users', TestUserPolicyController::class)->only(['show']);
        });

        TestUser::factory()->create(['name' => 'One']);   // id 1
        TestUser::factory()->create(['name' => 'Two']);   // id 2
    }

    protected function as(int $id): static
    {
        return $this->actingAs(new GenericUser(['id' => $id]));
    }

    function test_own_record_is_allowed_others_are_forbidden()
    {
        $this->as(1)->getJson('/policy/users/1')->assertOk()->assertJsonPath('data.name', 'One');

        $this->as(1)->getJson('/policy/users/2')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden')
            ->assertJsonPath('data.error', 'This action is unauthorized.');
    }

    function test_index_uses_view_any()
    {
        $this->as(2)->getJson('/policy/users')->assertOk();
    }

    function test_store_is_checked_and_rolled_back_when_denied()
    {
        $data = ['name' => 'New', 'email' => 'new@example.test', 'password' => 'secret'];

        $this->as(2)->postJson('/policy/users', $data)->assertStatus(403);
        $this->assertSame(2, TestUser::count());
        $this->assertSame(0, DB::transactionLevel());

        $this->as(1)->postJson('/policy/users', $data)->assertOk();
        $this->assertSame(3, TestUser::count());
    }

    function test_update_and_delete_are_checked_per_record()
    {
        $this->as(1)->putJson('/policy/users/2', ['name' => 'Hacked'])->assertStatus(403);
        $this->assertSame('Two', TestUser::find(2)->name);

        $this->as(2)->putJson('/policy/users/2', ['name' => 'Two Updated'])->assertOk();
        $this->assertSame('Two Updated', TestUser::find(2)->name);

        $this->as(2)->deleteJson('/policy/users/2')->assertStatus(403);
        $this->assertNotNull(TestUser::find(2));
    }

    function test_guests_are_forbidden()
    {
        $this->getJson('/policy/users/1')->assertStatus(403);
    }

    function test_token_validated_users_reach_the_policy()
    {
        // No Laravel guard: the policy gets user_login wrapped as an SSOUser.
        GembootAuth::fake(user: ['id' => 2]);

        $this->getJson('/token/users/2', ['Authorization' => 'Bearer x'])->assertOk();
        $this->getJson('/token/users/1', ['Authorization' => 'Bearer x'])->assertStatus(403);
    }

    function test_policies_are_ignored_unless_turned_on()
    {
        $this->as(1)->getJson('/plain/users/2')->assertOk();
    }

    function test_cached_records_are_still_authorized()
    {
        $this->as(1)->getJson('/shared/users/1')->assertOk();   // cached, shared between users
        $this->as(2)->getJson('/shared/users/1')->assertStatus(403);
    }

    function test_missing_policy_fails_closed()
    {
        // The flag is on but no policy is registered (e.g. wrong namespace): the
        // checks must not be skipped silently.
        // A fresh gate without registered policies, and without Laravel's guessing
        // by naming convention (which would find Policies\TestUserPolicy).
        $gate = new \Illuminate\Auth\Access\Gate($this->app, fn () => null);
        $gate->guessPolicyNamesUsing(fn () => []);
        $this->app->instance(\Illuminate\Contracts\Auth\Access\Gate::class, $gate);
        Gate::swap($gate);

        $this->as(1)->getJson('/policy/users/2')->assertStatus(500)->assertJsonMissingPath('data.name');
        $this->as(1)->putJson('/policy/users/2', ['name' => 'Hacked'])->assertStatus(500);
        $this->as(1)->deleteJson('/policy/users/2')->assertStatus(500);

        $this->assertSame('Two', TestUser::find(2)->name);
    }
}

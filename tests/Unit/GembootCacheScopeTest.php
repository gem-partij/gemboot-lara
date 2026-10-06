<?php

namespace Gemboot\Tests\Unit;

use Gemboot\SSO\Auth\SSOGuard;
use Gemboot\SSO\Auth\SSOUserProvider;
use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Observers\TestUserCachingObserver;
use Gemboot\Tests\Services\TestUserScopedService;
use Gemboot\Tests\Services\TestUserService;
use Gemboot\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GembootCacheScopeTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
    }

    protected function loggedIn(TestUser $user): GenericUser
    {
        // TestUser is not Authenticatable; only auth()->id() matters here.
        return new GenericUser(['id' => $user->id]);
    }

    function test_service_cache_is_per_logged_in_user()
    {
        // The scope lives inside getQueryListAll(), so only the cache key can keep
        // users apart. Before 8.2.0, user B got user A's cached list.
        $alpha = TestUser::factory()->create(['name' => 'Alpha']);
        $beta = TestUser::factory()->create(['name' => 'Beta']);
        $service = (new TestUserScopedService)->setObserver(new TestUserCachingObserver);

        $this->actingAs($this->loggedIn($alpha));
        $this->assertSame('Alpha', $service->listAll()->first()->name);

        $this->actingAs($this->loggedIn($beta));
        $this->assertSame('Beta', $service->listAll()->first()->name);
    }

    function test_cache_scope_can_be_shared_on_purpose()
    {
        $alpha = TestUser::factory()->create(['name' => 'Alpha']);
        $beta = TestUser::factory()->create(['name' => 'Beta']);
        $shared = new class extends TestUserScopedService {
            protected function cacheScope()
            {
                return null;
            }
        };
        $shared->setObserver(new TestUserCachingObserver);

        $this->actingAs($this->loggedIn($alpha));
        $shared->listAll();
        $this->actingAs($this->loggedIn($beta));

        // Shared on purpose: user B gets the entry cached for user A.
        $this->assertSame('Alpha', $shared->listAll()->first()->name);
    }

    function test_controller_cache_is_per_logged_in_user()
    {
        $this->app->bind(TestUserService::class, TestUserScopedService::class);
        $alpha = TestUser::factory()->create(['name' => 'Alpha']);
        $beta = TestUser::factory()->create(['name' => 'Beta']);

        $this->actingAs($this->loggedIn($alpha))->getJson('/test-cached/users')->assertJsonPath('data.data.0.name', 'Alpha');
        $this->actingAs($this->loggedIn($beta))->getJson('/test-cached/users')->assertJsonPath('data.data.0.name', 'Beta');
    }

    function test_controller_cache_is_cleared_by_the_caching_observer()
    {
        // The controller's own cache was untagged, so the observer could not clear
        // it and /users/{id} kept serving the old data until cache_seconds passed.
        TestUser::observe(TestUserCachingObserver::class);
        $user = TestUser::factory()->create(['name' => 'Before']);

        $this->getJson("/test-cached/users/{$user->id}")->assertJsonPath('data.name', 'Before');
        $user->update(['name' => 'After']);

        $this->getJson("/test-cached/users/{$user->id}")->assertJsonPath('data.name', 'After');
    }

    function test_sso_forget_cached_user()
    {
        config()->set('gemboot.sso.get_user_url', 'https://primary.test/user/me');
        Http::fake(['primary.test/*' => Http::response(['data' => ['id' => 7, 'name' => 'Ana']])]);
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer token-123');
        $guard = new SSOGuard(new SSOUserProvider(), $request);

        $this->assertSame(7, $guard->user()->getAuthIdentifier());
        $this->assertNotNull(Cache::get('sso_token_' . sha1('token-123')));

        $guard->forgetCachedUser();

        $this->assertNull(Cache::get('sso_token_' . sha1('token-123')));
        $guard->user();
        Http::assertSentCount(2);
    }
}

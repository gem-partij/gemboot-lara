<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Services\TestUserService;
use Gemboot\Observers\CoreEloquentCachingObserver;

class GembootServiceCacheTest extends TestCase
{

    protected function makeCachedService()
    {
        $observer = new class extends CoreEloquentCachingObserver {
        };

        return (new TestUserService)->setObserver($observer);
    }

    function test_find_or_fail_works_on_store_without_tags()
    {
        // The file store has no tag support. Previously env('CACHE_DRIVER') was null
        // on Laravel 11+, so tags() was still called and threw an exception.
        config()->set('cache.default', 'file');
        cache()->flush();
        $user = TestUser::factory()->create();

        $service = $this->makeCachedService();
        $found = $service->findOrFail($user->id);

        $this->assertSame($user->id, $found->id);

        // Without tags the observer cannot flush, so nothing may be cached.
        $cacheKey = $service->getCacheKey('gemboot_test_user', $service->generateCacheKey($user->id));
        $this->assertFalse(cache()->has($cacheKey));
    }

    function test_list_all_works_on_store_without_tags()
    {
        config()->set('cache.default', 'file');
        cache()->flush();
        TestUser::factory()->count(3)->create();

        $list = $this->makeCachedService()->listAll();

        $this->assertNotEmpty($list);
    }

    function test_find_or_fail_uses_tags_on_store_with_tags()
    {
        config()->set('cache.default', 'array');
        $user = TestUser::factory()->create();
        $service = $this->makeCachedService();

        $service->findOrFail($user->id);

        $cacheKey = $service->getCacheKey('gemboot_test_user', $service->generateCacheKey($user->id));
        $this->assertTrue(cache()->tags($service->getCacheTags('gemboot_test_user'))->has($cacheKey));
    }

    function test_cache_key_ignores_request_body_and_query_order()
    {
        // The key was built from json_encode(request()->all()), so request bodies
        // (passwords on update) ended up in cache key names.
        $service = new TestUserService;

        $this->app->instance('request', \Illuminate\Http\Request::create('/test/users/1?b=2&a=1', 'PUT'));
        $withoutBody = $service->generateCacheKey('main');

        $this->app->instance('request', \Illuminate\Http\Request::create('/test/users/1?a=1&b=2', 'PUT', ['password' => 'secret-value']));
        $withBody = $service->generateCacheKey('main');

        // Same query (in any order), different body: same key, and no body in it.
        $this->assertSame($withoutBody, $withBody);
        $this->assertStringNotContainsString('secret-value', $withBody);
        $this->assertLessThan(250, strlen($withBody));
    }

    function test_cache_key_uses_single_and_group_prefixes()
    {
        // getCacheKey() read c_cache_observer.*, which Gemboot doesn't define, so
        // both prefixes were empty ("table--id").
        $service = new TestUserService;

        $this->assertSame('users-single-5', $service->getCacheKey('users', 5));
        $this->assertSame('users-group-list', $service->getCacheKey('users', 'list', 'group'));
    }

    function test_cache_key_keeps_users_apart()
    {
        // Services often scope queries to the current user, so the user merged in
        // by TokenValidated must stay in the key.
        $service = new TestUserService;
        $keyFor = function (array $user) use ($service) {
            $request = \Illuminate\Http\Request::create('/test/users?page=2', 'GET');
            $request->merge(['user_login' => $user]);
            $request->attributes->set(\Gemboot\Middleware\TokenValidated::USER_LOGIN_MERGED, true);
            $this->app->instance('request', $request);

            return $service->generateCacheKey('main');
        };

        $this->assertNotSame($keyFor(['id' => 1]), $keyFor(['id' => 2]));
    }

    function test_list_all_cache_key_includes_the_query_passed_in()
    {
        // listAll($query) ignored $query in the key, so a list scoped to one user
        // was served to everyone with the same query string.
        config()->set('cache.default', 'array');
        $alpha = TestUser::factory()->create(['name' => 'Alpha']);
        $beta = TestUser::factory()->create(['name' => 'Beta']);
        $service = $this->makeCachedService();

        $first = $service->listAll(TestUser::where('id', $alpha->id));
        $second = $service->listAll(TestUser::where('id', $beta->id));

        $this->assertSame('Alpha', $first->first()->name);
        $this->assertSame('Beta', $second->first()->name);
    }
}

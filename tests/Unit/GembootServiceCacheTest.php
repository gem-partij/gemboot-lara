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
}

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
        // Store file tidak mendukung tag. Sebelumnya env('CACHE_DRIVER') bernilai
        // null pada Laravel 11+, sehingga tags() tetap dipanggil dan melempar exception.
        config()->set('cache.default', 'file');
        cache()->flush();
        $user = TestUser::factory()->create();

        $found = $this->makeCachedService()->findOrFail($user->id);

        $this->assertSame($user->id, $found->id);
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

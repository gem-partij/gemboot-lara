<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Services\TestUserService;
use Gemboot\Tests\Observers\TestUserCachingObserver;

class GembootCachingObserverTest extends TestCase
{

    function test_tags_match_the_tags_used_by_service()
    {
        // flush() only works when the observer's tags match the tags CoreService
        // uses when storing cache entries.
        $this->assertSame(
            (new TestUserService)->getCacheTags('gemboot_test_user'),
            (new TestUserCachingObserver)->tags()
        );
    }

    function test_save_flushes_cached_data_on_store_with_tags()
    {
        config()->set('cache.default', 'array');
        TestUser::observe(TestUserCachingObserver::class);
        $user = TestUser::factory()->create(['name' => 'Before']);
        $service = (new TestUserService)->setObserver(new TestUserCachingObserver);

        $this->assertSame('Before', $service->findOrFail($user->id)->name);

        $user->update(['name' => 'After']);

        $service = (new TestUserService)->setObserver(new TestUserCachingObserver);
        $this->assertSame('After', $service->findOrFail($user->id)->name);
    }

    function test_save_and_delete_succeed_on_store_without_tags()
    {
        // Previously the observer called a global getCacheTags() function that does
        // not exist, and tags() threw on the file store.
        config()->set('cache.default', 'file');
        TestUser::observe(TestUserCachingObserver::class);

        $user = TestUser::factory()->create(['name' => 'Before']);
        $user->update(['name' => 'After']);
        $user->delete();

        $this->assertNull(TestUser::find($user->id));
    }
}

<?php
namespace Gemboot\Tests\Observers;

use Gemboot\Observers\CoreEloquentCachingObserver;

class TestUserCachingObserver extends CoreEloquentCachingObserver
{
    protected $cacheTag = 'gemboot_test_user';

    public function tags()
    {
        return $this->getTags();
    }
}

<?php

namespace Gemboot\Tests\Controllers;

class TestUserPolicySharedCacheController extends TestUserPolicyController
{
    protected $cache_seconds = ['index' => 0, 'show' => 60];

    // Share cached records between users on purpose, to prove the policy still runs.
    protected function cacheScope()
    {
        return null;
    }
}

<?php
namespace Gemboot\Tests\Services;

use Gemboot\Tests\Models\TestUser;

/**
 * Lists only the logged-in user's own record, scoped inside the service
 * (the case a shared cache key would leak between users).
 */
class TestUserScopedService extends TestUserService
{
    protected function getQueryListAll($model = null, $disable_search = false)
    {
        return parent::getQueryListAll(TestUser::where('id', auth()->id()), $disable_search);
    }
}

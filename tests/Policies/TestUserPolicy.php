<?php

namespace Gemboot\Tests\Policies;

use Gemboot\Tests\Models\TestUser;

/**
 * Users may see and change only their own record; only user 1 may create;
 * nobody may delete.
 */
class TestUserPolicy
{
    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, TestUser $model): bool
    {
        return (int) $user->id === (int) $model->id;
    }

    public function create($user): bool
    {
        return (int) $user->id === 1;
    }

    public function update($user, TestUser $model): bool
    {
        return (int) $user->id === (int) $model->id;
    }

    public function delete($user, TestUser $model): bool
    {
        return false;
    }
}

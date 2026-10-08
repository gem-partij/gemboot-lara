<?php

namespace Gemboot\Tests\Controllers;

use Gemboot\Tests\Requests\StoreTestUserRequest;
use Gemboot\Tests\Requests\UpdateTestUserRequest;

class TestUserFormRequestController extends TestUserController
{
    protected $storeRequest = StoreTestUserRequest::class;
    protected $updateRequest = UpdateTestUserRequest::class;

    /** The class of the request each hook received. */
    public static array $hookRequests = [];

    protected function beforeStoreHooks($request)
    {
        static::$hookRequests[] = get_class($request);
    }

    protected function beforeUpdateHooks($request, $id)
    {
        static::$hookRequests[] = get_class($request);
    }
}

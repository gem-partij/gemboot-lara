<?php

namespace Gemboot\Tests\Controllers;

use Gemboot\Tests\Requests\StoreTestUserRequest;

class TestUserFormRequestValidatedOnlyController extends TestUserController
{
    protected $storeRequest = StoreTestUserRequest::class;
    protected $saveValidatedOnly = true;
}

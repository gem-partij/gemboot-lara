<?php

namespace Gemboot\Tests\Controllers;

use Illuminate\Http\Request;

class TestUserNotAFormRequestController extends TestUserController
{
    protected $storeRequest = Request::class;
}

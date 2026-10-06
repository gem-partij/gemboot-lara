<?php

namespace Gemboot\Tests\Controllers;

class TestUserValidatingController extends TestUserController
{
    // log_access() is not defined in the test app; this must not cause a fatal error.
    protected $logAccessTag = 'users';

    protected function validateStoreRequest($request)
    {
        return \Validator::make($request->all(), ['email' => 'required']);
    }
}

<?php

namespace Gemboot\Tests\Controllers;

class TestUserValidatedOnlyController extends TestUserController
{
    protected $saveValidatedOnly = true;

    // Fixed values are still merged in, even though they have no rule.
    protected $merge_store_data_with = ['email_verified_at' => '2026-01-01 00:00:00'];

    protected function validateStoreRequest($request)
    {
        return \Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
        ]);
    }

    protected function validateUpdateRequest($request, $id)
    {
        return \Validator::make($request->all(), ['name' => 'sometimes']);
    }
}

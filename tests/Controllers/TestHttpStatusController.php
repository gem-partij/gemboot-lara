<?php

namespace Gemboot\Tests\Controllers;

use Gemboot\Controllers\CoreRestResourceController as GembootController;
use Illuminate\Http\Request;

use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Services\TestUserService;

class TestHttpStatusController extends GembootController
{

    public function __construct(TestUser $model, TestUserService $service)
    {
        parent::__construct($model, $service);
    }

    public function test500Exception(Request $request)
    {
        return $this->responseSuccessOrException(function () {
            throw new \Exception("TEST 500 EXCEPTION");
            // return [];
        });
    }
}

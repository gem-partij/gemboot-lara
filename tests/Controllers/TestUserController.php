<?php

namespace Gemboot\Tests\Controllers;

use Gemboot\Controllers\CoreRestResourceController as GembootController;
use Illuminate\Http\Request;

use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Services\TestUserService;

class TestUserController extends GembootController
{

    public function __construct(TestUser $model, TestUserService $service)
    {
        parent::__construct($model, $service);
    }

}

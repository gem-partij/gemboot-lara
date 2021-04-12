<?php

namespace Gemboot\Tests\Controllers;

use Gemboot\Controllers\CoreRestController as GembootController;
use Illuminate\Http\Request;

use Gemboot\Tests\Models\TestUser;

class TestUserController extends GembootController
{

    public function __construct(TestUser $model)
    {
        parent::__construct($model);
    }

    public function index() {
        return TestUser::all();
    }

}

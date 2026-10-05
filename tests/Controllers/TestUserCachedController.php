<?php

namespace Gemboot\Tests\Controllers;

class TestUserCachedController extends TestUserController
{
    protected $cache_seconds = [
        'index' => 60,
        'show' => 60,
    ];
}

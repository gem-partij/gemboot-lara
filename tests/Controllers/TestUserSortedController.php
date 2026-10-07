<?php

namespace Gemboot\Tests\Controllers;

class TestUserSortedController extends TestUserController
{
    protected $orderBy = ['name' => 'desc'];
}

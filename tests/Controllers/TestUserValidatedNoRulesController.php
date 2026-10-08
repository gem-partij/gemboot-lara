<?php

namespace Gemboot\Tests\Controllers;

class TestUserValidatedNoRulesController extends TestUserController
{
    // Turned on, but validateStoreRequest() still has no rules.
    protected $saveValidatedOnly = true;
}

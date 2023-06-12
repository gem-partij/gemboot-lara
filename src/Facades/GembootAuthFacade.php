<?php

namespace Gemboot\Facades;

use Illuminate\Support\Facades\Facade;

class GembootAuthFacade extends Facade
{

    protected static function getFacadeAccessor()
    {
        return 'gemboot-auth';
    }
}

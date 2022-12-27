<?php

namespace Gemboot\Facades;

use Illuminate\Support\Facades\Facade;

class GembootPermissionFacade extends Facade
{

    protected static function getFacadeAccessor()
    {
        return 'gemboot-permission';
    }
}

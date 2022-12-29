<?php

namespace Gemboot\Facades;

use Illuminate\Support\Facades\Facade;

class GembootValidatorFacade extends Facade
{

    protected static function getFacadeAccessor()
    {
        return 'gemboot-validator';
    }
}

<?php
namespace Gemboot\Facades;

use Illuminate\Support\Facades\Facade;

class GembootRequestFacade extends Facade {

    protected static function getFacadeAccessor()
    {
        return 'gemboot-request';
    }

}

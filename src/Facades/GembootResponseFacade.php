<?php
namespace Gemboot\Facades;

use Illuminate\Support\Facades\Facade;

class GembootResponseFacade extends Facade {

    protected static function getFacadeAccessor()
    {
        return 'gemboot-response';
    }

}

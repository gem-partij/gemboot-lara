<?php
namespace Gemboot\Controllers;

use Gemboot\Controllers\CoreRestController;
use Gemboot\Models\CoreModel;
use Gemboot\Services\CoreService;

abstract class CoreRestProxyController extends CoreRestController
{

    public function __construct(CoreModel $model = null, CoreService $service = null)
    {
        if (is_null($service)) {
            $service = new CoreService($model, $this->with, $this->orderBy);
        }

        parent::__construct($model, $service);
    }


    public function __call($name, $arguments)
    {
        if(! empty($this->service)) {
            return $this->service->{$name}(...$arguments);
        }
    }

}

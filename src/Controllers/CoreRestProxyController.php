<?php
namespace Gemboot\Controllers;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Gemboot\Controllers\CoreRestController;
use Gemboot\Models\CoreModel;
use Gemboot\Services\CoreService;

abstract class CoreRestProxyController extends CoreRestController
{

    public function __construct(?Eloquent $model = null, ?CoreService $service = null)
    {
        // CoreService needs a model; without one the parent leaves the service unset.
        if (is_null($service) && !is_null($model)) {
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

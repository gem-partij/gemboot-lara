<?php
namespace Gemboot\Controllers;

use Gemboot\Controllers\CoreController;
use Illuminate\Database\Eloquent\Model as Eloquent;

use Gemboot\Models\CoreModel;
use Gemboot\Services\CoreService;
use Gemboot\Traits\JSONResponses;

abstract class CoreRestController extends CoreController
{
    use JSONResponses;

    protected $model;
    protected $with = [];
    protected $orderBy = [];

    protected $service;
    protected $response;

    protected $modelPrimaryKeyName = "";
    protected $modelTableName = "";

    protected $addWithOnShow = true;

    protected $logAccessTag = "";

    public function __construct(Eloquent $model = null, CoreService $service = null)
    {
        if(! is_null($model)) {
            $this->model = $model;

            if (is_null($service)) {
                $this->service = new CoreService($model, $this->with, $this->orderBy);
            } else {
                $this->service = $service;
                $this->service->setWith($this->with);
                $this->service->setOrderBy($this->orderBy);
            }

            $this->modelPrimaryKeyName = $model->getKeyName();
            $this->modelTableName = $model->getTable();
        }

    }


    /**
     * =========================
     * VALIDATORS
     * ---------
    **/
    protected function validateStoreRequest($request)
    {
        return \Validator::make($request->all(), [
        ]);
    }

    protected function validateUpdateRequest($request, $id)
    {
        return \Validator::make($request->all(), [
        ]);
    }
}

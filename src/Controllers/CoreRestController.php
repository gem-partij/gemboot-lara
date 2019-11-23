<?php
namespace Gemboot\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model as Eloquent;

use Gemboot\Models\CoreModel;
use Gemboot\Services\CoreService;
use Gemboot\Traits\JSONResponses;

abstract class CoreRestController extends Controller
{
    use JSONResponses;

    // protected $repo;
    //
    // public function __construct(CoreRepository $repo) {
    //     $this->repo = $repo;
    // }

    protected $model;
    protected $with = [];
    protected $orderBy = [];

    protected $service;
    protected $response;

    protected $modelPrimaryKeyName = "";
    protected $modelTableName = "";

    protected $addWithOnShow = true;

    protected $logAccessTag = "";

    public function __construct(CoreModel $model = null, CoreService $service = null)
    {
        $this->model = $model;

        if (is_null($service)) {
            $this->service = new CoreService($model, $this->with, $this->orderBy);
        } else {
            $this->service = $service;
            $this->service->setWith($this->with);
            $this->service->setOrderBy($this->orderBy);
        }

        if (! is_null($model)) {
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

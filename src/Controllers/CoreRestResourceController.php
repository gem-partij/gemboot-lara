<?php
namespace Gemboot\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Cache;

use Gemboot\Controllers\CoreRestController;
use Gemboot\Models\CoreModel;
use Gemboot\Services\CoreService;
use Gemboot\Contracts\ApiResourceControllerContract as ResourceContract;

abstract class CoreRestResourceController extends CoreRestController implements ResourceContract
{
    protected $merge_store_data_with = [];
    protected $merge_update_data_with = [];

    protected $cache_seconds = [
        'index' => 0, // default 0 seconds
        'show' => 0, // default 0 seconds
    ];

    public function __construct(CoreModel $model = null, CoreService $service = null)
    {
        if (is_null($service)) {
            $service = new CoreService($model, $this->with, $this->orderBy);
        }

        parent::__construct($model, $service);
    }


    /**
     * Display a listing of the resource.
     *
     * @bodyParam search string Add to search query
     * @bodyParam search_field string Search field. Defaults to 'id'
     * @bodyParam order string Order by. Defaults to 'id'
     * @bodyParam atoz string Order by asc or desc. Defaults to 'asc'
     * @bodyParam page_len int Page length. Defaults to 30
     * @response 200 {
     *  "current_page": 1,
     *  "data": [
     *      {
     *          "id": 1,
     *          "foo": "bar"
     *      }
     *  ],
     *  "first_page_url": "http://localhost:8000/api/users?page=1",
     *  "from": 1,
     *  "last_page": 1,
     *  "last_page_url": "http://localhost:8000/api/users?page=1",
     *  "next_page_url": null,
     *  "path": "http://localhost:8000/api/users",
     *  "per_page": 30,
     *  "prev_page_url": null,
     *  "to": 1,
     *  "total": 1
     * }
     * @response 500 {"status":500, "message":"Server Error!", "data":{"error":"Error message"}}
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->responseSuccessOrException(function () {
            if ($this->cache_seconds['index'] > 0) {
                $cache_key = $this->modelTableName.'_index_'.implode('_', request()->all());
                $cache_seconds = $this->cache_seconds['index'];

                return Cache::remember($cache_key, $cache_seconds, function () {
                    return $this->service->listAll();
                });
            } else {
                return $this->service->listAll();
            }
        });
    }

    /**
     * Store a newly created resource in storage.
     *
     * @authenticated
     * @response 200 {"status": 200, "message": "", "data": {"saved":1}}
     * @response 500 {"status":500, "message":"Server Error!", "data":{"error":"Error message"}}
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        \DB::beginTransaction();
        try {
            $validator = $this->validateStoreRequest($request);

            if ($validator->fails()) {
                return $this->responseBadRequest([
                    'errors' => $validator->errors(),
                ]);
            }

            $before_store_resp = $this->beforeStoreHooks($request);

            // jika before store tidak return apa-apa
            if (is_null($before_store_resp)) {
                $saved_data = $this->service->store($request->all(), $this->merge_store_data_with);
            } else {
                $saved_data = $before_store_resp;
            }

            $after_store_resp = $this->afterStoreHooks($saved_data, $request);

            \DB::commit();

            $after_store_commit_resp = $this->afterStoreCommitHooks($saved_data, $request);

            return $this->responseSuccessOrException(function() use ($saved_data) {
                return ['saved' => $saved_data];
            });
        } catch (\Exception $e) {
            \DB::rollback();
            return $this->responseException($e);
        }
    }

    /**
     * Display the specified resource.
     *
     * @response 200 {
     *  "id": 1,
     *  "foo": "bar"
     * }
     * @response 400 {"status":400, "message":"Bad Request!", "data":{"error":"ID Not Found!"}}
     * @response 500 {"status":500, "message":"Server Error!", "data":{"error":"Error message"}}
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            return $this->responseSuccessOrException(function() use ($id) {
                if ($this->cache_seconds['show'] > 0) {
                    $cache_key = $this->modelTableName.'_show_'.implode('_', request()->all());
                    $cache_seconds = $this->cache_seconds['show'];

                    return Cache::remember($cache_key, $cache_seconds, function () use ($id) {
                        return $this->service->findOrFail($id, $this->addWithOnShow);
                    });
                } else {
                    return $this->service->findOrFail($id, $this->addWithOnShow);
                }
            });
        } catch (ModelNotFoundException $e) {
            return $this->responseNotFound([
                'error' => 'ID:'.$id.' Not Found!'
            ]);
        } catch (\Exception $e) {
            return $this->responseException($e);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @authenticated
     * @response 200 {"status": 200, "message": "", "data": {"saved":1}}
     * @response 400 {"status":400, "message":"Bad Request!", "data":{"error":"ID Not Found!"}}
     * @response 500 {"status":500, "message":"Server Error!", "data":{"error":"Error message"}}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        \DB::beginTransaction();
        try {
            $validator = $this->validateUpdateRequest($request, $id);

            if ($validator->fails()) {
                return $this->responseBadRequest([
                    'errors' => $validator->errors(),
                ]);
            }

            $before_update_resp = $this->beforeUpdateHooks($request, $id);

            // jika before store tidak return apa-apa
            if (is_null($before_update_resp)) {
                $saved_data = $this->service->update($request->all(), $id, $this->merge_update_data_with);
            } else {
                $saved_data = $before_update_resp;
            }

            $after_update_resp = $this->afterUpdateHooks($saved_data, $request, $id);

            \DB::commit();

            $after_update_commit_resp = $this->afterUpdateCommitHooks($saved_data, $request, $id);

            return $this->responseSuccessOrException(function() use ($saved_data) {
                return ['saved' => $saved_data];
            });
        } catch (ModelNotFoundException $e) {
            \DB::rollback();
            return $this->responseNotFound([
                'error' => 'ID:'.$id.' Not Found!'
            ]);
        } catch (\Exception $e) {
            \DB::rollback();
            return $this->responseException($e);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @authenticated
     * @response 200 {"status": 200, "message": "", "data": {"deleted":1}}
     * @response 400 {"status":400, "message":"Bad Request!", "data":{"error":"ID Not Found!"}}
     * @response 500 {"status":500, "message":"Server Error!", "data":{"error":"Error message"}}
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $data = $this->service->delete($id);

            return $this->responseSuccessOrException(function() {
                return ['deleted' => $data];
            });
        } catch (ModelNotFoundException $e) {
            return $this->responseNotFound([
                'error' => 'ID:'.$id.' Not Found!'
            ]);
        } catch (\Exception $e) {
            return $this->responseException($e);
        }
    }


    /**
     * =========================
     * HOOKS
     * ---------
    **/
    protected function beforeStoreHooks($request)
    {
    }

    protected function afterStoreHooks($savedData, $request)
    {
    }

    protected function afterStoreCommitHooks($savedData, $request)
    {
    }

    protected function beforeUpdateHooks($request, $id)
    {
    }

    protected function afterUpdateHooks($savedData, $request, $id)
    {
    }

    protected function afterUpdateCommitHooks($savedData, $request)
    {
    }
}

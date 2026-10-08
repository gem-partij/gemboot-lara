<?php
namespace Gemboot\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Http\Request;
use Gemboot\Middleware\TokenValidated;
use Gemboot\SSO\Auth\SSOUser;
use Illuminate\Support\Facades\Gate;
use Cache;

use Throwable;
use Gemboot\Exceptions\BadRequestException;
use Gemboot\Exceptions\UnauthorizedException;
use Gemboot\Exceptions\ForbiddenException;
use Gemboot\Exceptions\NotFoundException;
use Gemboot\Exceptions\ServerErrorException;

use Gemboot\Controllers\CoreRestController;
use Gemboot\Models\CoreModel;
use Gemboot\Services\CoreService;
use Gemboot\Contracts\ApiResourceControllerInterface as ResourceContract;

abstract class CoreRestResourceController extends CoreRestController implements ResourceContract
{
    protected $merge_store_data_with = [];
    protected $merge_update_data_with = [];

    /**
     * Check the model's Laravel policy on every action (viewAny, view, create,
     * update, delete). Off by default; turn it on per controller.
     */
    protected $authorizeWithPolicy = false;

    /**
     * Save only the fields that have a validation rule (validateStoreRequest(),
     * validateUpdateRequest()), not everything the client sent. Off by default.
     */
    protected $saveValidatedOnly = false;

    protected $cache_seconds = [
        'index' => 0, // default 0 seconds
        'show' => 0, // default 0 seconds
    ];

    public function __construct(?Eloquent $model = null, ?CoreService $service = null)
    {
        // CoreService needs a model; without one the parent leaves the service unset.
        if (is_null($service) && !is_null($model)) {
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
            $this->authorizeAction('viewAny', $this->model ? get_class($this->model) : null);

            if ($this->cache_seconds['index'] > 0) {
                // Key from the sorted query string only. implode() over request()->all()
                // dropped parameter names and failed on nested arrays such as user_login.
                $query = $this->queryForCacheKey();
                $cache_key = $this->modelTableName . '_index_' . sha1(json_encode([$query, $this->cacheScope()]));
                $cache_seconds = $this->cache_seconds['index'];

                return $this->controllerCache()->remember($cache_key, $cache_seconds, function () {
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
                // Close the transaction opened above before returning.
                \DB::rollback();
                return $this->responseBadRequest([
                    'errors' => $validator->errors(),
                ]);
            }

            $this->authorizeAction('create', $this->model ? get_class($this->model) : null);

            $before_store_resp = $this->beforeStoreHooks($request);

            // jika before store tidak return apa-apa
            if (is_null($before_store_resp)) {
                $saved_data = $this->service->store($this->requestDataToSave($request, $validator, 'validateStoreRequest'), $this->merge_store_data_with);
            } else {
                $saved_data = $before_store_resp;
            }

            $after_store_resp = $this->afterStoreHooks($saved_data, $request);

            \DB::commit();

            $after_store_commit_resp = $this->afterStoreCommitHooks($saved_data, $request);

            return $this->responseSuccess([
                'saved' => $saved_data
            ]);
        } catch (Throwable $e) {
            \DB::rollback();
            return $this->handleException($e);
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
            return $this->responseSuccessOrException(function () use ($id) {
                if ($this->cache_seconds['show'] > 0) {
                    // The key must include $id, otherwise every record shares one entry.
                    $query = $this->queryForCacheKey();
                    $cache_key = $this->modelTableName . '_show_' . sha1(json_encode([$id, $query, $this->cacheScope()]));
                    $cache_seconds = $this->cache_seconds['show'];

                    $record = $this->controllerCache()->remember($cache_key, $cache_seconds, function () use ($id) {
                        return $this->service->findOrFail($id, $this->addWithOnShow);
                    });
                } else {
                    $record = $this->service->findOrFail($id, $this->addWithOnShow);
                }

                // Also for cached records: caching must never skip authorization.
                $this->authorizeAction('view', $record);

                return $record;
            });
        } catch (Throwable $e) {
            return $this->handleException($e);
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
                // Close the transaction opened above before returning.
                \DB::rollback();
                return $this->responseBadRequest([
                    'errors' => $validator->errors(),
                ]);
            }

            if ($this->authorizeWithPolicy) {
                $this->authorizeAction('update', $this->service->findOrFail($id, false));
            }

            $before_update_resp = $this->beforeUpdateHooks($request, $id);

            // jika before store tidak return apa-apa
            if (is_null($before_update_resp)) {
                $saved_data = $this->service->update($this->requestDataToSave($request, $validator, 'validateUpdateRequest'), $id, $this->merge_update_data_with);
            } else {
                $saved_data = $before_update_resp;
            }

            $after_update_resp = $this->afterUpdateHooks($saved_data, $request, $id);

            \DB::commit();

            $after_update_commit_resp = $this->afterUpdateCommitHooks($saved_data, $request, $id);

            return $this->responseSuccess([
                'saved' => $saved_data
            ]);
        } catch (Throwable $e) {
            \DB::rollback();
            return $this->handleException($e);
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
            if ($this->authorizeWithPolicy) {
                $this->authorizeAction('delete', $this->service->findOrFail($id, false));
            }

            $data = $this->service->delete($id);

            return $this->responseSuccess([
                'deleted' => $data
            ]);
        } catch (Throwable $e) {
            \DB::rollback();
            return $this->handleException($e);
        }
    }


    /**
     * =========================
     * HOOKS
     * ---------
     *
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

    /**
     * Request input for store()/update(), without the user that TokenValidated
     * merged in as "user_login" (it is not a column of the model).
     */
    private function requestDataToSave(Request $request, $validator, string $rulesMethod): array
    {
        if ($this->saveValidatedOnly) {
            // Fail closed: without rules nothing could be saved, which would only
            // surface as data silently not being stored.
            if (!$validator || $validator->getRules() === []) {
                throw new \LogicException(sprintf(
                    '%s has $saveValidatedOnly = true, but %s() returns no validation rules, so nothing could be saved.',
                    static::class,
                    $rulesMethod
                ));
            }

            return $validator->validated();
        }

        return $request->attributes->get(TokenValidated::USER_LOGIN_MERGED)
            ? $request->except('user_login')
            : $request->all();
    }

    /**
     * Who a cached index/show result belongs to. Per logged-in user by default,
     * since a controller or its service may scope queries to the current user.
     * Return null to share cached results between all users.
     */
    protected function cacheScope()
    {
        return auth()->id();
    }

    /**
     * Cache for index/show. On stores with tag support the entries carry the same
     * tags as CoreService, so CoreEloquentCachingObserver clears them on save and
     * delete. Other stores keep a plain cache that expires after cache_seconds.
     */
    private function controllerCache()
    {
        return Cache::supportsTags()
            ? Cache::tags([$this->modelTableName, $this->modelTableName . '-addwith'])
            : Cache::store();
    }

    /**
     * Sorted query string for cache keys. It keeps the "user_login" merged by
     * TokenValidated, so entries stay per user: a controller or service may scope
     * its query to the current user, and a shared key would leak data between users.
     */
    private function queryForCacheKey(): array
    {
        $query = request()->query();
        ksort($query);

        return $query;
    }

    /**
     * The user that policies receive: the Laravel guard's user, or else the user
     * TokenValidated merged into the request, wrapped as an SSOUser (attributes
     * readable as $user->id, $user->name, ...). Override to use your own model.
     */
    protected function policyUser()
    {
        if ($user = auth()->user()) {
            return $user;
        }

        $login = request()->attributes->get(TokenValidated::USER_LOGIN_MERGED) ? request('user_login') : null;

        return is_array($login) ? new SSOUser($login) : null;
    }

    /**
     * Check the model's policy for an action when $authorizeWithPolicy is on.
     * A denial becomes a ForbiddenException, i.e. a 403 in the Gemboot format.
     *
     * Fails closed: with the flag on but no policy found (wrong namespace, typo),
     * the request is refused with a 500 and a logged LogicException, instead of
     * silently skipping the checks the developer asked for.
     */
    private function authorizeAction(string $ability, $target): void
    {
        if (!$this->authorizeWithPolicy) {
            return;
        }

        if ($this->model === null || Gate::getPolicyFor($this->model) === null) {
            throw new \LogicException(sprintf(
                '%s has $authorizeWithPolicy = true, but no policy is registered for %s.',
                static::class,
                $this->model ? get_class($this->model) : 'its model (none set)'
            ));
        }

        $response = Gate::forUser($this->policyUser())->inspect($ability, $target);

        if ($response->denied()) {
            throw new ForbiddenException($response->message() ?: 'This action is unauthorized.');
        }
    }
}

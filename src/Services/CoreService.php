<?php

namespace Gemboot\Services;

use Gemboot\Contracts\CoreServiceInterface as CoreServiceContract;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Gemboot\Models\CoreModel;
use Gemboot\Traits\GembootHelpers;
use Gemboot\Observers\CoreEloquentCachingObserver;
use Gemboot\Exceptions\BadRequestException;

class CoreService implements CoreServiceContract
{
    use GembootHelpers;

    protected $model;
    protected $with = [];
    protected $orderBy = [];

    protected $modelPrimaryKeyName = "";
    protected $modelTableName = "";
    protected $modelDbConnection = "";
    protected $modelDbDriver = "mysql";

    protected $defaultCacheLifetime = 60 * 60 * 24;
    protected $cacheKeyPrefix = "";
    protected $cacheKeyPostfix = "";
    protected $observer = null;

    public function __construct(Eloquent $model, $with = [], $orderBy = [])
    {
        $this->model = $model;
        $this->setWith($with);
        $this->setOrderBy($orderBy);

        $this->modelPrimaryKeyName = $model->getKeyName();
        $this->modelTableName = $model->getTable();
        $this->modelDbConnection = $model->getConnectionName();
        $this->modelDbDriver = config("database.connections.$this->modelDbConnection.driver");
    }

    public function setWith($with)
    {
        $this->with = $with;
        return $this;
    }

    public function setOrderBy($orderBy)
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    public function setObserver(CoreEloquentCachingObserver $observer)
    {
        $this->observer = $observer;
        return $this;
    }

    public function setDefaultCacheLifetime($lifetime)
    {
        $lifetime = (int) $lifetime;
        $this->defaultCacheLifetime = $lifetime;
        return $this;
    }

    public function getModelPrimaryKeyName()
    {
        $this->modelPrimaryKeyName = $this->model->getKeyName();
        return $this->modelPrimaryKeyName;
    }

    public function getModelTableName()
    {
        $this->modelTableName = $this->model->getTable();
        return $this->modelTableName;
    }

    public function generateCacheKey($main_name, $prefix = null, $postfix = null)
    {
        if (empty($prefix)) {
            $prefix = strtolower(request()->method() . request()->path());
        }

        if (empty($postfix)) {
            // Hash of the sorted query string only. The full request input put request
            // bodies (passwords on update) into key names, gave unbounded key counts,
            // and produced keys over Memcached's 250-character limit.
            // On GET requests the user merged in by TokenValidated is part of the
            // query, so entries stay per user. Keep it that way: services often scope
            // queries to the current user, and a shared key would leak data between users.
            $query = request()->query();
            ksort($query);
            $postfix = sha1(json_encode($query));
        }

        return $prefix . "-" . $main_name . "-" . $postfix;
    }

    /**
     * Get a listing of the resource.
     **/
    public function listAll($model = null, $disable_search = false)
    {
        try {
            // Caching requires a store with tag support (redis, memcached, array).
            // Without tags the observer cannot flush stale entries, so stores such as
            // file and database skip caching rather than serve stale data.
            if (!empty($this->observer) && cache()->supportsTags()) {
                // cache response
                $cacheKey = $this->getCacheKey($this->getModelTableName(), $this->generateCacheKey("listAll()"), 'group');
                $cacheTags = $this->getCacheTags($this->getModelTableName());

                // A query passed in (e.g. scoped to the current user) must be part of
                // the key, otherwise every caller shares the first caller's result.
                // Builders, relations, and models all answer toSql() and getBindings()
                // through __call(), so method_exists() would not find them.
                if (is_object($model)) {
                    $cacheKey .= '-' . sha1($model->toSql() . '|' . json_encode($model->getBindings()));
                }

                return cache()->tags($cacheTags)->remember($cacheKey, $this->defaultCacheLifetime, function () use ($model, $disable_search) {
                    return $this->getQueryListAll($model, $disable_search);
                });
            }

            // default response
            return $this->getQueryListAll($model, $disable_search);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Get a count of the resource.
     **/
    public function countAll($model = null, $disable_search = false)
    {
        try {
            $query = is_null($model) ? $this->freshModelQuery() : $model;
            $query = $this->generateModelSearch($query, $disable_search);

            return $query->count();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Store a newly created resource in storage.
     **/
    public function store($requestData, $merge_data_with = [])
    {
        $this->beforeStoreHooks($requestData, $merge_data_with);

        // Save a copy: filling and saving $this->model itself made a second store()
        // update the row created by the first. A clone keeps attributes preset on
        // the model given to the constructor.
        $data = ($this->model instanceof Eloquent && !$this->model->exists)
            ? clone $this->model
            : $this->model->newInstance();
        $data->fill(array_merge($requestData, $merge_data_with));
        $data->save();

        $this->afterStoreHooks($data, $requestData, $merge_data_with);

        return $data;
    }

    /**
     * Get the specified resource.
     **/
    public function findOrFail($id, $addWith = true)
    {
        $cacheKey = $this->getCacheKey($this->getModelTableName(), $this->generateCacheKey($id));
        $cacheTags = $this->getCacheTags($this->getModelTableName());

        $query = $this->freshModelQuery();
        if (!empty($this->with) && $addWith) {
            $query = $query->with($this->with);
            $cacheKey .= '-addwith';
        }

        // See listAll(): stores without tag support skip caching to avoid stale data.
        if (empty($this->observer) || !cache()->supportsTags()) {
            return $query->findOrFail($id);
        } else {
            return cache()->tags($cacheTags)->remember($cacheKey, $this->defaultCacheLifetime, function () use ($query, $id) {
                return $query->findOrFail($id);
            });
        }
    }

    /**
     * Get the specified resource.
     **/
    public function firstOrFail($model, $addWith = true)
    {
        $query = $model;

        if (!empty($this->with) && $addWith) {
            $query = $query->with($this->with);
        }

        return $query->firstOrFail();
    }

    /**
     * Update the specified resource in storage.
     **/
    public function update($requestData, $id, $merge_data_with = [])
    {
        $this->beforeUpdateHooks($requestData, $id, $merge_data_with);

        $data = $this->findOrFail($id, false);
        $data->fill(array_merge($requestData, $merge_data_with));
        $data->save();

        $this->afterUpdateHooks($data, $requestData, $id, $merge_data_with);

        return $data;
    }

    /**
     * Update the specified resource in storage.
     **/
    public function updateOrCreate($whereData, $requestData, $merge_data_with = [])
    {
        return $this->model->updateOrCreate($whereData, array_merge($requestData, $merge_data_with));
    }

    /**
     * Update the specified resource in storage use the model given.
     **/
    public function updateUseModel($model, $requestData, $merge_data_with = [])
    {
        $model->fill(array_merge($requestData, $merge_data_with));
        $model->save();
        return $model;
    }


    /**
     * Remove the specified resource from storage.
     **/
    public function delete($id)
    {
        $this->beforeDeleteHooks($id);

        $data = $this->findOrFail($id, false);
        $data->delete();

        $this->afterDeleteHooks($data, $id);

        return $data;
    }


    protected function generateModelSearch($model = null, $disable_search = false)
    {
        if (is_null($model)) {
            $model = $this->freshModelQuery();
        }

        $search = null;
        $search_field = null;
        $search_mode = null;
        $search_exact = false;
        $search_operator = null;
        if (request()->has('search') && !$disable_search) {
            $search = request('search');
            $search_field = request()->has('search_field') ? request('search_field') : '';
            $search_mode = request()->has('search_mode') ? request('search_mode') : 'or';
            $search_exact = false;
            if ($this->modelDbDriver == 'pgsql') {
                $search_operator = 'ILIKE';
            }
        } elseif (request()->has('search_exact') && !$disable_search) {
            $search = request('search_exact');
            $search_field = request()->has('search_field') ? request('search_field') : '';
            $search_mode = request()->has('search_mode') ? request('search_mode') : 'or';
            $search_exact = true;
        }

        if (!is_null($search)) {
            if (!is_array($search) && !is_array($search_field)) {
                if ($search_exact) {
                    $model = $model->searchExact($search, $search_field, $search_mode, $search_operator);
                } else {
                    $model = $model->search($search, $search_field, $search_mode, $search_operator);
                }
            } else {
                // support multiple search
                if (!is_array($search)) {
                    $search = [$search];
                }
                if (!is_array($search_field)) {
                    $search_field = [$search_field];
                }

                if ($search_exact) {
                    $model = $model->searchExactMultiple($search, $search_field, $search_mode, $search_operator);
                } else {
                    $model = $model->searchMultiple($search, $search_field, $search_mode, $search_operator);
                }
            }
        }

        return $model;
    }

    protected function generateModelOrder($model = null)
    {
        if (is_null($model)) {
            $model = $this->freshModelQuery();
        }

        if (request()->has('order')) {
            $order = request()->has('order') ? request('order') : $this->getModelPrimaryKeyName();
            $atoz = request()->has('atoz') ? request('atoz') : 'asc';

            // support multiple order by
            if (!is_array($order)) {
                $order = [$order];
            }
            if (!is_array($atoz)) {
                $atoz = [$atoz];
            }
            $hidden = $model instanceof Eloquent
                ? $model->getHidden()
                : (method_exists($model, 'getModel') ? $model->getModel()->getHidden() : []);

            foreach ($order as $i => $order_item) {
                $atoz_item = isset($atoz[$i]) ? $atoz[$i] : 'asc';

                // Sorting by a hidden column leaks its ordering; an invalid direction
                // used to surface as a 500 from orderBy().
                if (!is_string($order_item) || in_array($order_item, $hidden, true)) {
                    throw new BadRequestException('Invalid order field.');
                }
                if (!is_string($atoz_item) || !in_array(strtolower($atoz_item), ['asc', 'desc'], true)) {
                    throw new BadRequestException('Invalid sort direction.');
                }

                $model = $model->orderBy($order_item, $atoz_item);
            }
        }

        return $model;
    }

    /**
     * A new query for this call. Methods used to store their query in $this->model,
     * so filters and eager loads leaked into later calls on the same service
     * instance (and, under Octane, into other requests).
     */
    protected function freshModelQuery()
    {
        return $this->model instanceof Eloquent ? $this->model->newQuery() : clone $this->model;
    }

    /**
     * Page size from ?page_len, limited by gemboot.pagination.max_page_len.
     */
    protected function getPageLength(): int
    {
        $page_len = request('page_len');
        $page_len = is_numeric($page_len) && (int) $page_len > 0 ? (int) $page_len : 30;

        $max = config('gemboot.pagination.max_page_len', 1000);
        if (!is_null($max) && $max !== '' && (int) $max > 0) {
            $page_len = min($page_len, (int) $max);
        }

        return $page_len;
    }

    protected function getQueryListAll($model = null, $disable_search = false)
    {
        $query = is_null($model) ? $this->freshModelQuery() : $model;

        if (!empty($this->with)) {
            $query = $query->with($this->with);
        }

        $query = $this->generateModelSearch($query, $disable_search);
        $query = $this->generateModelOrder($query);

        if (request()->has('page_len') && request('page_len') == 'all') {
            // count first
            $count_data = $query->count();
            if ($count_data <= 1000) {
                return $query->get();
            }

            return $query->paginate(999);
        }

        return $query->paginate($this->getPageLength());
    }


    /**
     * =========================
     * HOOKS
     * ---------
     **/
    protected function beforeStoreHooks(&$requestData, &$merge_data_with)
    {
    }

    protected function afterStoreHooks(&$savedData, &$requestData, &$merge_data_with)
    {
    }

    protected function beforeUpdateHooks(&$requestData, &$id, &$merge_data_with)
    {
    }

    protected function afterUpdateHooks(&$savedData, &$requestData, &$id, &$merge_data_with)
    {
    }

    protected function beforeDeleteHooks(&$id)
    {
    }

    protected function afterDeleteHooks(&$deletedData, &$id)
    {
    }
}

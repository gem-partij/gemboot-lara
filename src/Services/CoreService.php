<?php
namespace Gemboot\Services;

use Gemboot\Contracts\CoreServiceInterface as CoreServiceContract;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Gemboot\Models\CoreModel;
use Gemboot\Traits\GembootHelpers;
use Gemboot\Observers\CoreEloquentCachingObserver;

class CoreService implements CoreServiceContract
{
    use GembootHelpers;

    protected $model;
    protected $with = [];
    protected $orderBy = [];

    protected $modelPrimaryKeyName = "";
    protected $modelTableName = "";

    protected $defaultCacheLifetime = 60*60*24;
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
            $prefix = strtolower(request()->method().request()->path());
        }

        if (empty($postfix)) {
            $postfix = json_encode(request()->all());
        }

        return $prefix."-".$main_name."-".$postfix;
    }

    /**
     * Get a listing of the resource.
    **/
    public function listAll($model = null, $disable_search = false)
    {
        try {
            if(! empty($this->observer)) {
                // cache response
                $cacheKey = $this->getCacheKey($this->getModelTableName(), $this->generateCacheKey("listAll()"), 'group');
                $cacheTags = $this->getCacheTags($this->getModelTableName());

                $cacheDriver = cache();
                if (env('CACHE_DRIVER') != 'file') {
                    $cacheDriver = $cacheDriver->tags($cacheTags);
                }

                return $cacheDriver->remember($cacheKey, $this->defaultCacheLifetime, function () use ($model, $disable_search) {
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
    public function countAll($model = null, $disable_search = false) {
        try {
            if (! is_null($model)) {
                $this->model = $model;
            }

            $this->model = $this->generateModelSearch($this->model, $disable_search);

            return $this->model->count();
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * Store a newly created resource in storage.
    **/
    public function store($requestData, $merge_data_with = [])
    {
        $this->beforeStoreHooks($requestData, $merge_data_with);

        $data = $this->model;
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

        if (! empty($this->with) && $addWith) {
            $this->model = $this->model->with($this->with);
            $cacheKey .= '-addwith';
        }

        if (empty($this->observer)) {
            return $this->model->findOrFail($id);
        } else {
            $cacheDriver = cache();
            if (env('CACHE_DRIVER') != 'file') {
                $cacheDriver = $cacheDriver->tags($cacheTags);
            }

            return $cacheDriver->remember($cacheKey, $this->defaultCacheLifetime, function () use ($id) {
                return $this->model->findOrFail($id);
            });
        }
    }

    /**
     * Get the specified resource.
    **/
    public function firstOrFail($model, $addWith = true)
    {
        $this->model = $model;

        if (! empty($this->with) && $addWith) {
            $this->model = $this->model->with($this->with);
        }

        return $this->model->firstOrFail();
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


    protected function generateModelSearch($model = null, $disable_search = false) {
        if (is_null($model)) {
            $model = $this->model;
        }

        $search = null;
        $search_field = null;
        $search_mode = null;
        $search_exact = false;
        if (request()->has('search') && !$disable_search) {
            $search = request('search');
            $search_field = request()->has('search_field') ? request('search_field') : '';
            $search_mode = request()->has('search_mode') ? request('search_mode') : 'or';
            $search_exact = false;
        } elseif (request()->has('search_exact') && !$disable_search) {
            $search = request('search_exact');
            $search_field = request()->has('search_field') ? request('search_field') : '';
            $search_mode = request()->has('search_mode') ? request('search_mode') : 'or';
            $search_exact = true;
        }

        if (! is_null($search)) {
            if (!is_array($search) && !is_array($search_field)) {
                if ($search_exact) {
                    $model = $model->searchExact($search, $search_field, $search_mode);
                } else {
                    $model = $model->search($search, $search_field, $search_mode);
                }
            } else {
                // support multiple search
                if (! is_array($search)) {
                    $search = [$search];
                }
                if (! is_array($search_field)) {
                    $search_field = [$search_field];
                }

                if ($search_exact) {
                    $model = $model->searchExactMultiple($search, $search_field, $search_mode);
                } else {
                    $model = $model->searchMultiple($search, $search_field, $search_mode);
                }
            }
        }

        return $model;
    }

    protected function generateModelOrder($model = null) {
        if (is_null($model)) {
            $model = $this->model;
        }

        if (request()->has('order')) {
            $order = request()->has('order') ? request('order') : $this->getModelPrimaryKeyName();
            $atoz = request()->has('atoz') ? request('atoz') : 'asc';

            // support multiple order by
            if (! is_array($order)) {
                $order = [$order];
            }
            if (! is_array($atoz)) {
                $atoz = [$atoz];
            }
            foreach ($order as $i => $order_item) {
                $atoz_item = isset($atoz[$i]) ? $atoz[$i] : 'asc';
                $model = $model->order($order_item, $atoz_item);
            }
        }

        return $model;
    }

    protected function getQueryListAll($model = null, $disable_search = false) {
        if (! is_null($model)) {
            $this->model = $model;
        }

        if (! empty($this->with)) {
            $this->model = $this->model->with($this->with);
        }

        $this->model = $this->generateModelSearch($this->model, $disable_search);
        $this->model = $this->generateModelOrder($this->model);

        if (request()->has('page_len') && request('page_len') == 'all') {
            // count first
            $count_data = $this->model->count();
            if($count_data <= 1000) {
                return $this->model->get();
            }

            return $this->model->paginate(999);
        }

        return $this->model->paginate(
            request()->has('page_len')
            ? request('page_len')
            : 30
        );
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

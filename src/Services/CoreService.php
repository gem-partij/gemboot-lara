<?php
namespace Gemboot\Services;

use Gemboot\Contracts\CoreServiceContract;
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

    /**
     * Get a listing of the resource.
    **/
    public function listAll($model = null, $disable_search = false)
    {
        try {
            if (! is_null($model)) {
                $this->model = $model;
            }

            if (! empty($this->with)) {
                $this->model = $this->model->with($this->with);
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
                        $this->model = $this->model->searchExact($search, $search_field, $search_mode);
                    } else {
                        $this->model = $this->model->search($search, $search_field, $search_mode);
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
                        $this->model = $this->model->searchExactMultiple($search, $search_field, $search_mode);
                    } else {
                        $this->model = $this->model->searchMultiple($search, $search_field, $search_mode);
                    }
                }
            }

            $order = request()->has('order') ? request('order') : $this->modelPrimaryKeyName;
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
                $this->model = $this->model->order($order_item, $atoz_item);
            }

            if (request()->has('page_len') && request('page_len') == 'all') {
                return $this->model->paginate(999);
            }

            if (empty($this->observer)) {
                return $this->model->paginate(
                    request()->has('page_len')
                    ? request('page_len')
                    : 30
                );
            } else {
                $cacheKey = $this->get_cache_key($this->modelTableName, "listAll()-".json_encode(request()->all()), 'group');
                $cacheTags = $this->get_cache_tags($this->modelTableName);

                $cacheDriver = cache();
                if (env('CACHE_DRIVER') != 'file') {
                    $cacheDriver = $cacheDriver->tags($cacheTags);
                }

                return $cacheDriver->remember($cacheKey, 60*60*24, function () {
                    return $this->model->paginate(
                        request()->has('page_len')
                        ? request('page_len')
                        : 30
                    );
                });
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Store a newly created resource in storage.
    **/
    public function store($requestData, $merge_data_with = [])
    {
        $data = $this->model;
        $data->fill(array_merge($requestData, $merge_data_with));
        $data->save();
        return $data;
    }

    /**
     * Get the specified resource.
    **/
    public function findOrFail($id, $addWith = true)
    {
        $cacheKey = $this->get_cache_key($this->modelTableName, $id);
        $cacheTags = $this->get_cache_tags($this->modelTableName);

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

            return $cacheDriver->remember($cacheKey, 60*60*24, function () use ($id) {
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
        $data = $this->findOrFail($id, false);
        $data->fill(array_merge($requestData, $merge_data_with));
        $data->save();
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
        $data = $this->findOrFail($id, false);
        $data->delete();
        return $data;
    }
}
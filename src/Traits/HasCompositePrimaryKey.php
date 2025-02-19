<?php

namespace Gemboot\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;

trait HasCompositePrimaryKey
{
    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        return !is_array($keys) ? parent::setKeysForSaveQuery($query) : $query->where(function ($q) use ($keys) {
            foreach ($keys as $key) {
                $q->where($key, '=', $this->getAttribute($key));
            }
        });
    }

    /**
     * Get the casts array.
     *
     * @return array
     */
    public function getCasts()
    {
        if ($this->getIncrementing()) {
            return array_merge([$this->getKeyName() => $this->getKeyType()], $this->casts);
        }
        return $this->casts;
    }

    /**
     * @return false
     */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        $fields = $this->getKeyName();
        $keys = [];
        array_map(function ($key) use (&$keys) {
            $keys[] = $this->getAttribute($key);
        }, $fields);
        return $keys;
    }

    /**
     * Finds model by primary keys
     *
     * @param array $ids
     * @return mixed
     */
    public static function find(array $ids)
    {
        $modelClass = get_called_class();
        $model = new $modelClass();
        $keys = $model->primaryKey;
        return $model->where(function ($query) use ($ids, $keys) {
            foreach ($keys as $idx => $key) {
                if (isset($ids[$idx])) {
                    $query->where($key, $ids[$idx]);
                } else {
                    $query->whereNull($key);
                }
            }
        })->first();
    }

    /**
     * Find model by primary key or throws ModelNotFoundException
     *
     * @param array $ids
     * @return mixed
     */
    public static function findOrFail(array $ids)
    {
        $modelClass = get_called_class();
        $model = new $modelClass();
        $record = $model->find($ids);
        if (!$record) {
            throw new ModelNotFoundException;
        }
        return $record;
    }

    /**
     * Get the primary key value for a save query.
     *
     * @param mixed $keyName
     * @return mixed
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }
}

<?php
namespace Gemboot\Traits;

use Gemboot\Contracts\CoreModelInterface as CoreModelContract;
use Illuminate\Database\Eloquent\Builder;

trait MainModelAbilities
{

    /**
     * =========================
     * PUBLIC METHODS
     * ---------
    **/
    public function getTableColumns()
    {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }


    /**
     * =========================
     * MAIN SCOPES
     * ---------
    **/
    /**
     * Scope a query to search data.
     * (using where like query)
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
    **/
    public function scopeSearch(Builder $query, $string, $field = '', $mode = 'or')
    {
        $arr_date_fields = ['created_at', 'updated_at', 'deleted_at'];

        $string_like = '%'.$string.'%';
        if (strpos($string, '%') !== false) {
            $string_like = $string;
        }

        if (! empty($field)) {
            if (in_array($field, $arr_date_fields)) {
                return $this->getQueryDateSearch($query, $string, $field);
            }

            if ($mode == 'or') {
                if (strpos($field, '.') !== false) {
                    $exploded = explode('.', $field);
                    return $this->getQueryOrWhereHas($query, $exploded[0], $exploded[1], 'LIKE', $string_like);
                } else {
                    return $query->orWhere($this->getTable().'.'.$field, 'LIKE', $string_like);
                }
            } else {
                if (strpos($field, '.') !== false) {
                    $exploded = explode('.', $field);
                    return $this->getQueryWhereHas($query, $exploded[0], $exploded[1], 'LIKE', $string_like);
                } else {
                    return $query->where($this->getTable().'.'.$field, 'LIKE', $string_like);
                }
            }
        } else {
            $primary = $this->getKeyName();
            $cols = $this->getTableColumns();

            return $query->where(function (Builder $q) use ($mode, $primary, $cols, $string_like, $arr_date_fields) {
                if ($mode == 'or') {
                    foreach (array_diff($cols, $arr_date_fields) as $col) {
                        if ($col !== $primary) {
                            $q->orWhere($this->getTable().'.'.$col, 'LIKE', $string_like);
                        }
                    }
                } else {
                    foreach (array_diff($cols, $arr_date_fields) as $col) {
                        if ($col !== $primary) {
                            $q->where($this->getTable().'.'.$col, 'LIKE', $string_like);
                        }
                    }
                }
            });
        }
    }

    /**
     * Scope a query to search data.
     * (using where equal query)
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
    **/
    public function scopeSearchExact(Builder $query, $string, $field = '', $mode = 'or')
    {
        $arr_date_fields = ['created_at', 'updated_at', 'deleted_at'];

        if (! empty($field)) {
            if (in_array($field, $arr_date_fields)) {
                return $this->getQueryDateSearch($query, $string, $field);
            }

            if ($mode == 'or') {
                if (strpos($field, '.') !== false) {
                    $exploded = explode('.', $field);
                    return $this->getQueryOrWhereHas($query, $exploded[0], $exploded[1], '=', $string);
                } else {
                    return $query->orWhere($this->getTable().'.'.$field, '=', $string);
                }
            } else {
                if (strpos($field, '.') !== false) {
                    $exploded = explode('.', $field);
                    return $this->getQueryWhereHas($query, $exploded[0], $exploded[1], '=', $string);
                } else {
                    return $query->where($this->getTable().'.'.$field, '=', $string);
                }
            }
        } else {
            $primary = $this->getKeyName();
            $cols = $this->getTableColumns();

            return $query->where(function (Builder $q) use ($mode, $primary, $cols, $string, $arr_date_fields) {
                if ($mode == 'or') {
                    foreach (array_diff($cols, $arr_date_fields) as $col) {
                        if ($col !== $primary) {
                            $q->orWhere($this->getTable().'.'.$col, '=', $string);
                        }
                    }
                } else {
                    foreach (array_diff($cols, $arr_date_fields) as $col) {
                        if ($col !== $primary) {
                            $q->where($this->getTable().'.'.$col, '=', $string);
                        }
                    }
                }
            });
        }
    }

    /**
     * Scope a query to search data.
     * (using where like query)
     * (and in bracket query)
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
    **/
    public function scopeSearchMultiple(Builder $query, $string = [], $field = [], $mode = 'or')
    {
        return $query->where(function (Builder $q) use ($mode, $string, $field) {
            foreach ($string as $i => $string_item) {
                if ($string_item != '') {
                    if (! isset($field[$i])) {
                        throw new \Exception("Please complete your search field!");
                    }
                    $field_item = $field[$i];

                    $string_like = '%'.$string_item.'%';
                    if (strpos($string_item, '%') !== false) {
                        $string_like = $string_item;
                    }

                    if ($mode == 'or') {
                        if (strpos($field_item, '.') !== false) {
                            $exploded = explode('.', $field_item);
                            $q = $this->getQueryOrWhereHas($q, $exploded[0], $exploded[1], 'LIKE', $string_like);
                        } else {
                            $q = $q->orWhere($this->getTable().'.'.$field_item, 'LIKE', $string_like);
                        }
                    } else {
                        if (strpos($field_item, '.') !== false) {
                            $exploded = explode('.', $field_item);
                            $q = $this->getQueryWhereHas($q, $exploded[0], $exploded[1], 'LIKE', $string_like);
                        } else {
                            $q = $q->where($this->getTable().'.'.$field_item, 'LIKE', $string_like);
                        }
                    }
                }
            }
        });
    }

    /**
     * Scope a query to search data.
     * (using where equal query)
     * (and in bracket query)
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
    **/
    public function scopeSearchExactMultiple(Builder $query, $string = [], $field = [], $mode = 'or')
    {
        return $query->where(function (Builder $q) use ($mode, $string, $field) {
            foreach ($string as $i => $string_item) {
                if ($string_item != '') {
                    if (! isset($field[$i])) {
                        throw new \Exception("Please complete your search field!");
                    }
                    $field_item = $field[$i];

                    if ($mode == 'or') {
                        if (strpos($field_item, '.') !== false) {
                            $exploded = explode('.', $field_item);
                            $q = $this->getQueryOrWhereHas($q, $exploded[0], $exploded[1], '=', $string);
                        } else {
                            $q = $q->orWhere($this->getTable().'.'.$field_item, '=', $string);
                        }
                    } else {
                        if (strpos($field_item, '.') !== false) {
                            $exploded = explode('.', $field_item);
                            $q = $this->getQueryWhereHas($q, $exploded[0], $exploded[1], '=', $string);
                        } else {
                            $q = $q->where($this->getTable().'.'.$field_item, '=', $string);
                        }
                    }
                }
            }
        });
    }

    /**
     * Scope a query to sort data.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
    **/
    public function scopeOrder(Builder $query, $field = '', $asc_or_desc = 'asc')
    {
        if (! empty($field)) {
            return $query->orderBy($this->getTable().'.'.$field, $asc_or_desc);
        } else {
            return $query->orderBy($this->getTable().'.'.$this->primaryKey, $asc_or_desc);
        }
    }

    /**
     * Scope a query to limit data.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
    **/
    public function scopePerPage(Builder $query, $limit = 30)
    {
        return $query->limit($limit);
    }


    /**
     * =========================
     * PROTECTED METHODS
     * ---------
    **/
    protected function getQueryDateSearch(Builder &$query, $search, $search_field)
    {
        $strtotime = strtotime($search);
        $year = date('Y', $strtotime);
        $month = date('m', $strtotime);

        $strlen = strlen($search);

        if ($strlen > 10) {
            return $query->whereDate($this->getTable().'.'.$search_field, substr($search, 0, 10));
        } else {
            switch ($strlen) {
                case 10:
                    return $query->whereDate($this->getTable().'.'.$search_field, $search);
                case 7:
                    return $query->whereYear($this->getTable().'.'.$search_field, $year)
                            ->whereMonth($this->getTable().'.'.$search_field, $month);
                case 4:
                    return $query->whereYear($this->getTable().'.'.$search_field, $year);
                default:
                    return $query;
            }
        }
    }

    protected function getQueryWhereHas(Builder &$query, $relation_name, $col_name, $operator, $string_search)
    {
        return $query->whereHas($relation_name, function (Builder $q) use ($col_name, $operator, $string_search) {
            $q->where($col_name, $operator, $string_search);
        });
    }

    protected function getQueryOrWhereHas(Builder &$query, $relation_name, $col_name, $operator, $string_search)
    {
        return $query->orWhereHas($relation_name, function (Builder $q) use ($col_name, $operator, $string_search) {
            $q->where($col_name, $operator, $string_search);
        });
    }
}

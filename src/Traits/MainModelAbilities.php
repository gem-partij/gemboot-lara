<?php

namespace Gemboot\Traits;

use Gemboot\Contracts\CoreModelInterface as CoreModelContract;
use Gemboot\Exceptions\BadRequestException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionMethod;
use ReflectionNamedType;

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
    public function scopeSearch(Builder $query, $string, $field = '', $mode = 'or', $operator = 'LIKE')
    {
        if (empty($operator)) {
            $operator = 'LIKE';
        }
        $arr_date_fields = ['created_at', 'updated_at', 'deleted_at'];

        $string_like = '%' . $string . '%';
        if (strpos($string, '%') !== false) {
            $string_like = $string;
        }

        if (!empty($field)) {
            if (in_array($field, $arr_date_fields)) {
                return $this->getQueryDateSearch($query, $string, $field);
            }

            if ($mode == 'or') {
                if (strpos($field, '.') !== false) {
                    $exploded = explode('.', $field);
                    return $this->getQueryOrWhereHas($query, $exploded[0], $exploded[1], $operator, $string_like);
                } else {
                    $this->assertSearchableColumn($field);
                    return $query->orWhere($this->getTable() . '.' . $field, $operator, $string_like);
                }
            } else {
                if (strpos($field, '.') !== false) {
                    $exploded = explode('.', $field);
                    return $this->getQueryWhereHas($query, $exploded[0], $exploded[1], $operator, $string_like);
                } else {
                    $this->assertSearchableColumn($field);
                    return $query->where($this->getTable() . '.' . $field, $operator, $string_like);
                }
            }
        } else {
            $primary = $this->getKeyName();
            // Hidden columns (password, tokens, ...) are never searched.
            $cols = array_diff($this->getTableColumns(), $this->getHidden());

            return $query->where(function (Builder $q) use ($mode, $primary, $cols, $string_like, $arr_date_fields, $operator) {
                if ($mode == 'or') {
                    foreach (array_diff($cols, $arr_date_fields) as $col) {
                        if ($col !== $primary) {
                            $q->orWhere($this->getTable() . '.' . $col, $operator, $string_like);
                        }
                    }
                } else {
                    foreach (array_diff($cols, $arr_date_fields) as $col) {
                        if ($col !== $primary) {
                            $q->where($this->getTable() . '.' . $col, $operator, $string_like);
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
    public function scopeSearchExact(Builder $query, $string, $field = '', $mode = 'or', $operator = '=')
    {
        if (empty($operator)) {
            $operator = '=';
        }
        $arr_date_fields = ['created_at', 'updated_at', 'deleted_at'];

        if (!empty($field)) {
            if (in_array($field, $arr_date_fields)) {
                return $this->getQueryDateSearch($query, $string, $field);
            }

            if ($mode == 'or') {
                if (strpos($field, '.') !== false) {
                    $exploded = explode('.', $field);
                    return $this->getQueryOrWhereHas($query, $exploded[0], $exploded[1], $operator, $string);
                } else {
                    $this->assertSearchableColumn($field);
                    return $query->orWhere($this->getTable() . '.' . $field, $operator, $string);
                }
            } else {
                if (strpos($field, '.') !== false) {
                    $exploded = explode('.', $field);
                    return $this->getQueryWhereHas($query, $exploded[0], $exploded[1], $operator, $string);
                } else {
                    $this->assertSearchableColumn($field);
                    return $query->where($this->getTable() . '.' . $field, $operator, $string);
                }
            }
        } else {
            $primary = $this->getKeyName();
            // Hidden columns (password, tokens, ...) are never searched.
            $cols = array_diff($this->getTableColumns(), $this->getHidden());

            return $query->where(function (Builder $q) use ($mode, $primary, $cols, $string, $arr_date_fields, $operator) {
                if ($mode == 'or') {
                    foreach (array_diff($cols, $arr_date_fields) as $col) {
                        if ($col !== $primary) {
                            $q->orWhere($this->getTable() . '.' . $col, $operator, $string);
                        }
                    }
                } else {
                    foreach (array_diff($cols, $arr_date_fields) as $col) {
                        if ($col !== $primary) {
                            $q->where($this->getTable() . '.' . $col, $operator, $string);
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
    public function scopeSearchMultiple(Builder $query, $string = [], $field = [], $mode = 'or', $operator = 'LIKE')
    {
        if (empty($operator)) {
            $operator = 'LIKE';
        }
        return $query->where(function (Builder $q) use ($mode, $string, $field, $operator) {
            foreach ($string as $i => $string_item) {
                if ($string_item != '') {
                    if (!isset($field[$i])) {
                        throw new \Exception("Please complete your search field!");
                    }
                    $field_item = $field[$i];

                    $string_like = '%' . $string_item . '%';
                    if (strpos($string_item, '%') !== false) {
                        $string_like = $string_item;
                    }

                    if ($mode == 'or') {
                        if (strpos($field_item, '.') !== false) {
                            $exploded = explode('.', $field_item);
                            $q = $this->getQueryOrWhereHas($q, $exploded[0], $exploded[1], $operator, $string_like);
                        } else {
                            $this->assertSearchableColumn($field_item);
                            $q = $q->orWhere($this->getTable() . '.' . $field_item, $operator, $string_like);
                        }
                    } else {
                        if (strpos($field_item, '.') !== false) {
                            $exploded = explode('.', $field_item);
                            $q = $this->getQueryWhereHas($q, $exploded[0], $exploded[1], $operator, $string_like);
                        } else {
                            $this->assertSearchableColumn($field_item);
                            $q = $q->where($this->getTable() . '.' . $field_item, $operator, $string_like);
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
    public function scopeSearchExactMultiple(Builder $query, $string = [], $field = [], $mode = 'or', $operator = '=')
    {
        if (empty($operator)) {
            $operator = '=';
        }
        return $query->where(function (Builder $q) use ($mode, $string, $field, $operator) {
            foreach ($string as $i => $string_item) {
                if ($string_item != '') {
                    if (!isset($field[$i])) {
                        throw new \Exception("Please complete your search field!");
                    }
                    $field_item = $field[$i];

                    if ($mode == 'or') {
                        if (strpos($field_item, '.') !== false) {
                            $exploded = explode('.', $field_item);
                            $q = $this->getQueryOrWhereHas($q, $exploded[0], $exploded[1], $operator, $string_item);
                        } else {
                            $this->assertSearchableColumn($field_item);
                            $q = $q->orWhere($this->getTable() . '.' . $field_item, $operator, $string_item);
                        }
                    } else {
                        if (strpos($field_item, '.') !== false) {
                            $exploded = explode('.', $field_item);
                            $q = $this->getQueryWhereHas($q, $exploded[0], $exploded[1], $operator, $string_item);
                        } else {
                            $this->assertSearchableColumn($field_item);
                            $q = $q->where($this->getTable() . '.' . $field_item, $operator, $string_item);
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
        if (!empty($field)) {
            return $query->orderBy($this->getTable() . '.' . $field, $asc_or_desc);
        } else {
            return $query->orderBy($this->getTable() . '.' . $this->primaryKey, $asc_or_desc);
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
            return $query->whereDate($this->getTable() . '.' . $search_field, substr($search, 0, 10));
        } else {
            switch ($strlen) {
                case 10:
                    return $query->whereDate($this->getTable() . '.' . $search_field, $search);
                case 7:
                    return $query->whereYear($this->getTable() . '.' . $search_field, $year)
                        ->whereMonth($this->getTable() . '.' . $search_field, $month);
                case 4:
                    return $query->whereYear($this->getTable() . '.' . $search_field, $year);
                default:
                    return $query;
            }
        }
    }

    /**
     * Reject searches on hidden columns.
     *
     * A LIKE search on a hidden column (password hash, remember_token, ...) lets a
     * client guess its value one character at a time from which rows come back.
     *
     * @throws BadRequestException
     */
    protected function assertSearchableColumn($column)
    {
        if (in_array($column, $this->getHidden(), true)) {
            throw new BadRequestException('Invalid search field.');
        }
    }

    /**
     * Reject relation names that are not real relations of this model.
     *
     * The relation name comes from request input (search_field "relation.column").
     * whereHas() resolves it by calling $model->{$relation_name}(), so without this
     * check a request could call any method, e.g. truncate() or save().
     *
     * A model can set a strict allowlist with: protected $searchableRelations = ['author'];
     *
     * @throws BadRequestException
     */
    protected function assertSearchableRelation($relation_name)
    {
        if (!is_string($relation_name) || $relation_name === '') {
            throw new BadRequestException('Invalid search field.');
        }

        // Strict mode: only the relations the model lists explicitly.
        if (property_exists($this, 'searchableRelations') && is_array($this->searchableRelations)) {
            if (in_array($relation_name, $this->searchableRelations, true)) {
                return;
            }
            throw new BadRequestException('Invalid search field.');
        }

        // Dynamic relations registered with Model::resolveRelationUsing().
        if ($this->relationResolver(static::class, $relation_name)) {
            return;
        }

        if (!method_exists($this, $relation_name) || method_exists(Model::class, $relation_name)) {
            throw new BadRequestException('Invalid search field.');
        }

        // Methods from framework traits (e.g. SoftDeletes::restore) report the
        // consumer's model as their declaring class, so check the traits directly.
        foreach (class_uses_recursive(static::class) as $trait) {
            if ((str_starts_with($trait, 'Illuminate\\') || str_starts_with($trait, 'Gemboot\\Traits\\'))
                && method_exists($trait, $relation_name)
            ) {
                throw new BadRequestException('Invalid search field.');
            }
        }

        $method = new ReflectionMethod($this, $relation_name);
        $declaring_class = $method->getDeclaringClass()->getName();
        if (!$method->isPublic()
            || $method->isStatic()
            || $method->getNumberOfRequiredParameters() > 0
            || str_starts_with($declaring_class, 'Illuminate\\')
            || str_starts_with($declaring_class, 'Gemboot\\Models\\')
        ) {
            throw new BadRequestException('Invalid search field.');
        }

        // A declared return type must be a relation. Untyped methods are allowed
        // for backward compatibility; use $searchableRelations to rule them out.
        $return_type = $method->getReturnType();
        if ($return_type !== null) {
            if (!($return_type instanceof ReflectionNamedType)
                || $return_type->isBuiltin()
                || !is_a($return_type->getName(), Relation::class, true)
            ) {
                throw new BadRequestException('Invalid search field.');
            }
        }
    }

    protected function getQueryWhereHas(Builder &$query, $relation_name, $col_name, $operator, $string_search)
    {
        $this->assertSearchableRelation($relation_name);

        return $query->whereHas($relation_name, function (Builder $q) use ($col_name, $operator, $string_search) {
            if (in_array($col_name, $q->getModel()->getHidden(), true)) {
                throw new BadRequestException('Invalid search field.');
            }
            $q->where($col_name, $operator, $string_search);
        });
    }

    protected function getQueryOrWhereHas(Builder &$query, $relation_name, $col_name, $operator, $string_search)
    {
        $this->assertSearchableRelation($relation_name);

        return $query->orWhereHas($relation_name, function (Builder $q) use ($col_name, $operator, $string_search) {
            if (in_array($col_name, $q->getModel()->getHidden(), true)) {
                throw new BadRequestException('Invalid search field.');
            }
            $q->where($col_name, $operator, $string_search);
        });
    }
}

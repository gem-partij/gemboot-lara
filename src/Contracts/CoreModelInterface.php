<?php
namespace Gemboot\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface CoreModelInterface
{
    public function scopeSearch(Builder $query, $string, $field = '', $mode = 'or');

    public function scopeSearchExact(Builder $query, $string, $field = '', $mode = 'or');

    public function scopeSearchMultiple(Builder $query, $string = [], $field = [], $mode = 'or');

    public function scopeSearchExactMultiple(Builder $query, $string = [], $field = [], $mode = 'or');

    public function scopeOrder(Builder $query, $field = '', $asc_or_desc = 'asc');

    public function scopePerPage(Builder $query, $limit = 30);
}

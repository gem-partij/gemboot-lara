<?php
namespace Gemboot\Contracts;

interface CoreModelContract
{
    public function scopeSearch($query, $string, $field = '', $mode = 'or');

    public function scopeSearchExact($query, $string, $field = '', $mode = 'or');

    public function scopeSearchMultiple($query, $string = [], $field = [], $mode = 'or');

    public function scopeSearchExactMultiple($query, $string = [], $field = [], $mode = 'or');

    public function scopeOrder($query, $field = '', $asc_or_desc = 'asc');

    public function scopePerPage($query, $limit = 30);
}

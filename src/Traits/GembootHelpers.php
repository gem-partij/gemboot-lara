<?php
namespace Gemboot\Traits;

trait GembootHelpers
{
    public function getCacheKey($tag, $id, $single_or_group = 'single')
    {
        $single_or_group = ($single_or_group == 'group') ? config('c_cache_observer.cache_group_prefix') : config('c_cache_observer.cache_single_prefix');
        $cacheKey = $tag.'-'.$single_or_group.'-'.$id;
        return $cacheKey;
    }

    public function getCacheTags($tableName)
    {
        return [
            $tableName,
            $tableName.'-addwith',
        ];
    }
}

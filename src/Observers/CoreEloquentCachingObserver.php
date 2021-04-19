<?php
namespace Gemboot\Observers;

abstract class CoreEloquentCachingObserver
{
    protected $cacheTag = "";
    protected $cacheSeconds = 60*60*24;

    protected function getTags()
    {
        return get_cache_tags($this->cacheTag);
    }

    /**
     * Handle the Eloquent "saved" event.
     *
     * @return void
     */
    public function saved($data)
    {
        cache()->tags($this->getTags())->flush();
    }

    /**
     * Handle the Eloquent "deleted" event.
     *
     * @return void
     */
    public function deleted($data)
    {
        cache()->tags($this->getTags())->flush();
    }

    /**
     * Handle the Eloquent "restored" event.
     *
     * @return void
     */
    public function restored($data)
    {
        cache()->tags($this->getTags())->flush();
    }
}

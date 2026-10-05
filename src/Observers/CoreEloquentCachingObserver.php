<?php
namespace Gemboot\Observers;

abstract class CoreEloquentCachingObserver
{
    protected $cacheTag = "";
    protected $cacheSeconds = 60*60*24;

    protected function getTags()
    {
        // Must match GembootHelpers::getCacheTags(), which CoreService uses when
        // storing cache entries, so that flush() hits the same entries.
        return [
            $this->cacheTag,
            $this->cacheTag . '-addwith',
        ];
    }

    /**
     * Handle the Eloquent "saved" event.
     *
     * @return void
     */
    public function saved($data)
    {
        // Stores without tag support (file, database) throw on tags(), which would
        // make the consumer's save fail. CoreService does not cache on those stores.
        if (cache()->supportsTags()) {
            cache()->tags($this->getTags())->flush();
        }
    }

    /**
     * Handle the Eloquent "deleted" event.
     *
     * @return void
     */
    public function deleted($data)
    {
        if (cache()->supportsTags()) {
            cache()->tags($this->getTags())->flush();
        }
    }

    /**
     * Handle the Eloquent "restored" event.
     *
     * @return void
     */
    public function restored($data)
    {
        if (cache()->supportsTags()) {
            cache()->tags($this->getTags())->flush();
        }
    }
}

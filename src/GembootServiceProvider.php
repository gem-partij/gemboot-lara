<?php
namespace Gemboot;

use Illuminate\Support\ServiceProvider;

class GembootServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/gemboot_gw.php' => config_path('gemboot_gw.php'),
        ], 'gemboot');
    }

    public function register()
    {
    }
}

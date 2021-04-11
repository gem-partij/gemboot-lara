<?php
namespace Gemboot;

use Illuminate\Support\ServiceProvider;

use Gemboot\GembootResponse;
use Gemboot\Commands\GembootTest;
use Gemboot\Commands\MakeController;
use Gemboot\Commands\MakeModel;
use Gemboot\Commands\MakeService;

class GembootServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/gemboot_gw.php' => config_path('gemboot_gw.php'),
        ], 'gemboot');


        if ($this->app->runningInConsole()) {
            $this->commands([
                GembootTest::class,
                MakeController::class,
                MakeModel::class,
                MakeService::class,
            ]);
        }
    }

    public function register()
    {
        $this->app->bind('gemboot-response', function($app) {
            return new GembootResponse();
        });
    }
}

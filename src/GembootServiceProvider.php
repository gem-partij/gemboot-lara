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
        if ($this->app->runningInConsole()) {
            // Export gemboot commands
            $this->commands([
                GembootTest::class,
                MakeController::class,
                MakeModel::class,
                MakeService::class,
            ]);

            // Export gemboot gateway config
            $this->publishes([
                __DIR__.'/../config/gemboot_gw.php' => config_path('gemboot_gw.php'),
            ], 'gemboot-gateway');
        }
    }

    public function register()
    {
        // Register a class in the service container
        $this->app->bind('gemboot-response', function($app) {
            return new GembootResponse();
        });
    }
}

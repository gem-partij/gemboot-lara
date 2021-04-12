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

            // Export gemboot migrations
            // if(! class_exists('CreateGembootTestUsersTable')) {
            //     $this->publishes([
            //         __DIR__.'/../database/migrations/create_gemboot_test_users_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_gemboot_test_users_table.php'),
            //     ], 'gemboot-migrations');
            // }
        }

        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        // $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }

    public function register()
    {
        // Register a class in the service container
        $this->app->bind('gemboot-response', function($app) {
            return new GembootResponse();
        });
    }
}

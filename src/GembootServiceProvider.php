<?php

namespace Gemboot;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Gemboot\SSO\Auth\SSOGuard;
use Gemboot\SSO\Auth\SSOUserProvider;

use Gemboot\GembootRequest;
use Gemboot\GembootResponse;
use Gemboot\GembootPermission;
use Gemboot\GembootValidator;
use Gemboot\Libraries\AuthLibrary;
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

            // Export gemboot config
            $this->publishes([
                __DIR__ . '/../config/gemboot.php' => config_path('gemboot.php'),
            ], 'gemboot');

            // Export gemboot gateway config
            $this->publishes([
                __DIR__ . '/../config/gemboot.php' => config_path('gemboot.php'),
            ], 'gemboot-gateway');

            // Export gemboot auth config
            $this->publishes([
                __DIR__ . '/../config/gemboot.php' => config_path('gemboot.php'),
            ], 'gemboot-auth');

            // Export gemboot file_handler config
            $this->publishes([
                __DIR__ . '/../config/gemboot.php' => config_path('gemboot.php'),
            ], 'gemboot-file-handler');
        }

        // $this->app['auth']->extend('jwt', function ($app, $name, array $config) {
        //     $guard = new JWTGuard(
        //         $app['tymon.jwt'],
        //         $app['auth']->createUserProvider($config['provider']),
        //         $app['request']
        //     );

        //     $app->refresh('request', $guard, 'setRequest');

        //     return $guard;
        // });

        Auth::extend('gemboot-sso-token', function ($app, $name, array $config) {
            $provider = Auth::createUserProvider($config['provider']);

            return new SSOGuard(
                $provider,
                $app['request']
            );
        });

        Auth::provider('gemboot-sso-provider', function ($app, array $config) {
            return new SSOUserProvider();
        });
    }

    public function register()
    {
        // Register a class in the service container
        $this->app->bind('gemboot-request', function ($app) {
            return new GembootRequest();
        });

        $this->app->bind('gemboot-response', function ($app) {
            return new GembootResponse();
        });

        $this->app->bind('gemboot-permission', function ($app) {
            return new GembootPermission();
        });

        $this->app->bind('gemboot-auth', function ($app) {
            return new AuthLibrary();
        });

        $this->app->bind('gemboot-validator', function ($app) {
            return new GembootValidator();
        });
    }
}

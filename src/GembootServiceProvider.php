<?php

namespace Gemboot;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Gemboot\Auth\GembootGuard;
use Gemboot\Auth\RemoteTokenVerifier;
use Gemboot\Auth\TokenVerifier;
use Gemboot\SSO\Auth\SSOGuard;
use Gemboot\SSO\Auth\SSOUserProvider;

use Gemboot\GembootRequest;
use Gemboot\GembootResponse;
use Gemboot\GembootPermission;
use Gemboot\GembootValidator;
use Gemboot\Libraries\AuthLibrary;
use Gemboot\Commands\Doctor;
use Gemboot\Commands\Permissions;
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
                Doctor::class,
                Permissions::class,
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

        Auth::extend('gemboot-sso-token', function ($app, $name, array $config) {
            $provider = Auth::createUserProvider($config['provider']);

            $guard = new SSOGuard(
                $provider,
                $app['request']
            );

            // Hand every new request to the guard, so it doesn't keep the first
            // request's user (tests with several requests, Octane workers).
            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });

        // One guard for every auth path: the bearer token's user, as answered by
        // the bound TokenVerifier. No user provider needed.
        Auth::extend('gemboot', function ($app, $name, array $config) {
            $guard = new GembootGuard($app->make(TokenVerifier::class), $app['request']);
            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });

        Auth::provider('gemboot-sso-provider', function ($app, array $config) {
            return new SSOUserProvider();
        });
    }

    public function register()
    {
        // Defaults for consumers who never published config/gemboot.php, and for
        // top-level keys added after they published it.
        $this->mergeConfigFrom(__DIR__ . '/../config/gemboot.php', 'gemboot');

        // Apps can bind their own verifier before or after this provider runs.
        $this->app->bindIf(TokenVerifier::class, RemoteTokenVerifier::class);

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

<?php

namespace Gemboot\Tests;

use Orchestra\Testbench\Attributes\WithEnv;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithMigration;
use function Orchestra\Testbench\artisan;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Gemboot\GembootServiceProvider;
use Gemboot\Tests\Controllers\TestUserController;
use Gemboot\Tests\Controllers\TestAuthLibraryController;
use Gemboot\Tests\Controllers\TestHttpStatusController;

// class TestCase extends \PHPUnit\Framework\TestCase {
#[WithEnv('DB_CONNECTION', 'testing')]
#[WithConfig('database.default', 'testing')]
#[WithMigration]
class TestCase extends \Orchestra\Testbench\TestCase
{

    // use RefreshDatabase;
    // use DatabaseTransactions;

    public function setUp(): void
    {
        // RefreshDatabaseState::$migrated = false;

        parent::setUp();

        // $this->artisan('migrate', ['--database' => 'testbench'])->run();
        // $this->refreshDatabase();
        // $this->artisan('migrate')->run();
    }

    protected function getPackageProviders($app)
    {
        return [
            GembootServiceProvider::class,
        ];
    }

    // protected function getEnvironmentSetUp($app)
    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('gemboot.auth.base_url', 'http://199.169.3.14:3000/portal-pegawai');
        $app['config']->set('gemboot.auth.base_api', 'http://199.169.3.14:3000/portal-pegawai/api/auth');

        $app['config']->set('gemboot.file_handler.base_url', 'http://199.169.3.14:3000/file-handler');

        $app['config']->set('gemboot.notifications.telegram.token', env('GEMBOOT_TELEGRAM_BOT_TOKEN'));
        $app['config']->set('gemboot.notifications.telegram.chat_id', env('GEMBOOT_TELEGRAM_CHAT_ID'));

        // // import the CreatePostsTable class from the migration
        // include_once __DIR__ . '/../database/migrations/create_gemboot_test_users_table.php.stub';
        //
        // // run the up() method of that migration class
        // (new \CreateGembootTestUsersTable)->up();
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        artisan($this, 'migrate', ['--database' => 'testing']);

        $this->beforeApplicationDestroyed(function () {
            artisan($this, 'migrate:rollback', ['--database' => 'testing']);
        });
    }

    /**
     * Define routes setup.
     *
     * @param  \Illuminate\Routing\Router  $router
     *
     * @return void
     */
    protected function defineRoutes($router)
    {
        $router->middleware(['api'])->prefix('test')->group(function () use ($router) {
            // $router->get('/', [TestUserController::class, 'index']);
            $router->apiResource('users', TestUserController::class);
        });

        $router->middleware(['api'])->prefix('http-status')->group(
            function () use ($router) {
                $router->get('500', [TestHttpStatusController::class, 'test500Exception']);
            }
        );

        $router->middleware(['api'])->prefix('auth')->group(function () use ($router) {
            $router->post('login', [TestAuthLibraryController::class, 'login']);

            $router->get('me', [TestAuthLibraryController::class, 'me']);

            $router->get('validate-token', [TestAuthLibraryController::class, 'validateToken']);

            $router->get('has-role', [TestAuthLibraryController::class, 'hasRole']);

            $router->get('has-permission-to', [TestAuthLibraryController::class, 'hasPermissionTo']);

            $router->post('logout', [TestAuthLibraryController::class, 'logout']);
        });
    }
}

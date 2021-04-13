<?php
namespace Gemboot\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Gemboot\GembootServiceProvider;
use Gemboot\Tests\Controllers\TestUserController;

// class TestCase extends \PHPUnit\Framework\TestCase {
class TestCase extends \Orchestra\Testbench\TestCase {

    use RefreshDatabase;

    public function setUp(): void {
        parent::setUp();

        // $this->artisan('migrate', ['--database' => 'testbench'])->run();
    }

    protected function getPackageProviders($app) {
        return [
            GembootServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app) {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

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

        $this->artisan('migrate', ['--database' => 'testbench'])->run();

        $this->beforeApplicationDestroyed(function () {
            $this->artisan('migrate:rollback', ['--database' => 'testbench'])->run();
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
        $router->middleware(['api'])->prefix('test')->group(function() use ($router) {
            $router->get('/', [TestUserController::class, 'index']);
        });
    }

}

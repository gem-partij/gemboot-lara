<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Middleware\HasPermissionTo;
use Gemboot\Middleware\HasRole;
use Gemboot\Middleware\TokenValidated;
use Gemboot\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class PermissionsCommandTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $router = $this->app['router'];
        $router->aliasMiddleware('token-validated', TokenValidated::class);
        $router->aliasMiddleware('role', HasRole::class);
        $router->aliasMiddleware('permission', HasPermissionTo::class);
        $router->middlewareGroup('report-readers', ['token-validated', 'permission:report.read']);

        $ok = fn () => 'ok';
        $router->get('/admin', $ok)->middleware(['token-validated', 'role:admin|editor']);
        $router->get('/users', $ok)->middleware(['token-validated', 'permission:user.read']);
        $router->post('/users', $ok)->middleware(['token-validated', 'permission:user.write']);
        $router->get('/users/export', $ok)->middleware(['token-validated', HasPermissionTo::class . ':user.raed']);
        $router->get('/reports', $ok)->middleware('report-readers');
    }

    function test_json_lists_roles_permissions_and_their_routes()
    {
        Artisan::call('gemboot:permissions', ['--json' => true]);
        $result = json_decode(Artisan::output(), true);

        $this->assertSame(['admin', 'editor'], array_keys($result['roles']));
        $this->assertSame(['GET /admin'], $result['roles']['admin']);
        $this->assertSame(['report.read', 'user.raed', 'user.read', 'user.write'], array_keys($result['permissions']));
        // Found through a middleware group, and through the class name instead of the alias.
        $this->assertSame(['GET /reports'], $result['permissions']['report.read']);
        $this->assertSame(['GET /users/export'], $result['permissions']['user.raed']);
    }

    function test_possible_typos_are_flagged()
    {
        Artisan::call('gemboot:permissions', ['--json' => true]);
        $typos = json_decode(Artisan::output(), true)['possible_typos'];

        $this->assertContains(['user.raed', 'user.read'], $typos);
        $this->assertNotContains(['user.read', 'user.write'], $typos);
    }

    function test_readable_output()
    {
        Artisan::call('gemboot:permissions');
        $output = Artisan::output();

        $this->assertStringContainsString('Roles (2)', $output);
        $this->assertStringContainsString('Permissions (4)', $output);
        $this->assertStringContainsString('POST /users', $output);
        $this->assertStringContainsString('user.raed  <->  user.read', $output);
    }
}

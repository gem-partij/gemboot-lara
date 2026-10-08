<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Exceptions\ForbiddenException;
use Gemboot\GembootPermission;
use Gemboot\Tests\TestCase;
use Gemboot\Tests\Support\UsesFakeAuthServer;

class GembootPermissionTest extends TestCase
{
    use UsesFakeAuthServer;

    public function setUp(): void
    {
        parent::setUp();
        config()->set('gemboot.auth.base_api', static::$fakeAuthUrl);

        // Gemboot only asks the auth service when the request carries a token
        // (since 8.6, a missing token is rejected locally).
        $request = \Illuminate\Http\Request::create('/');
        $request->headers->set('Authorization', 'Bearer test-token');
        $this->app->instance('request', $request);
    }

    function test_has_role()
    {
        // Before the fix this read ->has_role on an array and threw an ErrorException.
        $this->assertTrue((new GembootPermission)->hasRole('admin'));
        $this->assertFalse((new GembootPermission)->hasRole('guest'));
    }

    function test_has_permission_to()
    {
        $this->assertTrue((new GembootPermission)->hasPermissionTo('user.read'));
        $this->assertFalse((new GembootPermission)->hasPermissionTo('user.delete'));

        // An array of names asks the auth service for any of them.
        $this->assertTrue((new GembootPermission)->hasPermissionTo(['user.delete', 'user.read']));
    }

    function test_require_permission_throws_forbidden()
    {
        $this->assertTrue((new GembootPermission)->requirePermission('user.read'));

        $this->expectException(ForbiddenException::class);
        (new GembootPermission)->requirePermission('user.delete');
    }

    function test_unreachable_auth_service_denies_instead_of_crashing()
    {
        config()->set('gemboot.auth.base_api', 'http://127.0.0.1:1/');

        $this->assertFalse((new GembootPermission)->hasRole('admin'));
        $this->assertFalse((new GembootPermission)->hasPermissionTo('user.read'));
    }
}

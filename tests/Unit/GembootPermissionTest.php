<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Exceptions\ForbiddenException;
use Gemboot\GembootPermission;
use Gemboot\Tests\TestCase;

class GembootPermissionTest extends TestCase
{
    protected static $fakeAuthServer;
    protected static $fakeAuthUrl;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Pick a free port, then start the fake auth service on it.
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $command = [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__ . '/../Support/fake-auth-server.php'];
        static::$fakeAuthServer = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        static::$fakeAuthUrl = "http://127.0.0.1:{$port}/";

        for ($i = 0; $i < 50; $i++) {
            if ($connection = @fsockopen('127.0.0.1', $port)) {
                fclose($connection);
                return;
            }
            usleep(100000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (static::$fakeAuthServer) {
            proc_terminate(static::$fakeAuthServer);
            proc_close(static::$fakeAuthServer);
        }

        parent::tearDownAfterClass();
    }

    public function setUp(): void
    {
        parent::setUp();
        config()->set('gemboot.auth.base_api', static::$fakeAuthUrl);
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

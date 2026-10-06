<?php

namespace Gemboot\Tests\Support;

/**
 * Starts tests/Support/fake-auth-server.php on a free port for the test class.
 */
trait UsesFakeAuthServer
{
    protected static $fakeAuthServer;
    protected static $fakeAuthUrl;
    protected static $fakeAuthLog;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        static::$fakeAuthLog = tempnam(sys_get_temp_dir(), 'gemboot-fake-auth-');
        $command = [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__ . '/fake-auth-server.php'];
        $env = array_merge(getenv(), ['FAKE_AUTH_LOG' => static::$fakeAuthLog]);
        static::$fakeAuthServer = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env);
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
        @unlink(static::$fakeAuthLog);
        @unlink(static::$fakeAuthLog . '.flaky');

        parent::tearDownAfterClass();
    }

    /**
     * Requests the fake auth service received since the log was last cleared.
     */
    protected function fakeAuthRequests(): array
    {
        clearstatcache();
        $lines = @file(static::$fakeAuthLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $lines ?: [];
    }

    protected function clearFakeAuthLog(): void
    {
        file_put_contents(static::$fakeAuthLog, '');
        @unlink(static::$fakeAuthLog . '.flaky');
    }
}

<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Testing\FakeAuthService;
use Gemboot\Tests\Support\UsesFakeAuthServer;
use Gemboot\Tests\TestCase;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Artisan;

class ContractTestCommandTest extends TestCase
{
    use UsesFakeAuthServer;

    private function contractTest(array $options): array
    {
        $code = Artisan::call('gemboot:contract-test', $options);

        return [$code, Artisan::output()];
    }

    /**
     * An auth service that answers what $answer returns: fn (endpoint, query, authorization) => [status, body].
     */
    private function scriptedAuthService(callable $answer): void
    {
        $this->app->instance(FakeAuthService::class, new class($answer) extends FakeAuthService {
            public function __construct(private $answer)
            {
                parent::__construct();
            }

            public function handler(): callable
            {
                return function ($request, array $options) {
                    parse_str($request->getUri()->getQuery(), $query);
                    $endpoint = (string) last(explode('/', trim($request->getUri()->getPath(), '/')));
                    [$status, $body] = ($this->answer)($endpoint, $query, $request->getHeaderLine('Authorization'));

                    return Create::promiseFor(new Response($status, ['Content-Type' => 'application/json'], json_encode($body)));
                };
            }
        });
    }

    /**
     * A correct auth service, as scripted answers; tests change one thing each.
     */
    private function correctAnswer(string $endpoint, array $query, string $auth): array
    {
        if ($auth !== 'Bearer good') {
            return [401, ['message' => 'Unauthenticated']];
        }

        return match ($endpoint) {
            'me' => [200, ['data' => ['id' => 1, 'name' => 'Ana']]],
            'validate-token' => [200, ['data' => ['user_id' => 1]]],
            'has-role' => [200, ['data' => ['has_role' => in_array('admin', explode('|', $query['role_name']), true)]]],
            'has-permission-to' => (function () use ($query) {
                $names = explode('|', $query['permission_name']);
                $granted = array_intersect($names, ['report.read']);

                return [200, ['data' => [
                    'has_permission_to' => count($granted) === count($names),
                    'has_any_permission' => $granted !== [],
                ]]];
            })(),
            default => [404, null],
        };
    }

    private function fullOptions(array $extra = []): array
    {
        return array_merge(['--url' => 'http://auth.test/api/auth/', '--token' => 'good', '--role' => 'admin', '--permission' => 'report.read'], $extra);
    }

    function test_a_correct_auth_service_passes()
    {
        [$code, $output] = $this->contractTest(['--url' => static::$fakeAuthUrl, '--token' => 'good', '--role' => 'admin', '--permission' => 'user.read']);

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('The auth service answers the way Gemboot expects.', $output);
        $this->assertStringNotContainsString('✗', $output);
    }

    function test_the_token_is_never_printed()
    {
        [, $output] = $this->contractTest(['--url' => static::$fakeAuthUrl, '--token' => 'Bearer good']);
        [, $json] = $this->contractTest(['--url' => static::$fakeAuthUrl, '--token' => 'good', '--json' => true]);

        $this->assertDoesNotMatchRegularExpression('/\bgood\b/', $output . $json);
    }

    function test_without_a_token_only_the_rejections_are_checked()
    {
        [$code, $output] = $this->contractTest(['--url' => static::$fakeAuthUrl]);

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('me without a token', $output);
        $this->assertStringContainsString('No token given', $output);
    }

    function test_an_unreachable_service_fails()
    {
        [$code, $output] = $this->contractTest(['--url' => 'http://127.0.0.1:1/']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('No connection', $output);
    }

    function test_a_service_that_accepts_any_token_fails()
    {
        $this->scriptedAuthService(fn ($endpoint) => $endpoint === 'me' ? [200, ['data' => ['id' => 1]]] : [401, null]);

        [$code, $output] = $this->contractTest($this->fullOptions());

        $this->assertSame(1, $code);
        $this->assertStringContainsString('would let anyone in', $output);
    }

    function test_5xx_for_an_invalid_token_fails()
    {
        $this->scriptedAuthService(fn ($endpoint, $query, $auth) => $auth === 'Bearer good'
            ? $this->correctAnswer($endpoint, $query, $auth)
            : [500, null]);

        [$code, $output] = $this->contractTest($this->fullOptions());

        $this->assertSame(1, $code);
        $this->assertStringContainsString('answers 503 instead of 401', $output);
    }

    function test_wrong_role_answers_fail()
    {
        $this->scriptedAuthService(fn ($endpoint, $query, $auth) => $endpoint === 'has-role'
            ? [200, ['data' => ['hasRole' => true]]]
            : $this->correctAnswer($endpoint, $query, $auth));

        [$code, $output] = $this->contractTest($this->fullOptions());

        $this->assertSame(1, $code);
        $this->assertStringContainsString('No boolean "has_role"', $output);
    }

    function test_a_role_nobody_has_must_be_denied()
    {
        $this->scriptedAuthService(fn ($endpoint, $query, $auth) => $endpoint === 'has-role'
            ? [200, ['has_role' => true]]
            : $this->correctAnswer($endpoint, $query, $auth));

        [$code, $output] = $this->contractTest($this->fullOptions());

        $this->assertSame(1, $code);
        $this->assertStringContainsString('role: would let every user in', $output);
    }

    function test_permission_a_or_b_as_any_of_is_a_warning()
    {
        $this->scriptedAuthService(function ($endpoint, $query, $auth) {
            if ($endpoint !== 'has-permission-to') {
                return $this->correctAnswer($endpoint, $query, $auth);
            }
            $granted = in_array('report.read', explode('|', $query['permission_name']), true);

            return [200, ['data' => ['has_permission_to' => $granted]]];
        });

        [$code, $output] = $this->contractTest($this->fullOptions());

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('"a|b" needs both', $output);
        $this->assertStringContainsString('No has_any_permission', $output);
        $this->assertStringContainsString('warning(s)', $output);
    }

    function test_validate_token_may_answer_any_body()
    {
        // e.g. a raw {"success": true} without the usual wrapper.
        $this->scriptedAuthService(fn ($endpoint, $query, $auth) => $endpoint === 'validate-token' && $auth === 'Bearer good'
            ? [200, ['success' => true]]
            : $this->correctAnswer($endpoint, $query, $auth));

        [$code, $output] = $this->contractTest($this->fullOptions());

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('the body may have any shape', $output);
    }

    function test_me_without_an_id_is_a_warning()
    {
        $this->scriptedAuthService(fn ($endpoint, $query, $auth) => $endpoint === 'me' && $auth === 'Bearer good'
            ? [200, ['data' => ['npp' => '123', 'name' => 'Ana']]]
            : $this->correctAnswer($endpoint, $query, $auth));

        [$code, $output] = $this->contractTest($this->fullOptions());

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('auth()->id() will be null', $output);
    }

    function test_json_output()
    {
        [$code, $output] = $this->contractTest(['--url' => static::$fakeAuthUrl, '--token' => 'good', '--json' => true]);

        $result = json_decode($output, true);
        $this->assertSame(0, $code);
        $this->assertSame(0, $result['failed']);
        $this->assertContains('me with the token', array_column($result['checks'], 'check'));
    }

    function test_no_url_is_an_error()
    {
        config()->set('gemboot.auth.base_api', null);

        [$code, $output] = $this->contractTest([]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('No valid auth service URL', $output);
    }
}

<?php

namespace Gemboot\Tests\Feature;

use Gemboot\FileHandler\FileHandler;
use Gemboot\Libraries\HttpClient;
use Gemboot\Middleware\AssignRequestId;
use Gemboot\Middleware\TokenValidated;
use Gemboot\SSO\Auth\SSOGuard;
use Gemboot\SSO\Auth\SSOUserProvider;
use Gemboot\Testing\FakeAuthService;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GembootRequestIdTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('gemboot.auth.base_api', 'http://auth.test/api/auth/');

        $router = $this->app['router'];
        // Answers with the ID the app sees in Laravel's Context.
        $router->get('/traced', fn () => (string) Context::get('request_id'))->middleware(AssignRequestId::class);
        $router->get('/traced/me', fn () => 'ok')->middleware([AssignRequestId::class, TokenValidated::class]);
    }

    /**
     * A fake auth service that also notes the request ID header of every call.
     */
    private function fakeAuthNotingRequestIds(): FakeAuthService
    {
        $fake = new class extends FakeAuthService {
            public array $requestIds = [];

            public function handler(): callable
            {
                $answer = parent::handler();

                return function ($request, array $options) use ($answer) {
                    $this->requestIds[] = $request->getHeaderLine('X-Request-Id');

                    return $answer($request, $options);
                };
            }
        };
        $this->app->instance(FakeAuthService::class, $fake);

        return $fake;
    }

    function test_a_request_without_an_id_gets_a_new_one()
    {
        $response = $this->get('/traced')->assertOk();

        $id = $response->headers->get('X-Request-Id');
        $this->assertTrue(Str::isUuid($id));
        $this->assertSame($id, $response->getContent());
    }

    function test_a_valid_incoming_id_is_kept()
    {
        $response = $this->get('/traced', ['X-Request-Id' => '01JABCDEF-gateway.42'])->assertOk();

        $this->assertSame('01JABCDEF-gateway.42', $response->headers->get('X-Request-Id'));
        $this->assertSame('01JABCDEF-gateway.42', $response->getContent());
    }

    function test_an_invalid_incoming_id_is_replaced()
    {
        foreach (['two words', "forged\tlog line", str_repeat('a', 129)] as $incoming) {
            $id = $this->get('/traced', ['X-Request-Id' => $incoming])->headers->get('X-Request-Id');

            $this->assertTrue(Str::isUuid($id), "Kept invalid ID [{$incoming}].");
        }
    }

    function test_incoming_ids_can_be_ignored()
    {
        config()->set('gemboot.request_id.accept_incoming', false);

        $id = $this->get('/traced', ['X-Request-Id' => 'chosen-by-client'])->headers->get('X-Request-Id');

        $this->assertTrue(Str::isUuid($id));
    }

    function test_the_header_name_is_configurable()
    {
        config()->set('gemboot.request_id.header', 'X-Correlation-Id');

        $response = $this->get('/traced', ['X-Correlation-Id' => 'abc-1']);

        $this->assertSame('abc-1', $response->headers->get('X-Correlation-Id'));
        $this->assertFalse($response->headers->has('X-Request-Id'));
    }

    function test_auth_service_calls_carry_the_id()
    {
        $fake = $this->fakeAuthNotingRequestIds();

        $this->get('/traced/me', ['Authorization' => 'Bearer t1', 'X-Request-Id' => 'abc-1'])->assertOk();

        $this->assertSame(['abc-1'], $fake->requestIds);
    }

    function test_sso_and_file_handler_calls_carry_the_id()
    {
        config()->set('gemboot.sso.get_user_url', 'https://users.test/user/me');
        Http::fake(['*' => Http::response(['data' => ['user' => ['id' => 7]]])]);
        Context::add('request_id', 'abc-1');

        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer t1');
        (new SSOGuard(new SSOUserProvider(), $request))->user();
        (new FileHandler())->setBaseUrl('https://files.test')->setToken('t1')->ping();

        Http::assertSentCount(2);
        Http::assertSent(fn (HttpClientRequest $sent) => $sent->hasHeader('X-Request-Id', 'abc-1'));
        Http::assertNotSent(fn (HttpClientRequest $sent) => !$sent->hasHeader('X-Request-Id', 'abc-1'));
    }

    function test_without_an_id_nothing_extra_is_sent()
    {
        $fake = $this->fakeAuthNotingRequestIds();

        (new HttpClient('http://auth.test/api/auth/'))->setToken('Bearer t1')->get('me');

        $this->assertSame([''], $fake->requestIds);
    }

    function test_headers_set_by_the_caller_win()
    {
        $fake = $this->fakeAuthNotingRequestIds();
        Context::add('request_id', 'abc-1');

        (new HttpClient('http://auth.test/api/auth/'))
            ->withHeaders(['X-Request-Id' => 'set-by-caller'])
            ->setToken('Bearer t1')
            ->get('me');

        $this->assertSame(['set-by-caller'], $fake->requestIds);
    }
}

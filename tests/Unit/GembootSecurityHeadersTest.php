<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Libraries\AuthLibrary;
use Gemboot\Middleware\TokenValidated;
use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Request;

class GembootSecurityHeadersTest extends TestCase
{
    protected function assertSecurityHeaders($response): void
    {
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    function test_success_and_error_responses_carry_the_headers()
    {
        TestUser::factory()->create();

        $this->assertSecurityHeaders($this->getJson('/test/users')->baseResponse);
        $this->assertSecurityHeaders($this->getJson('/test/users/999')->assertStatus(404)->baseResponse);
        $this->assertSecurityHeaders($this->getJson('/http-status/500')->assertStatus(500)->baseResponse);
    }

    function test_middleware_short_answers_carry_the_headers()
    {
        config()->set('gemboot.auth.base_api', 'http://127.0.0.1:1/');
        $this->app['router']->get('/client-only', fn () => 'ok')->middleware(TokenValidated::class . ':client');

        $response = $this->getJson('/client-only', ['Authorization' => 'Bearer x'])->assertStatus(503);

        $this->assertSecurityHeaders($response->baseResponse);
    }

    function test_auth_library_json_answers_carry_the_headers()
    {
        config()->set('gemboot.auth.base_api', 'http://127.0.0.1:1/');
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer x');

        $this->assertSecurityHeaders((new AuthLibrary)->me(true, $request));
    }

    function test_headers_can_be_turned_off_or_changed()
    {
        config()->set('gemboot.response.security_headers', []);
        $response = $this->getJson('/test/users')->baseResponse;
        $this->assertNull($response->headers->get('X-Content-Type-Options'));
        $this->assertStringNotContainsString('no-store', $response->headers->get('Cache-Control'));

        config()->set('gemboot.response.security_headers', ['Cache-Control' => 'public, max-age=60']);
        $response = $this->getJson('/test/users')->baseResponse;
        $this->assertStringContainsString('max-age=60', $response->headers->get('Cache-Control'));
    }

    function test_config_published_before_8_3_gets_the_defaults()
    {
        // An older published config/gemboot.php has a "response" section without
        // security_headers; config merging doesn't fill nested keys.
        config()->set('gemboot.response', ['send_header_error' => true]);

        $this->assertSecurityHeaders($this->getJson('/test/users')->baseResponse);
    }
}

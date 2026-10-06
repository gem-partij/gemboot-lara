<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Middleware\TokenValidated;
use Gemboot\Tests\Controllers\TestUserController;
use Gemboot\Tests\Controllers\TestUserValidatingController;
use Gemboot\Tests\Services\TestUserCapturingService;
use Gemboot\Tests\Services\TestUserService;
use Gemboot\Tests\Support\UsesFakeAuthServer;
use Gemboot\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class GembootControllerFixesTest extends TestCase
{
    use UsesFakeAuthServer;

    public function setUp(): void
    {
        parent::setUp();
        config()->set('gemboot.auth.base_api', static::$fakeAuthUrl);
        TestUserCapturingService::$received = [];
        $this->app->bind(TestUserService::class, TestUserCapturingService::class);

        $router = $this->app['router'];
        $router->post('/fix/users', [TestUserController::class, 'store']);
        $router->post('/fix/authed/users', [TestUserController::class, 'store'])->middleware(TokenValidated::class);
        $router->post('/fix/validating/users', [TestUserValidatingController::class, 'store']);
        $router->get('/fix/validating/users', [TestUserValidatingController::class, 'index']);
    }

    protected function userData(): array
    {
        return ['name' => 'User', 'email' => 'a@example.test', 'password' => 'secret'];
    }

    function test_user_login_merged_by_token_validated_is_not_saved()
    {
        $this->postJson('/fix/authed/users', $this->userData(), ['Authorization' => 'Bearer good'])
            ->assertStatus(200);

        $this->assertArrayNotHasKey('user_login', TestUserCapturingService::$received[0]);
    }

    function test_client_field_named_user_login_is_kept_without_token_validated()
    {
        // Only the value TokenValidated merged in is removed; an app may have a
        // real user_login column.
        $this->postJson('/fix/users', $this->userData() + ['user_login' => 'ana'])->assertStatus(200);

        $this->assertSame('ana', TestUserCapturingService::$received[0]['user_login']);
    }

    function test_failed_validation_closes_the_transaction()
    {
        // store() returned the 400 without rolling back the transaction it opened.
        $this->postJson('/fix/validating/users', ['name' => 'No email'])->assertStatus(400);

        $this->assertSame(0, DB::transactionLevel());
    }

    function test_log_access_tag_without_helper_does_not_crash()
    {
        $this->getJson('/fix/validating/users')->assertStatus(200);
    }

    function test_controllers_can_be_built_without_a_model()
    {
        $resource = new class extends \Gemboot\Controllers\CoreRestResourceController {
        };
        $proxy = new class extends \Gemboot\Controllers\CoreRestProxyController {
        };

        $this->assertInstanceOf(\Gemboot\Controllers\CoreRestResourceController::class, $resource);
        $this->assertInstanceOf(\Gemboot\Controllers\CoreRestProxyController::class, $proxy);
    }

    function test_generated_service_stub_uses_explicit_nullable()
    {
        $stub = file_get_contents(__DIR__ . '/../../stubs/service.stub');

        $this->assertStringContainsString('?{{ model }} $model = null', $stub);
    }
}

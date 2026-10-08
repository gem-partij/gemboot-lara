<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Auth\GembootUser;
use Gemboot\Facades\GembootAuthFacade as GembootAuth;
use Gemboot\Tests\Controllers\TestAuthorizeController;
use Gemboot\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Support\Facades\Gate;

class GembootPermissionGateTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('gemboot.auth.base_api', 'http://auth.test/api/auth/');
        config()->set('auth.guards.api', ['driver' => 'gemboot']);
        config()->set('gemboot.authorization.permissions_as_abilities', true);

        $router = $this->app['router'];
        $router->get('/can/report', fn () => 'ok')->middleware(['auth:api', Authorize::class . ':report.read']);
        $router->get('/authorize/report', [TestAuthorizeController::class, 'report'])->middleware('auth:api');
    }

    private function actAs(array $permissions): void
    {
        $this->actingAs(new GembootUser(['id' => 7], permissions: $permissions), 'api');
    }

    function test_off_by_default()
    {
        config()->set('gemboot.authorization.permissions_as_abilities', false);
        $this->actAs(['report.read']);

        $this->assertFalse(Gate::allows('report.read'));
    }

    function test_permissions_answer_ability_checks()
    {
        $this->actAs(['report.read']);

        $this->assertTrue(Gate::allows('report.read'));
        $this->assertFalse(Gate::allows('report.export'));
    }

    function test_the_can_route_middleware_uses_them()
    {
        $this->actAs(['report.read']);
        $this->get('/can/report')->assertOk();

        $this->actAs([]);
        $this->get('/can/report')->assertStatus(403);
    }

    function test_unknown_permissions_are_asked_from_the_auth_service()
    {
        $fake = GembootAuth::fake(permissions: ['report.read']);

        $this->get('/can/report', ['Authorization' => 'Bearer t1'])->assertOk();

        $fake->assertCalled('has-permission-to', 1);
    }

    function test_gates_defined_by_the_app_win()
    {
        $this->actAs(['report.read']);
        Gate::define('report.read', fn () => false);

        $this->assertFalse(Gate::allows('report.read'));
    }

    function test_checks_with_arguments_are_left_to_policies()
    {
        $this->actAs(['update']);

        $this->assertFalse(Gate::allows('update', [new \stdClass]));
    }

    function test_other_users_and_guests_are_left_alone()
    {
        $fake = GembootAuth::fake(permissions: ['report.read']);

        $this->assertFalse(Gate::allows('report.read'));

        $this->actingAs(new GenericUser(['id' => 1]), 'api');
        $this->assertFalse(Gate::allows('report.read'));

        $fake->assertNotCalled('has-permission-to');
    }

    function test_authorize_inside_a_gemboot_callback_answers_403()
    {
        $this->actAs(['report.read']);
        $this->getJson('/authorize/report')->assertOk()->assertJsonPath('data.report', 'ok');

        $this->actAs([]);
        $this->getJson('/authorize/report')
            ->assertStatus(403)
            ->assertJsonPath('status', 403)
            ->assertJsonPath('data.error', 'This action is unauthorized.');
    }
}

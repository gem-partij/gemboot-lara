<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Facades\GembootAuthFacade as GembootAuth;
use Gemboot\Middleware\TokenValidated;
use Gemboot\Tests\Controllers\TestUserFormRequestController;
use Gemboot\Tests\Controllers\TestUserFormRequestValidatedOnlyController;
use Gemboot\Tests\Controllers\TestUserNotAFormRequestController;
use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Models\TestUserOpen;
use Gemboot\Tests\Requests\StoreTestUserRequest;
use Gemboot\Tests\Requests\UpdateTestUserRequest;
use Gemboot\Tests\TestCase;

class GembootFormRequestTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config()->set('gemboot.auth.base_api', 'http://auth.test/api/auth/');
        TestUserFormRequestController::$hookRequests = [];

        $router = $this->app['router'];
        $router->apiResource('form/users', TestUserFormRequestController::class)->only(['store', 'update']);
        $router->apiResource('form-validated/users', TestUserFormRequestValidatedOnlyController::class)->only(['store']);
        $router->apiResource('form-logged-in/users', TestUserFormRequestController::class)->only(['store'])
            ->middleware(TokenValidated::class);
        $router->apiResource('not-a-form/users', TestUserNotAFormRequestController::class)->only(['store']);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Ana',
            'email' => 'Ana@Example.test',
            'password' => 'secret',
        ], $override);
    }

    function test_store_validates_with_the_form_request_and_saves_its_prepared_input()
    {
        $this->postJson('/form/users', $this->payload())->assertOk();

        // prepareForValidation() lowercased the email before it was saved.
        $this->assertSame('ana@example.test', TestUser::first()->email);
        // The hooks receive the FormRequest, so they can call validated() too.
        $this->assertSame([StoreTestUserRequest::class], TestUserFormRequestController::$hookRequests);
    }

    function test_failed_rules_answer_400_like_validate_store_request()
    {
        $response = $this->postJson('/form/users', $this->payload(['email' => 'not-an-email']));

        $response->assertStatus(400)
            ->assertJsonPath('status', 400)
            ->assertJsonStructure(['data' => ['errors' => ['email']]]);
        $this->assertSame(0, TestUser::count());
    }

    function test_denied_authorize_answers_403()
    {
        $response = $this->postJson('/form/users', $this->payload(), ['X-Test-Deny' => 'yes']);

        $response->assertStatus(403)
            ->assertJsonPath('status', 403)
            ->assertJsonPath('data.error', 'This action is unauthorized.');
        $this->assertSame(0, TestUser::count());
    }

    function test_update_uses_the_update_request_and_its_route_parameter()
    {
        $user = TestUser::factory()->create(['email' => 'ana@example.test']);
        TestUser::factory()->create(['email' => 'taken@example.test']);

        // Its own email passes the unique rule, because the rule ignores the record in the route.
        $this->putJson("/form/users/{$user->id}", ['name' => 'Ana B', 'email' => 'ana@example.test'])->assertOk();
        $this->assertSame('Ana B', $user->refresh()->name);
        $this->assertSame([UpdateTestUserRequest::class], TestUserFormRequestController::$hookRequests);

        $this->putJson("/form/users/{$user->id}", ['email' => 'taken@example.test'])
            ->assertStatus(400)
            ->assertJsonStructure(['data' => ['errors' => ['email']]]);
    }

    function test_save_validated_only_uses_the_form_request_rules()
    {
        $this->app->bind(TestUser::class, TestUserOpen::class);

        $this->postJson('/form-validated/users', $this->payload(['remember_token' => 'chosen-by-client']))->assertOk();

        $user = TestUser::first();
        $this->assertSame('ana@example.test', $user->email);
        $this->assertNull($user->remember_token);
    }

    function test_the_merged_user_login_is_not_saved()
    {
        // An open model would try to save a user_login column and fail.
        $this->app->bind(TestUser::class, TestUserOpen::class);
        GembootAuth::fake(user: ['id' => 7]);

        $this->postJson('/form-logged-in/users', $this->payload(), ['Authorization' => 'Bearer t1'])->assertOk();

        $this->assertSame(1, TestUser::count());
    }

    function test_a_class_that_is_not_a_form_request_fails_closed()
    {
        $this->postJson('/not-a-form/users', $this->payload())->assertStatus(500);

        $this->assertSame(0, TestUser::count());
    }
}

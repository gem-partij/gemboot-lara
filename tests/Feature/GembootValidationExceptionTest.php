<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Tests\Controllers\TestValidateController;
use Gemboot\Tests\TestCase;

class GembootValidationExceptionTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $router = $this->app['router'];
        $router->post('/validate/request', [TestValidateController::class, 'requestValidate']);
        $router->post('/validate/validator', [TestValidateController::class, 'validatorValidate']);
        $router->post('/validate/gemboot', [TestValidateController::class, 'gembootRules']);
    }

    function test_request_validate_in_a_callback_answers_400_with_the_errors()
    {
        $this->postJson('/validate/request', ['email' => 'not-an-email'])
            ->assertStatus(400)
            ->assertJsonPath('status', 400)
            ->assertJsonStructure(['data' => ['error' => ['name', 'email']]]);
    }

    function test_validator_validate_in_a_callback_answers_400()
    {
        $this->postJson('/validate/validator', [])
            ->assertStatus(400)
            ->assertJsonStructure(['data' => ['error' => ['name']]]);
    }

    function test_the_answer_matches_gemboots_own_validation()
    {
        $payload = ['email' => 'not-an-email'];

        $this->assertSame(
            $this->postJson('/validate/gemboot', $payload)->json(),
            $this->postJson('/validate/request', $payload)->json()
        );
    }

    function test_valid_input_still_succeeds()
    {
        $this->postJson('/validate/request', ['name' => 'Ana', 'email' => 'ana@example.test'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ana');
    }
}

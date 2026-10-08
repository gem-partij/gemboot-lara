<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Tests\Controllers\TestAuthorizeController;
use Gemboot\Tests\TestCase;

class GembootAuthorizationExceptionTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $router = $this->app['router'];
        $router->get('/authorize/not-yours', [TestAuthorizeController::class, 'notYours']);
        $router->get('/authorize/hidden', [TestAuthorizeController::class, 'hidden']);
    }

    function test_a_denial_answers_403_with_its_message()
    {
        $this->getJson('/authorize/not-yours')
            ->assertStatus(403)
            ->assertJsonPath('status', 403)
            ->assertJsonPath('data.error', 'Not yours');
    }

    function test_a_denial_keeps_the_status_the_policy_chose()
    {
        $this->getJson('/authorize/hidden')->assertStatus(404)->assertJsonPath('status', 404);
    }
}

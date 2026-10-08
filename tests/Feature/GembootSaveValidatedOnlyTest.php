<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Tests\Controllers\TestUserController;
use Gemboot\Tests\Controllers\TestUserValidatedNoRulesController;
use Gemboot\Tests\Controllers\TestUserValidatedOnlyController;
use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Models\TestUserOpen;
use Gemboot\Tests\TestCase;

class GembootSaveValidatedOnlyTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // A model that accepts any field, as with $guarded = [].
        $this->app->bind(TestUser::class, TestUserOpen::class);

        $router = $this->app['router'];
        $router->apiResource('open/users', TestUserController::class)->only(['store']);
        $router->apiResource('validated/users', TestUserValidatedOnlyController::class)->only(['store', 'update']);
        $router->apiResource('norules/users', TestUserValidatedNoRulesController::class)->only(['store']);
    }

    private function payload(): array
    {
        return [
            'name' => 'Ana',
            'email' => 'ana@example.test',
            'password' => 'secret',
            // A field the client must not be able to set.
            'remember_token' => 'chosen-by-client',
        ];
    }

    function test_without_the_option_any_field_reaches_an_open_model()
    {
        // Today's default behavior, shown for contrast.
        $this->postJson('/open/users', $this->payload())->assertOk();

        $this->assertSame('chosen-by-client', TestUser::first()->remember_token);
    }

    function test_with_the_option_only_validated_fields_are_saved()
    {
        $this->postJson('/validated/users', $this->payload())->assertOk();

        $user = TestUser::first();
        $this->assertSame('Ana', $user->name);
        $this->assertNull($user->remember_token);
        // Fixed values from $merge_store_data_with still apply.
        $this->assertNotNull($user->email_verified_at);
    }

    function test_update_saves_only_fields_with_an_update_rule()
    {
        $user = TestUser::factory()->create(['name' => 'Old', 'email' => 'old@example.test']);

        $this->putJson("/validated/users/{$user->id}", ['name' => 'New', 'email' => 'changed@example.test'])->assertOk();

        $user->refresh();
        $this->assertSame('New', $user->name);
        $this->assertSame('old@example.test', $user->email);
    }

    function test_option_without_rules_fails_closed()
    {
        $this->postJson('/norules/users', $this->payload())->assertStatus(500);

        $this->assertSame(0, TestUser::count());
    }
}

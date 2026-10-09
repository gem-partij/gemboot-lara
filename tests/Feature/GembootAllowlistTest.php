<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Models\TestUserListed;
use Gemboot\Tests\Models\TestUserListedRelationsOnly;
use Gemboot\Tests\TestCase;

class GembootAllowlistTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // TestUserController and TestUserService resolve TestUser from the container.
        $this->app->bind(TestUser::class, TestUserListed::class);
    }

    function test_listed_fields_can_be_searched()
    {
        TestUserListed::factory()->create(['name' => 'Ana', 'email' => 'ana@example.test']);
        TestUserListed::factory()->create(['name' => 'Budi', 'email' => 'budi@example.test']);

        $this->getJson('/test/users?search=Ana&search_field=name')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/test/users?search=Ana&search_field=selfTyped.name')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/test/users?search=' . now()->format('Y-m') . '&search_field=created_at')->assertOk()->assertJsonPath('data.total', 2);
    }

    function test_fields_that_are_not_listed_are_rejected()
    {
        TestUserListed::factory()->create();

        $this->getJson('/test/users?search=ana&search_field=email')->assertStatus(400);
        $this->getJson('/test/users?search_exact=1&search_field=id')->assertStatus(400);
        $this->getJson('/test/users?search[]=x&search_field[]=email')->assertStatus(400);
        $this->getJson('/test/users?search=x&search_field=selfTyped.email')->assertStatus(400);
        $this->getJson('/test/users?search=2026&search_field=updated_at')->assertStatus(400);
    }

    function test_standard_filters_follow_the_list()
    {
        config()->set('gemboot.query.standard_parameters', true);
        TestUserListed::factory()->create(['name' => 'Ana']);

        $this->getJson('/test/users?filter[name]=Ana')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/test/users?filter[email]=ana@example.test')->assertStatus(400);
    }

    function test_search_without_a_field_looks_only_in_listed_columns()
    {
        TestUserListed::factory()->create(['name' => 'Ana', 'email' => 'ana@example.test']);

        $this->getJson('/test/users?search=Ana')->assertOk()->assertJsonPath('data.total', 1);
        // "example" is only in the email, which isn't listed.
        $this->getJson('/test/users?search=example')->assertOk()->assertJsonPath('data.total', 0);
    }

    function test_search_without_a_field_finds_nothing_when_no_column_is_listed()
    {
        TestUserListedRelationsOnly::factory()->create(['name' => 'Ana']);

        $this->assertCount(0, TestUserListedRelationsOnly::query()->search('Ana')->get());
        $this->assertCount(0, TestUserListedRelationsOnly::query()->searchExact('Ana')->get());
    }

    function test_listed_columns_can_be_sorted()
    {
        TestUserListed::factory()->create(['name' => 'Budi']);
        TestUserListed::factory()->create(['name' => 'Ana']);

        $this->getJson('/test/users?order=name')->assertOk()->assertJsonPath('data.data.0.name', 'Ana');
        $this->getJson('/test/users?order=gemboot_test_user.name&atoz=desc')->assertOk()->assertJsonPath('data.data.0.name', 'Budi');
    }

    function test_columns_that_are_not_listed_cannot_be_sorted()
    {
        TestUserListed::factory()->create();

        $this->getJson('/test/users?order=email')->assertStatus(400);

        config()->set('gemboot.query.standard_parameters', true);
        $this->getJson('/test/users?sort=-email')->assertStatus(400);
    }

    function test_models_without_lists_behave_as_before()
    {
        $this->app->bind(TestUser::class, TestUser::class);
        TestUser::factory()->create(['name' => 'Ana', 'email' => 'ana@example.test']);

        $this->getJson('/test/users?search=ana&search_field=email')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/test/users?search=example')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/test/users?order=email')->assertOk();
    }
}

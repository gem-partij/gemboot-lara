<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Exceptions\BadRequestException;
use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Models\TestUserHidden;
use Gemboot\Tests\TestCase;

class GembootSearchSafetyTest extends TestCase
{
    protected function useHiddenModel(): void
    {
        // TestUserController and TestUserService resolve TestUser from the container.
        $this->app->bind(TestUser::class, TestUserHidden::class);
    }

    function test_explicit_search_on_hidden_column_is_rejected()
    {
        $this->useHiddenModel();
        TestUserHidden::factory()->create();

        // LIKE '$2y$10$a%' on password used to reveal the hash one character at a time.
        $this->getJson('/test/users?search=$2y$10$a%25&search_field=password')->assertStatus(400);
        $this->getJson('/test/users?search_exact=x&search_field=remember_token')->assertStatus(400);
        $this->getJson('/test/users?search[]=x&search_field[]=password')->assertStatus(400);
    }

    function test_search_through_relation_on_hidden_column_is_rejected()
    {
        TestUserHidden::factory()->create();

        $this->expectException(BadRequestException::class);
        TestUserHidden::query()->search('x', 'selfHidden.password', 'and')->get();
    }

    function test_search_without_field_skips_hidden_columns()
    {
        $user = TestUserHidden::factory()->create(['name' => 'Visible Name']);
        $hash = $user->getAttributes()['password'];

        $this->assertCount(1, TestUserHidden::query()->search('Visible')->get());
        $this->assertCount(0, TestUserHidden::query()->search(substr($hash, 0, 12))->get());

        // Same data through a model without $hidden still finds it: only $hidden matters.
        $this->assertCount(1, TestUser::query()->search(substr($hash, 0, 12))->get());
    }

    function test_order_by_hidden_column_is_rejected()
    {
        $this->useHiddenModel();
        TestUserHidden::factory()->count(2)->create();

        $this->getJson('/test/users?order=password')->assertStatus(400);
        $this->getJson('/test/users?order=name')->assertStatus(200);
    }

    function test_invalid_sort_direction_returns_400()
    {
        TestUser::factory()->count(2)->create();

        // orderBy() threw InvalidArgumentException, which surfaced as a 500.
        $this->getJson('/test/users?order=name&atoz=sideways')->assertStatus(400);
        $this->getJson('/test/users?order=name&atoz=DESC')->assertStatus(200);
    }

    function test_page_len_is_limited_by_config()
    {
        TestUser::factory()->count(5)->create();
        config()->set('gemboot.pagination.max_page_len', 2);

        $this->getJson('/test/users?page_len=100')->assertJsonPath('data.per_page', 2);
        $this->getJson('/test/users?page_len=1')->assertJsonPath('data.per_page', 1);
    }

    function test_page_len_defaults_and_unlimited_setting()
    {
        TestUser::factory()->count(3)->create();

        $this->getJson('/test/users?page_len=abc')->assertJsonPath('data.per_page', 30);
        $this->getJson('/test/users?page_len=-5')->assertJsonPath('data.per_page', 30);

        config()->set('gemboot.pagination.max_page_len', null);
        $this->getJson('/test/users?page_len=5000')->assertJsonPath('data.per_page', 5000);
    }
}

<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Models\TestUserHidden;
use Gemboot\Tests\TestCase;

class GembootStandardQueryParametersTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        TestUser::factory()->create(['name' => 'Beta', 'email' => 'beta@example.test']);
        TestUser::factory()->create(['name' => 'Alpha', 'email' => 'alpha@example.test']);
        TestUser::factory()->create(['name' => 'Gamma', 'email' => 'gamma@example.test']);
    }

    private function enable(): void
    {
        config()->set('gemboot.query.standard_parameters', true);
    }

    private function names(string $query): array
    {
        return collect($this->getJson('/test/users?' . $query)->assertOk()->json('data.data'))->pluck('name')->all();
    }

    function test_ignored_unless_turned_on()
    {
        // Clients may already send these names; 8.x keeps ignoring them by default.
        $this->assertSame(['Beta', 'Alpha', 'Gamma'], $this->names('sort=-name&filter[name]=Alpha'));
        $this->assertSame(30, $this->getJson('/test/users?per_page=1')->json('data.per_page'));
    }

    function test_sort()
    {
        $this->enable();

        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $this->names('sort=name'));
        $this->assertSame(['Gamma', 'Beta', 'Alpha'], $this->names('sort=-name'));
        $this->assertSame(['Gamma', 'Beta', 'Alpha'], $this->names('sort=-name,email'));
        // order/atoz still win when present.
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $this->names('sort=-name&order=name&atoz=asc'));
    }

    function test_per_page()
    {
        $this->enable();

        $this->assertSame(1, $this->getJson('/test/users?per_page=1')->json('data.per_page'));
        $this->assertSame(2, $this->getJson('/test/users?per_page=1&page_len=2')->json('data.per_page'));

        config()->set('gemboot.pagination.max_page_len', 2);
        $this->assertSame(2, $this->getJson('/test/users?per_page=50')->json('data.per_page'));
    }

    function test_filter()
    {
        $this->enable();

        $this->assertSame(['Alpha'], $this->names('filter[name]=Alpha'));
        $this->assertSame([], $this->names('filter[name]=Alpha&filter[email]=beta@example.test'));
        $this->assertSame(['Alpha'], $this->names('filter[name]=Alpha&search=alpha&search_field=email'));
        $this->assertSame(['Beta'], $this->names('filter[selfTyped.name]=Beta'));
    }

    function test_invalid_input_is_a_400()
    {
        $this->enable();

        $this->getJson('/test/users?filter[name][]=Alpha')->assertStatus(400);
        $this->getJson('/test/users?filter[truncate.id]=1')->assertStatus(400);
        $this->getJson('/test/users?sort=nonexistent')->assertStatus(400);
    }

    function test_hidden_columns_cannot_be_filtered_or_sorted()
    {
        // Bound before the first request: Laravel builds the controller once per app.
        $this->enable();
        $this->app->bind(TestUser::class, TestUserHidden::class);

        $this->getJson('/test/users?filter[password]=x')->assertStatus(400);
        $this->getJson('/test/users?sort=password')->assertStatus(400);
    }

    function test_order_by_an_unknown_column_is_a_400_not_a_500()
    {
        // Before 8.7, an unknown ?order= column reached the database and failed.
        $this->getJson('/test/users?order=nonexistent')->assertStatus(400);
        $this->getJson('/test/users?order=gemboot_test_user.name')->assertOk();
    }
}

<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Services\TestUserService;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Request;

class GembootDefaultOrderTest extends TestCase
{
    protected function names($list): array
    {
        return collect($list->items())->pluck('name')->all();
    }

    function test_order_by_is_the_default_sort()
    {
        // $orderBy was accepted by controllers and services but never applied.
        TestUser::factory()->create(['name' => 'Beta']);
        TestUser::factory()->create(['name' => 'Alpha']);
        TestUser::factory()->create(['name' => 'Gamma']);

        $descending = new TestUserService(null, [], ['name' => 'desc']);
        $this->assertSame(['Gamma', 'Beta', 'Alpha'], $this->names($descending->listAll()));

        $ascending = new TestUserService(null, [], ['name']);
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $this->names($ascending->listAll()));
    }

    function test_controller_order_by_property_sorts_index()
    {
        $this->app['router']->get('/sorted/users', [\Gemboot\Tests\Controllers\TestUserSortedController::class, 'index']);
        TestUser::factory()->create(['name' => 'Alpha']);
        TestUser::factory()->create(['name' => 'Beta']);

        $this->getJson('/sorted/users')
            ->assertJsonPath('data.data.0.name', 'Beta')
            ->assertJsonPath('data.data.1.name', 'Alpha');
    }

    function test_order_parameter_overrides_the_default_sort()
    {
        TestUser::factory()->create(['name' => 'Beta']);
        TestUser::factory()->create(['name' => 'Alpha']);
        $this->app->instance('request', Request::create('/test/users?order=name&atoz=asc', 'GET'));

        $service = new TestUserService(null, [], ['name' => 'desc']);

        $this->assertSame(['Alpha', 'Beta'], $this->names($service->listAll()));
    }
}

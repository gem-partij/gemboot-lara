<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\Models\TestUser;
use Gemboot\Tests\Services\TestUserService;
use Gemboot\Tests\TestCase;

class GembootServiceStateTest extends TestCase
{
    protected function userData(string $email): array
    {
        return ['name' => 'User', 'email' => $email, 'password' => 'secret'];
    }

    function test_store_twice_creates_two_rows()
    {
        // store() used to fill and save $this->model itself, so the second call
        // updated the row created by the first.
        $service = new TestUserService;

        $first = $service->store($this->userData('a@example.test'));
        $second = $service->store($this->userData('b@example.test'));

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, TestUser::count());
    }

    function test_store_after_queries_on_the_same_service()
    {
        // findOrFail() and listAll() used to replace $this->model with a query
        // builder, and store() then called fill() on the builder.
        $service = new TestUserService;
        $existing = $service->store($this->userData('a@example.test'));

        $service->findOrFail($existing->id);
        $service->listAll();
        $service->countAll();

        $service->store($this->userData('b@example.test'));
        $this->assertSame(2, TestUser::count());
    }

    function test_store_keeps_attributes_preset_on_the_model()
    {
        $service = new TestUserService(new TestUser(['name' => 'Preset Name']));

        $first = $service->store(['email' => 'a@example.test', 'password' => 'secret']);
        $second = $service->store(['email' => 'b@example.test', 'password' => 'secret']);

        $this->assertSame('Preset Name', $first->name);
        $this->assertSame('Preset Name', $second->name);
    }

    function test_filters_do_not_leak_between_requests()
    {
        // Laravel reuses the controller (and its service) between requests in one
        // app. The second request used to inherit the first one's search.
        TestUser::factory()->create(['name' => 'Alpha']);
        TestUser::factory()->create(['name' => 'Beta']);

        $this->assertCount(1, $this->getJson('/test/users?search=Alpha&search_field=name')->json('data.data'));
        $this->assertCount(2, $this->getJson('/test/users')->json('data.data'));
    }

    function test_eager_loads_do_not_accumulate()
    {
        $service = (new TestUserService)->setWith(['selfTyped']);
        $user = $service->store($this->userData('a@example.test'));

        $service->findOrFail($user->id);
        $service->setWith([]);

        $this->assertFalse($service->findOrFail($user->id)->relationLoaded('selfTyped'));
    }
}

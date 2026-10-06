<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Gemboot\Tests\Models\TestUser;

class GembootControllerCacheTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
    }

    function test_show_cache_is_per_record()
    {
        // Before the fix the show cache key ignored $id, so /users/2 returned user 1.
        $first = TestUser::factory()->create();
        $second = TestUser::factory()->create();

        $this->getJson("/test-cached/users/{$first->id}")->assertJsonPath('data.id', $first->id);
        $this->getJson("/test-cached/users/{$second->id}")->assertJsonPath('data.id', $second->id);
    }

    function test_index_cache_keeps_parameter_names_apart()
    {
        // The old key joined only the values, so both requests below became
        // "gemboot_test_user_index_Alpha_name" and the second got the first's result.
        TestUser::factory()->create(['name' => 'Alpha']);
        TestUser::factory()->create(['name' => 'Beta']);

        $this->assertCount(1, $this->getJson('/test-cached/users?search=Alpha&search_field=name')->json('data.data'));
        $this->assertCount(2, $this->getJson('/test-cached/users?foo=Alpha&bar=name')->json('data.data'));
    }

    function test_index_cache_handles_nested_input()
    {
        // TokenValidated merges the user_login array into the request; implode() failed on it.
        TestUser::factory()->create();

        $this->getJson('/test-cached/users?user_login[id]=1&user_login[name]=Ana')->assertStatus(200);
        $this->getJson('/test-cached/users/1?user_login[id]=1')->assertStatus(200);
    }
}

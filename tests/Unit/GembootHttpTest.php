<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;
use Gemboot\Tests\Models\TestUser;

class GembootHttpTest extends TestCase
{

    /** ================
     * TEST REQUEST GET ALL DATA (PAGINATION)
     *
     * @test
     **/
    public function test_get_all_data()
    {
        $response = $this->getJson('/test/users');
        // ob_get_clean();

        $response
            ->assertStatus(200)
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('status')
                    ->has('message')
                    ->has('data')
                    ->has('data.current_page')
                    ->has('data.data')
                    ->whereType('data.data', ['array', 'null'])
                    ->has('data.from')
                    ->has('data.last_page')
                    ->has('data.per_page')
                    ->has('data.to')
                    ->has('data.total')
            );
    }

    /** ================
     * TEST REQUEST STORE DATA
     *
     * @test
     **/
    public function test_store_data()
    {
        $data = TestUser::factory()->make();

        $response = $this->postJson('/test/users', $data->toArray());
        // ob_get_clean();

        $response
            ->assertStatus(200)
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('status')
                    ->has('message')
                    ->has('data')
                    ->has('data.saved')
                    ->has('data.saved.id')
                    ->has('data.saved.name')
                    ->has('data.saved.email')
            );
    }

    /** ================
     * TEST REQUEST SHOW DATA BY ID
     *
     * @test
     **/
    public function test_show_data_by_id()
    {
        $data = TestUser::factory()->create();

        $response = $this->getJson('/test/users/' . $data->id);
        // ob_get_clean();

        $response
            ->assertStatus(200)
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('status')
                    ->has('message')
                    ->has('data')
                    ->has('data.id')
                    ->has('data.name')
                    ->has('data.email')
            );
    }

    /** ================
     * TEST REQUEST UPDATE DATA
     *
     * @test
     **/
    public function test_update_data()
    {
        $data = TestUser::factory()->create();
        $data_update = TestUser::factory()->make();

        $response = $this->putJson('/test/users/' . $data->id, $data_update->toArray());
        // ob_get_clean();

        $response
            ->assertStatus(200)
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('status')
                    ->has('message')
                    ->has('data')
                    ->has('data.saved')
                    ->has('data.saved.id')
                    ->has('data.saved.name')
                    ->has('data.saved.email')
            );
    }

    /** ================
     * TEST REQUEST DELETE DATA
     *
     * @test
     **/
    public function test_delete_data()
    {
        $data = TestUser::factory()->create();

        $response = $this->deleteJson('/test/users/' . $data->id);
        // ob_get_clean();

        $response
            ->assertStatus(200)
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('status')
                    ->has('message')
                    ->has('data')
                    ->has('data.deleted')
                    ->has('data.deleted.id')
                    ->has('data.deleted.name')
                    ->has('data.deleted.email')
            );
    }
}

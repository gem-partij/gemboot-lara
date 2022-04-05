<?php
namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Gemboot\Tests\Models\TestUser;

class GembootModelTest extends TestCase {

    /** @test */
    public function model_can_be_instantiated() {
        $user = TestUser::factory()->make();
        $this->assertTrue(true);
    }

    /** @test */
    public function model_can_be_persisted() {
        $user = TestUser::factory()->count(5)->create();
        $this->assertCount(5, $user);
    }

    /** @test */
    public function model_has_an_email() {
        $user = TestUser::factory()->create([
            'email' => 'anggerpputro@gmail.com',
        ]);
        $this->assertEquals('anggerpputro@gmail.com', $user->email);
    }

}

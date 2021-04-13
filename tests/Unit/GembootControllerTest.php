<?php
namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;

class GembootControllerTest extends TestCase {

    /** @test */
    public function access_index() {
        $response = $this->get('/test');
        $response->assertStatus(200);
    }

}

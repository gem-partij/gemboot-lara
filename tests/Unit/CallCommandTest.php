<?php
namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class CallCommandTest extends TestCase {

    /** @test */
    public function it_call_gemboot_test() {
        Artisan::call('gemboot:test');
        $this->assertTrue(true);
    }

}

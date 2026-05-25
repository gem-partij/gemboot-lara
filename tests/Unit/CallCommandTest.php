<?php
namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;

class CallCommandTest extends TestCase {

    #[Test]
    public function it_call_gemboot_test() {
        Artisan::call('gemboot:test');
        $this->assertTrue(true);
    }

}

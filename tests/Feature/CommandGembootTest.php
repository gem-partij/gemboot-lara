<?php
namespace Gemboot\Tests\Unit;

// use Gemboot\Tests\TestCase;
use Tests\TestCase;
use Gemboot\Commands\GembootTest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Application;

use GembootResponse;

class CommandGembootTest extends TestCase {

    /** @test **/
    function call_gemboot_command() {
        // Application::starting(function ($artisan) {
        //     $artisan->add(app(GembootTest::class));
        // });
        //
        // Artisan::call('gemboot:test');

        $this->assertTrue(true);
    }

    function call_gemboot_facade() {
        $response = GembootResponse::successOrException(function() {
            return [
                'OK'
            ];
        });

        // dd($response);
    }

}

<?php
namespace Gemboot\Tests\Feature;

use Gemboot\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class MakeControllerCommandTest extends TestCase {

    /** @test */
    public function it_call_basic_make() {
        // destination path of the Foo class
        $fooController = app_path('Http/Controllers/Api/MyFooController.php');

        // make sure we're starting from a clean state
        if(File::exists($fooController)) {
            unlink($fooController);
        }

        $this->assertFalse(File::exists($fooController));

        // Run the make command
        Artisan::call('gemboot:make-controller MyFooController');

        // Assert a new file is created
        $this->assertTrue(File::exists($fooController));
    }

}

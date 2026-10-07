<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class MakeModelCommandTest extends TestCase
{
    function test_make_model_controller_without_service_does_not_reference_a_missing_service()
    {
        // --controller without --service generated a controller using GadgetService,
        // which was never created.
        $model = app_path('Models/Gadget.php');
        $plainModel = app_path('Gadget.php');
        $controller = app_path('Http/Controllers/Api/GadgetController.php');
        $service = app_path('Services/GadgetService.php');
        foreach ([$model, $plainModel, $controller, $service] as $file) {
            File::delete($file);
        }

        try {
            Artisan::call('gemboot:make-model', ['name' => 'Gadget', '--controller' => true]);

            $this->assertTrue(File::exists($controller));
            $this->assertFalse(File::exists($service));
            $this->assertStringNotContainsString('GadgetService', File::get($controller));
        } finally {
            foreach ([$model, $plainModel, $controller, $service] as $file) {
                File::delete($file);
            }
        }
    }
}

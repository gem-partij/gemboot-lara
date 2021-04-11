<?php
namespace Gemboot\Tests;

use Gemboot\GembootServiceProvider;

class TestCase extends \PHPUnit\Framework\TestCase {

    public function setUp(): void {
        parent::setUp();
    }

    protected function getPackageProviders($app) {
        return [
            GembootServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app) {

    }

}

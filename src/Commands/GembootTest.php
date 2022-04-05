<?php
namespace Gemboot\Commands;

use Illuminate\Console\Command;

class GembootTest extends Command {

    protected $signature = "gemboot:test";

    protected $description = "mung test tok";


    public function handle() {
        $this->comment("OK");
    }
}

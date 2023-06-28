<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;

use Illuminate\Support\Facades\Notification;
use Gemboot\Notifications\Telegram;
use Gemboot\Libraries\TelegramLibrary;

class GembootNotificationTest extends TestCase
{

    function test_send()
    {
        if (!env('TEST_NOTIFICATION')) {
            return $this->assertTrue(true);
        }

        // $notif = (object)[
        //     'content' => "*NEW ERROR CATCH:*\nbla bla bla",
        // ];
        // $response = Notification::notify(new Telegram($notif));

        $response = (new TelegramLibrary)->send("<pre>bla bla bla</pre>");
        // dd($response);

        $assert = false;
        if ($response) {
            $assert = true;
        }

        $this->assertTrue($assert);
    }

    function test_500()
    {
        if (!env('TEST_NOTIFICATION')) {
            return $this->assertTrue(true);
        }

        $response = $this->getJson('/http-status/500');

        $response
            ->assertStatus(500);
    }
}

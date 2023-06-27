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
        // $notif = (object)[
        //     'content' => "*NEW ERROR CATCH:*\nbla bla bla",
        // ];
        // $response = Notification::notify(new Telegram($notif));

        $response = (new TelegramLibrary)->send("<pre>bla bla bla</pre>");
        // dd($response);

        $this->assertTrue(true);
    }
}

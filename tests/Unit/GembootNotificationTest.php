<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;

use Illuminate\Support\Facades\Notification;
use Gemboot\Notifications\Telegram;

class GembootNotificationTest extends TestCase
{

    function test_send()
    {
        $notif = (object)[
            'content' => "*NEW ERROR CATCH:*\nbla bla bla",
        ];
        $response = Notification::notify(new Telegram($notif));
        dd($response);

        $this->assertTrue(true);
    }
}

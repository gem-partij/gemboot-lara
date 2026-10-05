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
        // Test ini memanggil API Telegram yang sesungguhnya, sehingga hanya
        // dijalankan apabila diaktifkan secara eksplisit beserta kredensialnya.
        if (!env('TEST_NOTIFICATION')) {
            $this->markTestSkipped('Set TEST_NOTIFICATION=true to run against the real Telegram API.');
        }

        if (!env('GEMBOOT_TELEGRAM_BOT_TOKEN') || !env('GEMBOOT_TELEGRAM_CHAT_ID')) {
            $this->markTestSkipped('GEMBOOT_TELEGRAM_BOT_TOKEN and GEMBOOT_TELEGRAM_CHAT_ID are required.');
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
        // Tanpa token bot, responseException() tidak mengirim notifikasi,
        // sehingga test ini aman dijalankan tanpa akses jaringan.
        $response = $this->getJson('/http-status/500');

        $response
            ->assertStatus(500);
    }
}

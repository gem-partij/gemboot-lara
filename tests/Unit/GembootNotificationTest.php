<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;

use Illuminate\Support\Facades\Log;
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

    function test_500_skips_notification_when_token_is_placeholder()
    {
        // Config yang dipublish tanpa mengisi env masih memuat placeholder.
        // Placeholder tidak boleh memicu pemanggilan API Telegram.
        config()->set('gemboot.notifications.telegram.token', 'YOUR BOT TOKEN HERE');
        config()->set('gemboot.notifications.telegram.chat_id', 'YOUR TELEGRAM CHAT ID HERE');
        Log::spy();

        $response = $this->getJson('/http-status/500');

        $response->assertStatus(500);
        Log::shouldNotHaveReceived('warning');
    }

    function test_500_reads_debug_flag_from_config()
    {
        // env('APP_DEBUG') bernilai null setelah config:cache, sehingga trace
        // harus ditentukan oleh config('app.debug').
        config()->set('app.debug', true);
        $this->getJson('/http-status/500')
            ->assertStatus(500)
            ->assertJsonPath('data.error', 'TEST 500 EXCEPTION')
            ->assertJsonStructure(['data' => ['error', 'trace']]);

        config()->set('app.debug', false);
        $this->getJson('/http-status/500')
            ->assertStatus(500)
            ->assertJsonPath('data.error', 'TEST 500 EXCEPTION')
            ->assertJsonMissingPath('data.trace');
    }
}

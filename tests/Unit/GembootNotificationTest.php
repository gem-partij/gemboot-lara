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
        // This test calls the real Telegram API, so it only runs when enabled
        // explicitly and credentials are provided.
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
        // Without a bot token, responseException() sends no notification,
        // so this test is safe to run without network access.
        $response = $this->getJson('/http-status/500');

        $response
            ->assertStatus(500);
    }

    function test_500_skips_notification_when_token_is_placeholder()
    {
        // A published config without the env var set still holds the placeholder.
        // The placeholder must not trigger a call to the Telegram API.
        config()->set('gemboot.notifications.telegram.token', 'YOUR BOT TOKEN HERE');
        config()->set('gemboot.notifications.telegram.chat_id', 'YOUR TELEGRAM CHAT ID HERE');
        Log::spy();

        $response = $this->getJson('/http-status/500');

        $response->assertStatus(500);
        Log::shouldNotHaveReceived('warning');
    }

    function test_500_reads_debug_flag_from_config()
    {
        // env('APP_DEBUG') returns null after config:cache, so the trace
        // must be driven by config('app.debug').
        config()->set('app.debug', true);
        $this->getJson('/http-status/500')
            ->assertStatus(500)
            ->assertJsonPath('data.error', 'TEST 500 EXCEPTION')
            ->assertJsonStructure(['data' => ['error', 'trace']]);

        // Without debug, unexpected exceptions only get a generic message: their
        // text can carry internals such as SQL, host, and database name.
        config()->set('app.debug', false);
        $this->getJson('/http-status/500')
            ->assertStatus(500)
            ->assertJsonPath('data.error', 'Internal Server Error')
            ->assertJsonMissingPath('data.trace');
    }
}

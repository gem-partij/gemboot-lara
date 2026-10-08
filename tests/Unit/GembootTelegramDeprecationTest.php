<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Libraries\TelegramLibrary;
use Gemboot\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class GembootTelegramDeprecationTest extends TestCase
{
    // The notice is reported once per process; run in a fresh one.
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    function test_using_telegram_library_reports_a_deprecation_once()
    {
        $notices = [];
        set_error_handler(function ($level, $message) use (&$notices) {
            if ($level === E_USER_DEPRECATED) {
                $notices[] = $message;
            }

            return true;
        });

        try {
            new TelegramLibrary();
            new TelegramLibrary();
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $notices);
        $this->assertStringContainsString('removed in gemboot-lara 9.0', $notices[0]);
    }

    function test_doctor_warns_when_telegram_alerts_are_configured()
    {
        config()->set('gemboot.auth.base_api', 'https://auth.example.test/api/auth');
        config()->set('gemboot.notifications.telegram.token', 'some-token');

        Artisan::call('gemboot:doctor', ['--skip-network' => true]);

        $this->assertStringContainsString('Telegram error alerts (GEMBOOT_TELEGRAM_BOT_TOKEN) are deprecated', Artisan::output());
    }
}

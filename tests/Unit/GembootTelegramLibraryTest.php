<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Libraries\TelegramLibrary;
use Gemboot\Tests\TestCase;

class GembootTelegramLibraryTest extends TestCase
{
    function test_empty_token_does_not_throw()
    {
        // The constructor used to build the bot right away, and the Telegram SDK
        // throws when the token is empty.
        config()->set('gemboot.notifications.telegram.token', null);
        config()->set('gemboot.notifications.telegram.chat_id', null);

        $this->assertFalse((new TelegramLibrary)->send('hello'));
    }

    function test_exception_message_is_html_escaped_and_sent_without_delay()
    {
        config()->set('gemboot.notifications.telegram.token', 'test-token');
        config()->set('gemboot.notifications.telegram.chat_id', '123');
        config()->set('app.name', 'App <Beta>');

        $library = new class extends TelegramLibrary {
            public array $sent = [];

            protected function bot()
            {
                $library = $this;

                return new class($library) {
                    public function __construct(private $library)
                    {
                    }

                    public function sendMessage(array $params)
                    {
                        $this->library->sent[] = $params;
                        return true;
                    }

                    public function sendChatAction(array $params)
                    {
                        throw new \RuntimeException('sendChatAction should not be called');
                    }
                };
            }
        };

        $started = microtime(true);
        $library->sendExceptionMessage(new \Exception('Expected <div> but got </span>'));

        // sendMessage used to call sendChatAction() and sleep(1) first.
        $this->assertLessThan(0.5, microtime(true) - $started);
        $this->assertCount(1, $library->sent);

        $text = $library->sent[0]['text'];
        $this->assertStringContainsString('Expected &lt;div&gt; but got &lt;/span&gt;', $text);
        $this->assertStringContainsString('App &lt;Beta&gt;', $text);
        $this->assertSame('HTML', $library->sent[0]['parse_mode']);
    }
}

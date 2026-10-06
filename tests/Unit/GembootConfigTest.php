<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Libraries\AuthLibrary;
use Gemboot\Libraries\HttpClient;
use Gemboot\Tests\TestCase;

class GembootConfigTest extends TestCase
{
    protected function guzzleConfig(HttpClient $client, string $option)
    {
        $property = new \ReflectionProperty(HttpClient::class, 'client');

        return $property->getValue($client)->getConfig($option);
    }

    function test_package_defaults_are_merged_without_publishing()
    {
        // Before 8.1.0 there was no mergeConfigFrom, so every key was null unless
        // the consumer published config/gemboot.php.
        $this->assertTrue(config('gemboot.http.verify'));
        $this->assertEquals(30, config('gemboot.http.timeout'));
        $this->assertEquals(10, config('gemboot.http.connect_timeout'));
        $this->assertEquals(0, config('gemboot.auth.cache_ttl'));
        $this->assertEquals(1000, config('gemboot.pagination.max_page_len'));

        // Defaults that must stay "off" so merging changes nothing for consumers
        // who never published the config.
        $this->assertFalse(config('gemboot.response.compressed'));
        $this->assertNull(config('gemboot.notifications.telegram.token'));
    }

    function test_tls_verification_is_on_by_default()
    {
        // HttpClient used to hardcode 'verify' => false.
        $client = new HttpClient('https://auth.example.test/');

        $this->assertTrue($this->guzzleConfig($client, 'verify'));
        $this->assertEquals(30, $this->guzzleConfig($client, 'timeout'));
        $this->assertEquals(10, $this->guzzleConfig($client, 'connect_timeout'));
    }

    function test_tls_and_timeouts_follow_config()
    {
        config()->set('gemboot.http.verify', '/etc/ssl/custom-ca.pem');
        config()->set('gemboot.http.timeout', 5);
        config()->set('gemboot.http.connect_timeout', 2);

        $client = new HttpClient('https://auth.example.test/');

        $this->assertSame('/etc/ssl/custom-ca.pem', $this->guzzleConfig($client, 'verify'));
        $this->assertEquals(5, $this->guzzleConfig($client, 'timeout'));
        $this->assertEquals(2, $this->guzzleConfig($client, 'connect_timeout'));

        config()->set('gemboot.http.verify', false);
        $this->assertFalse($this->guzzleConfig(new HttpClient('https://auth.example.test/'), 'verify'));
    }

    function test_old_published_config_without_new_keys_uses_safe_defaults()
    {
        // A config/gemboot.php published before 8.1.0 has an "auth" section without
        // the new nested keys. mergeConfigFrom only merges top-level keys, so the
        // code must fall back to its own defaults.
        config()->set('gemboot.auth', ['base_api' => 'https://auth.example.test/']);
        config()->set('gemboot.http', null);

        $client = new HttpClient('https://auth.example.test/');
        $this->assertTrue($this->guzzleConfig($client, 'verify'));
        $this->assertEquals(10, $this->guzzleConfig($client, 'connect_timeout'));
    }

    function test_placeholder_base_api_falls_back_to_legacy_config()
    {
        // Configs published before 8.1.0 hold a placeholder string. It must still
        // count as "not set", so the 3.x-era gemboot_auth.base_api keeps working.
        config()->set('gemboot.auth.base_api', 'YOUR GEMBOOT AUTH BASE API HERE');
        config()->set('gemboot_auth.base_api', 'https://legacy-auth.example.test/api');

        $baseUrl = (new \ReflectionProperty(AuthLibrary::class, 'baseUrlAuth'))->getValue(new AuthLibrary());

        $this->assertSame('https://legacy-auth.example.test/api/', $baseUrl);
    }

    function test_all_class_aliases_are_registered_through_package_discovery()
    {
        // Laravel only reads extra.laravel.aliases. 17 aliases used to sit in a
        // top-level extra.aliases and were never registered.
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'), true);

        $this->assertArrayNotHasKey('aliases', $composer['extra']);

        $aliases = $composer['extra']['laravel']['aliases'];
        $this->assertCount(22, $aliases);
        foreach ($aliases as $alias => $class) {
            $this->assertTrue(class_exists($class), "{$alias} points to missing class {$class}");
        }
        $this->assertArrayHasKey('GembootResourceController', $aliases);
        $this->assertArrayHasKey('GembootNotFoundException', $aliases);
    }
}

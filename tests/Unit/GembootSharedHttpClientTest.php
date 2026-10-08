<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Facades\GembootAuthFacade as GembootAuth;
use Gemboot\Libraries\HttpClient;
use Gemboot\Tests\TestCase;

class GembootSharedHttpClientTest extends TestCase
{
    private function guzzle(HttpClient $client)
    {
        return (new \ReflectionProperty(HttpClient::class, 'client'))->getValue($client);
    }

    function test_clients_with_the_same_settings_share_one_guzzle_client()
    {
        // Each AuthLibrary (one per middleware) used to build its own Guzzle client,
        // so connections to the auth service were never reused.
        $a = new HttpClient('https://auth.example.test/');
        $b = new HttpClient('https://auth.example.test/');

        $this->assertSame($this->guzzle($a), $this->guzzle($b));
    }

    function test_different_settings_get_different_clients()
    {
        $base = $this->guzzle(new HttpClient('https://auth.example.test/'));

        $this->assertNotSame($base, $this->guzzle(new HttpClient('https://other.example.test/')));

        config()->set('gemboot.http.timeout', 5);
        $this->assertNotSame($base, $this->guzzle(new HttpClient('https://auth.example.test/')));
    }

    function test_the_fake_is_never_shared()
    {
        $real = $this->guzzle(new HttpClient('https://auth.example.test/'));
        GembootAuth::fake();

        $faked = $this->guzzle(new HttpClient('https://auth.example.test/'));

        $this->assertNotSame($real, $faked);
        $this->assertNotSame($faked, $this->guzzle(new HttpClient('https://auth.example.test/')));
    }
}

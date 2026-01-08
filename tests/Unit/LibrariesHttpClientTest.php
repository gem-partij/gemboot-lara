<?php
namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Gemboot\Libraries\HttpClient;

class LibrariesHttpClientTest extends TestCase
{

    protected $baseUrlTest = "https://tools-httpstatus.pickup-services.com";

    /** @test */
    public function return_ok()
    {
        $response = (new HttpClient($this->baseUrlTest))->get("/200");
        $this->assertEquals(200, $response->info->http_code);
    }

    /** @test */
    public function return_4x()
    {
        $response = (new HttpClient($this->baseUrlTest))->get("/400");
        $this->assertEquals(400, $response->info->http_code);
    }

    /** @test */
    public function return_5x()
    {
        $response = (new HttpClient($this->baseUrlTest))->get("/500");
        $this->assertEquals(500, $response->info->http_code);
    }

    /** @test */
    public function throw_http_error()
    {
        try {
            $response = (new HttpClient($this->baseUrlTest))->throwOnHttpError()->get("/400");
            // $this->assertEquals(400, $response->info->http_code);
        } catch (HttpException $he) {
            $this->assertEquals(400, $he->getStatusCode());
        } catch (\Exception $e) {
            // var_dump($e->getMessage());
            $this->assertEquals("Bad Request", $e->getMessage());
        }
    }

}

<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Gemboot\FileHandler\FileHandler;
use Illuminate\Testing\Fluent\AssertableJson;

class GembootFileHandlerTest extends TestCase
{

    /** ================
     * TEST TOKEN
     *
     * @test
     **/
    public function test_token()
    {
        if (!env('TEST_FILE_HANDLER')) {
            return $this->assertTrue(true);
        }

        $response_auth = $this->postJson('/auth/login', [
            'npp' => 'tester',
            'password' => 'tester',
            'hwid' => 'gemboot',
        ]);

        $auth_data = $response_auth->getData()->data;

        // $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxMCIsImp0aSI6IjM1YzE2YmUzMWMzYzE0MDUyYWJjMTQxY2U2OTJjNmFlNjlmYzVkYzJmYmIzOTY3OTY3YmQ5ZWQyYjBiMGJkYmY3ZWNlYmRiNjlkMzQ4NjEyIiwiaWF0IjoxNjcyMTA0Nzk5LjAwNDU5MywibmJmIjoxNjcyMTA0Nzk5LjAwNDU5NSwiZXhwIjoxNzAzNjQwNzk4Ljk5NzA5Miwic3ViIjoiODQ3Iiwic2NvcGVzIjpbXX0.O8MC_j4GoZ_smGo2-dvN6klT6mJ4fuMHE_t5CRmHLDhFhw1OMo7--ExAJUlNGFqaClgBYmGAgO7kqQvZBfGy9RL8xwFG-vaiEaDvumhTfR2LCtFZnUTRqkBVXinTs0lrH34K8K9a7YWkf6NpTaxsHuJajOq67B6vsiSiNDSNwfvUTMebtUAikLIxJCn9kfqNmq4wspXWwwnTGxJ4f5mY-uhlvnrRijZ41CWaa34dYE4qXozJs8T3EcvjlVfaNCQPwygne5yLO-MLCEaXz9ASIQwNX-7Ua0LSJMlyerVokgRmnzmooUkqPpjsY0960fhTv8qXrciuPzTSve15_Nks8G4ac0LTX_TPApoL71Utf8xVgpTI8AwUuQ7d5KRSaNPvi-XJ_KDURmB7RUy-poBFzV0oTudW5M9w0KaanbPWXCfXHO0RDKHeN0WOnQUY37HM0eRawS61Lw5zlEdeUBdN_rD_xAWZ2xmhualKIXv1M1l4Kg7t89KTaZK6Kww0jfGy6tTmBYfJWwa_0jtwqGZoOcyav9ohdF2PTpUVtGjajA9oIrJMVKDxp9fcw8KWr6JhQOSZ70gERvCEtO7w6A1eY4_fkLW21bzDEulaINNmCNdAFmeAJhKFtw2Oks3dwf_pUMG1n-9WGDj0zlB0SH4gKE-jLAUSMcbALkc_D6Z_k74";
        $token =  $auth_data->access_token;

        $fileHandler = new FileHandler(null);
        $response = $fileHandler->setToken($token)->ping();

        if ($response->ok()) {
            $this->assertTrue(true);
        } else {
            $this->assertTrue(false);
        }
    }
}

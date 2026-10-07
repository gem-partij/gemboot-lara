<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Libraries\AuthLibrary;
use Gemboot\Tests\Support\UsesFakeAuthServer;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Request;

class GembootAuthLibraryJsonTest extends TestCase
{
    use UsesFakeAuthServer;

    function test_auth_library_json_routes_answer_503_during_an_outage()
    {
        // With $response_json = true the auth service's status code is reused; without
        // a connection it is 0, which is not a valid status and used to cause a 500.
        config()->set('gemboot.auth.base_api', 'http://127.0.0.1:1/');
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer good');

        $response = (new AuthLibrary)->me(true, $request);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('Auth service unavailable', $response->getData(true)['data']['error']);
    }

    function test_auth_library_json_routes_still_pass_answers_through()
    {
        config()->set('gemboot.auth.base_api', static::$fakeAuthUrl);
        $good = Request::create('/');
        $good->headers->set('Authorization', 'Bearer good');
        $bad = Request::create('/');
        $bad->headers->set('Authorization', 'Bearer bad');

        $this->assertSame(200, (new AuthLibrary)->me(true, $good)->getStatusCode());
        $this->assertSame(401, (new AuthLibrary)->me(true, $bad)->getStatusCode());
    }
}

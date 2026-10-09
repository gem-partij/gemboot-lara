<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Events\AuthServiceUnavailable;
use Gemboot\Facades\GembootAuthFacade as GembootAuth;
use Gemboot\Libraries\AuthLibrary;
use Gemboot\Middleware\HasRole;
use Gemboot\Middleware\TokenValidated;
use Gemboot\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class GembootOutageGraceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('gemboot.auth.base_api', 'http://auth.test/api/auth/');
        config()->set('gemboot.auth.outage_grace', 60);

        $router = $this->app['router'];
        $router->get('/me', fn (Request $r) => response()->json($r->user_login))->middleware(TokenValidated::class);
        $router->get('/admin', fn () => 'ok')->middleware([TokenValidated::class, HasRole::class . ':admin']);
    }

    private function asToken(string $token = 't1'): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    function test_off_by_default_an_outage_answers_503()
    {
        config()->set('gemboot.auth.outage_grace', 0);
        $fake = GembootAuth::fake(user: ['id' => 7]);
        $this->get('/me', $this->asToken())->assertOk();

        $fake->outage();
        $this->get('/me', $this->asToken())->assertStatus(503);
    }

    function test_the_last_good_answer_covers_an_outage()
    {
        Event::fake([AuthServiceUnavailable::class]);
        $fake = GembootAuth::fake(user: ['id' => 7], roles: ['admin']);
        $this->get('/admin', $this->asToken())->assertOk();

        $fake->outage();
        $this->get('/me', $this->asToken())->assertOk()->assertJsonPath('id', 7);
        $this->get('/admin', $this->asToken())->assertOk();

        // Still reported, so alerts fire even while users keep working.
        Event::assertDispatched(AuthServiceUnavailable::class, fn ($e) => $e->servedFromLastGoodAnswer === true);
    }

    function test_it_expires_after_the_grace_period()
    {
        $fake = GembootAuth::fake();
        $this->get('/me', $this->asToken())->assertOk();
        $fake->outage();

        $this->travel(59)->seconds();
        $this->get('/me', $this->asToken())->assertOk();

        $this->travel(2)->seconds();
        $this->get('/me', $this->asToken())->assertStatus(503);
    }

    function test_the_grace_period_starts_when_the_cached_answer_expires()
    {
        config()->set('gemboot.auth.cache_ttl', 30);
        $fake = GembootAuth::fake();
        $this->get('/me', $this->asToken())->assertOk();
        $fake->outage();

        // 30 seconds of cache, then 60 seconds of grace.
        $this->travel(85)->seconds();
        $this->get('/me', $this->asToken())->assertOk();

        $this->travel(6)->seconds();
        $this->get('/me', $this->asToken())->assertStatus(503);
    }

    function test_rejections_are_never_reused()
    {
        $fake = GembootAuth::fake()->rejectTokens();
        $this->get('/me', $this->asToken())->assertStatus(401);

        $fake->rejectTokens(false)->outage();
        $this->get('/me', $this->asToken())->assertStatus(503);
    }

    function test_each_token_has_its_own_last_answer()
    {
        $fake = GembootAuth::fake();
        $this->get('/me', $this->asToken('t1'))->assertOk();

        $fake->outage();
        $this->get('/me', $this->asToken('t2'))->assertStatus(503);
    }

    function test_logout_clears_the_last_good_answer()
    {
        $fake = GembootAuth::fake();
        $this->get('/me', $this->asToken())->assertOk();

        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer t1');
        (new AuthLibrary)->logout(false, $request);

        $fake->outage();
        $this->get('/me', $this->asToken())->assertStatus(503);
    }
}

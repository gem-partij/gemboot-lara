<?php

namespace Gemboot\Facades;

use Gemboot\Testing\FakeAuthService;
use Illuminate\Support\Facades\Facade;

class GembootAuthFacade extends Facade
{
    /**
     * Replace the central auth service with an in-memory fake, for tests.
     *
     *     GembootAuth::fake(user: ['id' => 7], roles: ['admin'], permissions: ['report.read']);
     *
     * Requests with any bearer token are the given user; requests without one get 401.
     */
    public static function fake(array $user = ['id' => 1], array $roles = [], array $permissions = []): FakeAuthService
    {
        $fake = new FakeAuthService($user, $roles, $permissions);

        static::$app->instance(FakeAuthService::class, $fake);
        $fake->registerSsoStub();

        return $fake;
    }

    /**
     * A fake auth service that can't be reached: protected routes answer 503.
     */
    public static function fakeOutage(): FakeAuthService
    {
        return static::fake()->outage();
    }

    protected static function getFacadeAccessor()
    {
        return 'gemboot-auth';
    }
}

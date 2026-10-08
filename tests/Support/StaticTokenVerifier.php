<?php

namespace Gemboot\Tests\Support;

use Gemboot\Auth\GembootUser;
use Gemboot\Auth\TokenVerifier;

/**
 * A custom verifier for tests: fixed users per token, like an app that binds
 * its own TokenVerifier. Counts its calls.
 */
class StaticTokenVerifier implements TokenVerifier
{
    public int $calls = 0;

    /** @param array<string, GembootUser> $users */
    public function __construct(private array $users)
    {
    }

    public function verify(string $token): ?GembootUser
    {
        $this->calls++;

        return $this->users[$token] ?? null;
    }
}

<?php

namespace Gemboot\Auth;

/**
 * Answers one question for the gemboot guard: who does this token belong to?
 *
 * The default, RemoteTokenVerifier, asks the auth service's `me` endpoint. Bind
 * your own implementation to TokenVerifier::class to answer differently, e.g.
 * with roles and permissions, so role: and permission: can answer locally.
 */
interface TokenVerifier
{
    /**
     * The user the token belongs to, or null when the token is invalid.
     *
     * @param string $token the bearer token, without the "Bearer " prefix
     *
     * @throws \Gemboot\Exceptions\ServiceUnavailableException when the answer
     *         can't be determined (auth service unreachable or failing)
     */
    public function verify(string $token): ?GembootUser;
}

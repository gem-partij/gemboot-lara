<?php

namespace Gemboot\Auth;

use Gemboot\Exceptions\ServiceUnavailableException;
use Gemboot\Libraries\AuthLibrary;
use Illuminate\Http\Request;

/**
 * Verifies a token by asking the auth service's `me` endpoint, the same call
 * token-validated makes. Answers are cached per token when
 * gemboot.auth.cache_ttl is above 0, and GembootAuth::fake() answers in tests.
 */
class RemoteTokenVerifier implements TokenVerifier
{
    public function verify(string $token): ?GembootUser
    {
        // AuthLibrary reads the token from a request.
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $auth = new AuthLibrary();
        $answer = $auth->me(false, $request);

        if (!$answer) {
            if ($auth->isAuthServiceUnavailable()) {
                throw new ServiceUnavailableException('Auth service unavailable');
            }

            return null;
        }

        return GembootUser::fromAuthService((array) $answer);
    }
}

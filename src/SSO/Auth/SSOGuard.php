<?php

namespace Gemboot\SSO\Auth;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class SSOGuard implements Guard
{
    protected $request;
    protected $provider;
    protected $user;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    /**
     * Mengecek apakah user terautentikasi.
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }

    /**
     * Mengecek apakah guest (tidak login).
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Mendapatkan ID dari user.
     */
    public function id()
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * Validasi kredensial. Tidak digunakan karena kita pakai token.
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    /**
     * Set user secara manual (jarang dipakai di SSO).
     */
    public function setUser(Authenticatable $user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Mengembalikan user yang terautentikasi.
     */
    public function user(): ?Authenticatable
    {
        if ($this->user) return $this->user;

        // Ambil token dari header Authorization
        $token = $this->request->bearerToken();
        if (!$token) return null;

        $cacheKey = 'sso_token_' . sha1($token);

        $cached = Cache::get($cacheKey);
        if ($cached) {
            $this->user = new SSOUser($cached);
            return $this->user;
        }

        try {
            $validateTokenUrl = config('gemboot.sso.validate_token_url') ?? config('gemboot.sso.auth_service_url') . '/validate-token';
            $getUserUrl = config('gemboot.sso.get_user_url') ?? config('gemboot.sso.user_service_url') . "/user/me";

            // Validasi token ke auth-service (pakai HTTP atau gRPC)
            $validate = Http::withToken($token)
                ->get($validateTokenUrl);

            if (!$validate->ok()) return null;

            $userId = $validate->json()['user_id'] ?? null;
            if (!$userId) return null;

            // Get data user ke user-service (pakai HTTP atau gRPC)
            $userResponse = Http::withToken($token)
                ->get($getUserUrl, [
                    'showRoles' => 'true',
                    'showPermissions' => 'true',
                ]);

            if (!$userResponse->ok()) return null;

            $userResponseJSON = $userResponse->json();
            $userData = isset($userResponseJSON['user']) ? $userResponseJSON['user'] : $userResponseJSON;
            $userData['roles'] = isset($userResponseJSON['roles']) ? $userResponseJSON['roles'] : null;
            $userData['permissions'] = isset($userResponseJSON['permissions']) ? $userResponseJSON['permissions'] : null;

            Cache::put($cacheKey, $userData, now()->addSeconds(config('gemboot.sso.cache_ttl', 300)));

            $this->user = new SSOUser($userData);
            return $this->user;
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    /**
     * Mengecek apakah sudah ada user yang diautentikasi.
     */
    public function hasUser(): bool
    {
        return !is_null($this->user);
    }
}

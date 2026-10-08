<?php

namespace Gemboot\Commands;

use Gemboot\Libraries\HttpClient;
use Gemboot\Middleware\HasPermissionTo;
use Gemboot\Middleware\HasRole;
use Gemboot\Middleware\TokenValidated;
use Gemboot\Support\SecurityHeaders;
use Illuminate\Console\Command;

/**
 * Checks the Gemboot setup and explains how to fix what is wrong.
 *
 * Most Gemboot misconfigurations fail silently (every request answers 401, or
 * caching quietly does nothing), so this command makes them visible.
 */
class Doctor extends Command
{
    protected $signature = 'gemboot:doctor
                            {--skip-network : Do not contact the auth service}';

    protected $description = 'Check the Gemboot setup and explain how to fix problems';

    private const PLACEHOLDERS = ['YOUR GEMBOOT AUTH BASE API HERE'];

    private int $errors = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        // Laravel reuses the command object within one process.
        $this->errors = 0;
        $this->warnings = 0;

        $this->line('<options=bold>Gemboot setup check</>');
        $this->newLine();

        $baseApi = $this->checkAuthServiceUrl();
        if ($baseApi) {
            $this->checkTransportSecurity($baseApi);
            if (!$this->option('skip-network')) {
                $this->checkAuthServiceReachable($baseApi);
            }
        }
        $this->checkSsoGuard();
        $this->checkMiddlewareAliases();
        $this->checkClassAliases();
        $this->checkCache();
        $this->checkResponseSettings();

        $this->newLine();
        if ($this->errors > 0) {
            $this->line("<fg=red>{$this->errors} problem(s) and {$this->warnings} warning(s) found.</>");

            return self::FAILURE;
        }
        if ($this->warnings > 0) {
            $this->line("<fg=yellow>No problems, {$this->warnings} warning(s).</>");

            return self::SUCCESS;
        }
        $this->line('<info>Everything looks good.</info>');

        return self::SUCCESS;
    }

    private function checkAuthServiceUrl(): ?string
    {
        $baseApi = config('gemboot.auth.base_api');

        if (empty($baseApi) || in_array($baseApi, self::PLACEHOLDERS, true)) {
            if ($this->ssoGuardNames()) {
                $this->note('GEMBOOT_AUTH_BASE_API is not set. Only needed for the auth middleware and AuthLibrary; your SSO guard does not use it.');
            } else {
                $this->problem(
                    'GEMBOOT_AUTH_BASE_API is not set. The auth middleware will reject every request with 401.',
                    'Set GEMBOOT_AUTH_BASE_API in .env, e.g. https://auth.example.com/api/auth'
                );
            }

            return null;
        }

        if (!filter_var($baseApi, FILTER_VALIDATE_URL)) {
            $this->problem("GEMBOOT_AUTH_BASE_API is not a valid URL: {$baseApi}", 'Use a full URL including https://');

            return null;
        }

        $this->ok("Auth service URL: {$baseApi}");

        return $baseApi;
    }

    private function checkTransportSecurity(string $baseApi): void
    {
        $host = (string) parse_url($baseApi, PHP_URL_HOST);
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if (str_starts_with(strtolower($baseApi), 'http://') && !$local) {
            $this->warning(
                'The auth service URL uses http://, so tokens and passwords travel unencrypted.',
                'Use https:// for GEMBOOT_AUTH_BASE_API.'
            );
        }

        $verify = config('gemboot.http.verify', true);
        if ($verify === false) {
            $this->warning(
                'TLS certificate checks are off (GEMBOOT_HTTP_VERIFY=false).',
                'Only acceptable for local development. Point GEMBOOT_HTTP_VERIFY at your CA bundle instead.'
            );
        } elseif (is_string($verify) && !is_file($verify)) {
            $this->problem("GEMBOOT_HTTP_VERIFY points to a file that does not exist: {$verify}", 'Fix the path to your CA bundle.');
        } else {
            $this->ok('TLS certificates are checked.');
        }
    }

    private function checkAuthServiceReachable(string $baseApi): void
    {
        $base = rtrim($baseApi, '/') . '/';
        $response = (new HttpClient($base))->get('me');
        $code = (int) ($response->info->http_code ?? 0);

        if ($code === 0) {
            $this->problem(
                'Cannot reach the auth service: ' . ($response->error ?? 'no connection') . '. Protected routes will answer 503.',
                'Check the URL, the network, firewalls, and the certificate settings (GEMBOOT_HTTP_VERIFY).'
            );
        } elseif ($code >= 500) {
            $this->warning("The auth service answered HTTP {$code}. Protected routes answer 503 while this lasts.", 'Check the auth service.');
        } else {
            $this->ok("The auth service answers (HTTP {$code} for a request without a token).");
        }
    }

    private function checkSsoGuard(): void
    {
        $guards = $this->ssoGuardNames();
        if (!$guards) {
            return;
        }

        $list = implode(', ', $guards);
        if (empty(config('gemboot.sso.get_user_url')) && empty(config('gemboot.sso.user_service_url'))) {
            $this->problem(
                "The SSO guard ({$list}) has no user service URL, so every request will be unauthenticated.",
                'Set GEMBOOT_USER_SERVICE_URL or GEMBOOT_SSO_GET_USER_URL in .env.'
            );
        } else {
            $this->ok("SSO guard configured: {$list}.");
        }
    }

    private function checkMiddlewareAliases(): void
    {
        $registered = array_values(app('router')->getMiddleware());
        $ours = array_intersect([TokenValidated::class, HasRole::class, HasPermissionTo::class], $registered);

        if (count($ours) === 3) {
            $this->ok('Auth middleware aliases are registered.');
        } else {
            $this->note('Not all auth middleware aliases are registered (token-validated, role, permission). Fine if you use the class names directly; see docs/INSTALLATION.md.');
        }
    }

    private function checkClassAliases(): void
    {
        if (class_exists('GembootResourceController') && class_exists('GembootNotFoundException')) {
            $this->ok('Class aliases are registered.');

            return;
        }

        $this->problem(
            'Class aliases such as GembootResourceController are not registered. Generated controllers will fail with "Class not found".',
            'Make sure package discovery is not disabled for gem-partij/gemboot-lara ("dont-discover" in composer.json), then run composer dump-autoload.'
        );
    }

    private function checkCache(): void
    {
        $store = config('cache.default');

        if (cache()->supportsTags()) {
            $this->ok("Cache store '{$store}' supports tags, so service caching and automatic clearing work.");
        } else {
            $this->note("Cache store '{$store}' has no tag support. CoreService caching (setObserver) is skipped, and controller caches only expire by time. Use redis or memcached if you need them.");
        }

        $maxFailed = \Gemboot\Support\FailedAuthLimiter::max();
        if ($maxFailed > 0) {
            $this->note("Failed authentication attempts are limited to {$maxFailed} per client IP per minute (429 after that). Behind a proxy or load balancer, configure TrustProxies so the real client IP is used.");
        } else {
            $this->note('The limit on failed authentication attempts is off (GEMBOOT_AUTH_MAX_FAILED_ATTEMPTS=0).');
        }

        if ((int) config('gemboot.auth.cache_ttl', 0) > 0) {
            $this->note('Auth answers are cached (GEMBOOT_AUTH_CACHE_TTL). Revoked tokens keep working until their entries expire.');
        }
    }

    private function checkResponseSettings(): void
    {
        if (config('gemboot.response.compressed')) {
            $this->warning(
                'GEMBOOT_RESPONSE_COMPRESSED is on. It is deprecated, removed in 9.0, and breaks under Octane.',
                'Turn it off and let the web server compress responses.'
            );
        }

        $telegramToken = config('gemboot.notifications.telegram.token');
        if (!empty($telegramToken) && $telegramToken !== 'YOUR BOT TOKEN HERE') {
            $this->warning(
                'Telegram error alerts (GEMBOOT_TELEGRAM_BOT_TOKEN) are deprecated and will be removed in 9.0.',
                'Forward errors with a Laravel log channel instead, e.g. Monolog\Handler\TelegramBotHandler (see docs/RESPONSES.md, "Error alerts").'
            );
        }

        if (SecurityHeaders::get() === []) {
            $this->note('Security headers are off (GEMBOOT_SECURITY_HEADERS=false).');
        } else {
            $this->ok('Security headers are added to responses.');
        }

        if (app()->configurationIsCached()) {
            $this->note('Configuration is cached. Run php artisan config:cache again after changing .env.');
        }
    }

    /**
     * Names of the guards that use Gemboot's SSO driver.
     */
    private function ssoGuardNames(): array
    {
        $names = [];
        foreach ((array) config('auth.guards', []) as $name => $guard) {
            if (($guard['driver'] ?? null) === 'gemboot-sso-token') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function ok(string $message): void
    {
        $this->line("  <info>✓</info> {$message}");
    }

    private function note(string $message): void
    {
        $this->line("  <fg=cyan>i</> {$message}");
    }

    private function warning(string $message, string $fix): void
    {
        $this->warnings++;
        $this->line("  <fg=yellow>!</> {$message}");
        $this->line("      <fg=gray>Fix:</> {$fix}");
    }

    private function problem(string $message, string $fix): void
    {
        $this->errors++;
        $this->line("  <fg=red>✗</> {$message}");
        $this->line("      <fg=gray>Fix:</> {$fix}");
    }
}

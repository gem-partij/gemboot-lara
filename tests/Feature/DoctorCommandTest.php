<?php

namespace Gemboot\Tests\Feature;

use Gemboot\Middleware\HasPermissionTo;
use Gemboot\Middleware\HasRole;
use Gemboot\Middleware\TokenValidated;
use Gemboot\Tests\Support\UsesFakeAuthServer;
use Gemboot\Tests\TestCase;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class DoctorCommandTest extends TestCase
{
    use UsesFakeAuthServer;

    public function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
    }

    /**
     * What Laravel's package discovery does in a real app (Testbench skips it).
     */
    protected function registerAppSetup(): void
    {
        AliasLoader::getInstance([
            'GembootResourceController' => \Gemboot\Controllers\CoreRestResourceController::class,
            'GembootNotFoundException' => \Gemboot\Exceptions\NotFoundException::class,
        ])->register();

        $router = $this->app['router'];
        $router->aliasMiddleware('token-validated', TokenValidated::class);
        $router->aliasMiddleware('role', HasRole::class);
        $router->aliasMiddleware('permission', HasPermissionTo::class);
    }

    protected function doctor(array $options = []): array
    {
        $code = Artisan::call('gemboot:doctor', $options);

        return [$code, Artisan::output()];
    }

    function test_reports_everything_fine_for_a_good_setup()
    {
        $this->registerAppSetup();
        config()->set('gemboot.auth.base_api', static::$fakeAuthUrl);

        [$code, $output] = $this->doctor();

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('The auth service answers (HTTP 401', $output);
        $this->assertStringContainsString('Everything looks good.', $output);
    }

    function test_missing_auth_url_is_a_problem()
    {
        $this->registerAppSetup();
        config()->set('gemboot.auth.base_api', null);

        [$code, $output] = $this->doctor();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('GEMBOOT_AUTH_BASE_API is not set', $output);
        $this->assertStringContainsString('Fix: Set GEMBOOT_AUTH_BASE_API', $output);
    }

    function test_unreachable_auth_service_is_a_problem_unless_network_is_skipped()
    {
        $this->registerAppSetup();
        config()->set('gemboot.auth.base_api', 'http://127.0.0.1:1/');

        [$code, $output] = $this->doctor();
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Cannot reach the auth service', $output);

        [$code, $output] = $this->doctor(['--skip-network' => true]);
        $this->assertSame(0, $code, $output);
        $this->assertStringNotContainsString('Cannot reach', $output);
    }

    function test_insecure_settings_are_warnings()
    {
        $this->registerAppSetup();
        config()->set('gemboot.auth.base_api', 'http://auth.example.test/api/auth');
        config()->set('gemboot.http.verify', false);
        config()->set('gemboot.response.compressed', true);

        [$code, $output] = $this->doctor(['--skip-network' => true]);

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('uses http://', $output);
        $this->assertStringContainsString('TLS certificate checks are off', $output);
        $this->assertStringContainsString('GEMBOOT_RESPONSE_COMPRESSED is on', $output);
        $this->assertStringContainsString('3 warning(s)', $output);
    }

    function test_missing_ca_bundle_is_a_problem()
    {
        $this->registerAppSetup();
        config()->set('gemboot.auth.base_api', 'https://auth.example.test/api/auth');
        config()->set('gemboot.http.verify', '/nowhere/ca.pem');

        [$code, $output] = $this->doctor(['--skip-network' => true]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('does not exist: /nowhere/ca.pem', $output);
    }

    function test_sso_guard_without_user_service_is_a_problem()
    {
        $this->registerAppSetup();
        config()->set('gemboot.auth.base_api', null);
        config()->set('auth.guards.api', ['driver' => 'gemboot-sso-token', 'provider' => 'gemboot-sso']);
        config()->set('gemboot.sso.get_user_url', null);
        config()->set('gemboot.sso.user_service_url', null);

        [$code, $output] = $this->doctor();

        $this->assertSame(1, $code);
        // Without the middleware, a missing GEMBOOT_AUTH_BASE_API is only a note.
        $this->assertStringContainsString('your SSO guard does not use it', $output);
        $this->assertStringContainsString('The SSO guard (api) has no user service URL', $output);
    }

    // class_alias() can't be undone, so aliases registered by other tests would
    // still exist; run this one in a fresh PHP process.
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    function test_missing_class_aliases_are_a_problem()
    {
        // Testbench doesn't run package discovery, so the aliases are missing here,
        // as in an app that disabled discovery for this package.
        config()->set('gemboot.auth.base_api', 'https://auth.example.test/api/auth');

        [$code, $output] = $this->doctor(['--skip-network' => true]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Class aliases such as GembootResourceController are not registered', $output);
    }

    function test_cache_store_without_tags_is_explained()
    {
        $this->registerAppSetup();
        config()->set('gemboot.auth.base_api', 'https://auth.example.test/api/auth');
        config()->set('cache.default', 'file');

        [, $output] = $this->doctor(['--skip-network' => true]);

        $this->assertStringContainsString("Cache store 'file' has no tag support", $output);
    }
}

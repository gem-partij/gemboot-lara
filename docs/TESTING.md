# Testing your API

After this guide, you'll be able to test your Gemboot API's protected routes without a running auth service: logged-in users, roles, permissions, and even outages.

## The problem

Your routes use `token-validated`, `role:`, and `permission:`. In a test, those middleware would call your real auth service. That's slow, needs the network, and you can't easily test "user without the admin role" or "auth service is down".

## The fake auth service

`GembootAuth::fake()` replaces the auth service with an in-memory stand-in for the current test:

```php
use GembootAuth;

public function test_admins_can_list_users(): void
{
    GembootAuth::fake(user: ['id' => 7, 'name' => 'Ana'], roles: ['admin']);

    $this->getJson('/api/users', ['Authorization' => 'Bearer any-token'])
        ->assertOk();
}
```

How it behaves:

- **Any bearer token is the fake user.** The token's value doesn't matter.
- **A request without a token is rejected (401)**, like a real auth service would.
- `role:` and `permission:` are answered from the `roles` and `permissions` you pass.
- Everything else is real: the middleware, `user_login` on the request, the 401/403/503 answers, and the auth cache.

The fake is cleared automatically before the next test. While it's active, the [limit on failed attempts](AUTH.md#protection-against-token-floods-and-password-guessing) is off, so tests that check 401s many times never get a 429.

### Keep tests away from the real auth service

The fake only answers calls to the URLs Gemboot knows about. If your `.env` points `GEMBOOT_AUTH_BASE_API` or the SSO URLs at a real server, a typo or a missing setting in a test can still reach it. Two lines in `setUp()` rule that out:

```php
protected function setUp(): void
{
    parent::setUp();

    // Hosts that don't exist, so nothing can reach the real auth service.
    config([
        'gemboot.auth.base_api' => 'http://auth.test/api/auth',
        'gemboot.sso.get_user_url' => 'http://users.test/user/me',
    ]);

    // Any other HTTP call made with Laravel's Http client fails the test.
    Http::preventStrayRequests();
}
```

`GembootAuth::fake()` works together with `Http::preventStrayRequests()`: the calls it answers aren't stray.

## Common tests

**Not logged in:**

```php
GembootAuth::fake();

$this->getJson('/api/users')->assertStatus(401);
```

**Missing role or permission:**

```php
GembootAuth::fake(roles: ['editor']);

$this->getJson('/api/users', ['Authorization' => 'Bearer x'])->assertStatus(403);
```

**The current user in your code:**

```php
GembootAuth::fake(user: ['id' => 7]);

$this->postJson('/api/orders', ['product_id' => 3], ['Authorization' => 'Bearer x'])->assertOk();

$this->assertDatabaseHas('orders', ['created_by' => 7]);
```

**An invalid or expired token:**

```php
GembootAuth::fake()->rejectTokens();

$this->getJson('/api/users', ['Authorization' => 'Bearer expired'])->assertStatus(401);
```

**The auth service is down:**

```php
GembootAuth::fakeOutage();

$this->getJson('/api/users', ['Authorization' => 'Bearer x'])->assertStatus(503);
```

## Several roles and permissions

```php
GembootAuth::fake(roles: ['admin', 'editor'], permissions: ['report.read', 'report.export']);
```

When Gemboot asks for several names at once (`role:admin|editor`, or an array in `GembootPermission::hasPermissionTo()`), the fake answers like this:

| Question | Answer |
|---|---|
| `has-role` | true if the user has **any** of the roles |
| `has_permission_to` | true if the user has **all** of the permissions |
| `has_any_permission` | true if the user has **any** of them |

## Changing the fake during a test

`fake()` returns the fake, so you can adjust it between requests:

```php
$fake = GembootAuth::fake(roles: ['editor']);
$this->getJson('/api/users', ['Authorization' => 'Bearer x'])->assertStatus(403);

$fake->withRoles(['admin']);
$this->getJson('/api/users', ['Authorization' => 'Bearer x'])->assertOk();
```

Also available: `withUser([...])`, `withPermissions([...])`, `outage()`, and `rejectTokens()`. Each takes `false` to switch back, for example `->outage(false)`.

If you turned on the auth cache (`GEMBOOT_AUTH_CACHE_TTL`), Gemboot may reuse an earlier answer for the same token. Use a different token per scenario, or keep the cache off in tests.

## Checking which calls were made

```php
$fake = GembootAuth::fake(roles: ['admin']);

$this->getJson('/api/users', ['Authorization' => 'Bearer x']);

$fake->assertCalled('me', 1);          // exactly once
$fake->assertCalled('has-role');       // at least once
$fake->assertNotCalled('has-permission-to');
```

`$fake->requests()` returns every call with its endpoint, query parameters, and token, for anything more specific.

## The SSO guard

The fake also answers the SSO guard's user service, as long as `GEMBOOT_USER_SERVICE_URL` or `GEMBOOT_SSO_GET_USER_URL` is set in your test environment:

```php
GembootAuth::fake(user: ['id' => 9], roles: ['admin']);

$this->getJson('/api/profile', ['Authorization' => 'Bearer x'])
    ->assertOk()
    ->assertJsonPath('data.id', 9);
```

Then `auth()->user()->roles` and `->permissions` hold the roles and permissions you passed. Other HTTP calls of your app are not affected.

## The `gemboot` guard

The fake answers the [`gemboot` guard](AUTH.md#one-user-everywhere-the-gemboot-guard) too, because the guard asks the same `me` endpoint:

```php
GembootAuth::fake(user: ['id' => 9, 'name' => 'Ana']);

$this->getJson('/api/profile', ['Authorization' => 'Bearer x'])
    ->assertJsonPath('data.id', 9);
```

To skip the auth service entirely, use `actingAs()` with a `GembootUser`. Known roles and permissions let `role:` and `permission:` answer without the fake on `auth:api` routes:

```php
use Gemboot\Auth\GembootUser;

$this->actingAs(new GembootUser(['id' => 9], roles: ['admin'], permissions: []), 'api');
```

## Without the fake: `actingAs()`

For the SSO guard, Laravel's own `actingAs()` also works, because the guard is a normal Laravel guard:

```php
use Gemboot\SSO\Auth\SSOUser;

$this->actingAs(new SSOUser(['id' => 9, 'roles' => ['admin']]), 'api');
```

This skips the user service entirely. The auth middleware (`token-validated`, `role:`, `permission:`) doesn't use Laravel guards, so for those routes use `GembootAuth::fake()`.

## Next

- [Authentication](AUTH.md)
- [Artisan commands](COMMANDS.md): `gemboot:doctor` checks your real setup

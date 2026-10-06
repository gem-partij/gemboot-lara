# Gemboot Lara

[![Latest Stable Version](https://poser.pugx.org/gem-partij/gemboot-lara/v/stable)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Total Downloads](https://poser.pugx.org/gem-partij/gemboot-lara/downloads)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![tests](https://github.com/gem-partij/gemboot-lara/actions/workflows/tests.yml/badge.svg)](https://github.com/gem-partij/gemboot-lara/actions/workflows/tests.yml)
[![License](https://poser.pugx.org/gem-partij/gemboot-lara/license)](https://packagist.org/packages/gem-partij/gemboot-lara)

Gemboot Lara is a Laravel package for **API services that sit behind a central auth service**.

After reading this page you will know what the package does, how a request flows through it, how to set it up, and where each piece lives in the code.

## What it does

Imagine you run several Laravel API services. None of them stores users or passwords. A separate auth service does that: it issues tokens, and it knows each user's roles and permissions. Every API service has to do the same three things on every request:

1. **Check the bearer token** with the auth service, and check the user's role or permission.
2. **Return JSON in one shape**, so clients can parse every service the same way.
3. **Do the usual CRUD work** (list with search and paging, show, store, update, delete).

Gemboot Lara gives you one convention for each:

| Need | What Gemboot gives you | Main code |
|---|---|---|
| Token and permission checks | Route middleware (`TokenValidated`, `HasRole`, `HasPermissionTo`) and an SSO guard, all backed by HTTP calls to your auth service | `src/Middleware/`, `src/Libraries/AuthLibrary.php`, `src/SSO/Auth/` |
| One response shape | Every response is `{ "status", "message", "data" }`, and exceptions turn into the right HTTP status | `src/Traits/JSONResponses.php`, `src/Exceptions/` |
| CRUD | A Service-Model-View-Controller (SMVC) layer: base controller, base service, base model, and artisan generators | `src/Controllers/`, `src/Services/CoreService.php`, `src/Models/CoreModel.php` |

The token check is the core of the package. The other two are conventions built around it.

### The response shape, by example

Without Gemboot, every controller repeats the same `try/catch` and JSON building:

```php
public function index()
{
    try {
        return response()->json(['status' => 200, 'message' => 'Success!', 'data' => User::all()], 200);
    } catch (\Throwable $e) {
        \Log::error($e->getMessage());
        return response()->json(['status' => 500, 'message' => 'Internal Server Error', 'data' => ['error' => $e->getMessage()]], 500);
    }
}
```

With Gemboot, you write only the part that matters:

```php
use GembootResponse;

public function index()
{
    return GembootResponse::responseSuccessOrException(function () {
        return User::all();
    });
}
```

Both return the same JSON:

```jsonc
// Success
{ "status": 200, "message": "Success!", "data": [ /* users */ ] }

// Error: throw new GembootNotFoundException() inside the callback
{ "status": 404, "message": "Not Found", "data": { "error": "Not Found" } }
```

Any exception thrown inside the callback becomes an error response. Gemboot exceptions map to their status code, and Eloquent's `ModelNotFoundException` becomes a 404. Anything else becomes a 500, is logged, and can send a Telegram alert (see [Configuration](#configuration)).

## How a request flows

```text
Client ──Bearer token──▶ Your Laravel API (uses Gemboot)
                           │
                           ├─ 1. Middleware: TokenValidated / HasRole / HasPermissionTo
                           │       └─ AuthLibrary ──HTTP──▶ Central auth service
                           │                                 (GET me, has-role, has-permission-to)
                           │       401 / 403 here if the auth service says no
                           │
                           ├─ 2. Controller (extends GembootResourceController)
                           │       └─ responseSuccessOrException(fn)
                           │
                           ├─ 3. Service (extends GembootService): query, search, paging, cache
                           │
                           └─ 4. JSONResponses: wraps the result as { status, message, data }
```

## Quick start

### 1. Install

```sh
composer require gem-partij/gemboot-lara
```

The service provider, facades, and class aliases register themselves through Laravel package discovery.

### 2. Publish the config and point it at your auth service

```sh
php artisan vendor:publish --tag=gemboot
```

This creates `config/gemboot.php`. Then set at least this in `.env`:

```dotenv
GEMBOOT_AUTH_BASE_API=https://auth.example.com/api/auth
```

### 3. Protect routes

Register the middleware aliases in `bootstrap/app.php`:

```php
use Illuminate\Foundation\Configuration\Middleware;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'token-validated' => \Gemboot\Middleware\TokenValidated::class,
        'role'            => \Gemboot\Middleware\HasRole::class,
        'permission'      => \Gemboot\Middleware\HasPermissionTo::class,
    ]);
})
```

Then use them on routes:

```php
Route::middleware(['token-validated', 'permission:user.read'])->group(function () {
    Route::apiResource('users', UserController::class);
});
```

`TokenValidated` calls the auth service's `me` endpoint and merges the result into the request as `user_login`, so `$request->user_login` holds the current user.

### 4. Generate the SMVC classes

```sh
php artisan gemboot:make-model User --all
```

This creates the model, a service, and a resource controller. The controller extends `GembootResourceController`, which already implements `index`, `store`, `show`, `update`, and `destroy`. See [docs/COMMANDS.md](docs/COMMANDS.md) for the separate `gemboot:make-service` and `gemboot:make-controller` commands.

`index` reads these query parameters:

| Parameter | Example | Effect |
|---|---|---|
| `search`, `search_field`, `search_mode` | `?search=ana&search_field=name` | `LIKE` search (`ILIKE` on PostgreSQL). Arrays search several fields. `relation.column` searches a related model. |
| `search_exact` | `?search_exact=42&search_field=id` | Exact match instead of `LIKE` |
| `order`, `atoz` | `?order=name&atoz=desc` | Sort. Arrays sort by several columns. |
| `page_len` | `?page_len=50` | Page size (default 30). `all` returns up to 1000 rows without paging. |

### Searching relations

`?search=ana&search_field=author.name` searches the `author` relation. The part before the dot must be a real relation on the model: a public method with no required parameters, not inherited from Eloquent or Gemboot, whose return type (if declared) is a `Relation`. Anything else gets a 400.

To allow only specific relations, list them on the model:

```php
class Post extends GembootModel
{
    protected $searchableRelations = ['author', 'tags'];
}
```

We recommend the list, and declaring relation return types (`public function author(): BelongsTo`), for any model exposed through `index`.

## The auth service contract

Gemboot does not issue tokens. It forwards the client's bearer token to your auth service and trusts the answer. Your auth service must expose these endpoints under `gemboot.auth.base_api`:

| Method and path | Used by | Gemboot expects |
|---|---|---|
| `POST login` | `AuthLibrary::login()` | Body fields `npp`, `password`, `hwid` |
| `GET me` | `TokenValidated`, `AuthLibrary::me()` | HTTP 200 with the user. A `data` wrapper is unwrapped. |
| `GET validate-token` | `TokenValidated:client`, `AuthLibrary::validateToken()` | HTTP 200 if the token is valid |
| `GET has-role?role_name=…` | `HasRole`, `GembootPermission::hasRole()` | HTTP 200 with `has_role` |
| `GET has-permission-to?permission_name=…` | `HasPermissionTo`, `GembootPermission::hasPermissionTo()` | HTTP 200 with `has_permission_to` (and `has_any_permission` when several names are joined with `\|`) |
| `POST logout` | `AuthLibrary::logout()` | HTTP 200 |

Any status other than 200 counts as "no", and the middleware answers 401 or 403.

### Alternative: the SSO guard

Instead of the middleware, you can use Laravel's own `auth` system through the `gemboot-sso-token` guard. It calls a "get current user" URL with the token and caches the user for `gemboot.sso.cache_ttl` seconds (default 300), keyed by a SHA-1 hash of the token.

```php
// config/auth.php
'guards' => [
    'api' => ['driver' => 'gemboot-sso-token', 'provider' => 'gemboot-sso'],
],
'providers' => [
    'gemboot-sso' => ['driver' => 'gemboot-sso-provider'],
],
```

Then `auth:api` on a route and `auth()->user()` work as usual. Set `GEMBOOT_USER_SERVICE_URL` (the guard calls `{url}/user/me`) or `GEMBOOT_SSO_GET_USER_URL` for the full URL. Both have `_FALLBACK` variants that are tried when the first call fails.

## Configuration

All settings live in **one file, `config/gemboot.php`**. Every value comes from an environment variable, so you can usually leave the file as published and only edit `.env`.

| Key | Env variable | Purpose |
|---|---|---|
| `auth.base_api` | `GEMBOOT_AUTH_BASE_API` | Base URL of the auth service endpoints above |
| `sso.user_service_url`, `sso.get_user_url` | `GEMBOOT_USER_SERVICE_URL`, `GEMBOOT_SSO_GET_USER_URL` | Used by the SSO guard |
| `sso.fallback.*` | same names with `_FALLBACK` | Second user service for the SSO guard |
| `sso.cache_ttl` | `GEMBOOT_SSO_CACHE_TTL` | Seconds to cache an SSO user (default 300) |
| `file_handler.base_url` | `GEMBOOT_FILE_HANDLER_BASE_URL` | File upload service used by `FileHandler` |
| `notifications.telegram.token`, `.chat_id` | `GEMBOOT_TELEGRAM_BOT_TOKEN`, `GEMBOOT_TELEGRAM_CHAT_ID` | Telegram alert on every unhandled 500. Off when the token is empty. |
| `response.send_header_error` | `GEMBOOT_SEND_HEADER_ERROR` | Adds an `x-gemboot-error-message` header to error responses (default on) |

The publish tags `gemboot-auth`, `gemboot-gateway`, and `gemboot-file-handler` exist for backward compatibility. They publish the same `config/gemboot.php`. The separate files in this repository's `config/` folder (`gemboot_auth.php`, `gemboot_sso.php`, and so on) are left over from older versions and are not published.

### Caching

`CoreService` can cache `listAll()` and `findOrFail()` results. Turn it on per service with `setObserver()`, and register the same observer on the model so writes clear the cache:

```php
class UserCachingObserver extends \Gemboot\Observers\CoreEloquentCachingObserver
{
    protected $cacheTag = 'users'; // the model's table name
}

User::observe(UserCachingObserver::class);
$service = (new UserService)->setObserver(new UserCachingObserver);
```

This needs a cache store with tag support: redis, memcached, or array. On file or database stores, Gemboot skips caching and reads from the database, because it could not clear stale entries there.

## Facades and aliases

Everything below is registered automatically.

| Alias | Class | Use it for |
|---|---|---|
| `GembootResponse` | `Gemboot\GembootResponse` | `responseSuccess`, `responseSuccessOrException`, `responseNotFound`, … |
| `GembootAuth` | `Gemboot\Libraries\AuthLibrary` | Calling the auth service directly |
| `GembootPermission` | `Gemboot\GembootPermission` | `hasRole`, `hasPermissionTo`, `requirePermission` inside code |
| `GembootValidator` | `Gemboot\GembootValidator` | `makeAndThrow` validates and throws `GembootValidationFailException` |
| `GembootRequest` | `Gemboot\GembootRequest` | `getRequestToken()` reads the bearer token from a request |
| `GembootController`, `GembootResourceController`, `GembootProxyController` | `Gemboot\Controllers\CoreRest*Controller` | Base controllers |
| `GembootService` | `Gemboot\Services\CoreService` | Base service |
| `GembootModel` | `Gemboot\Models\CoreModel` | Base model (adds `search`, `searchExact` scopes) |

Exceptions, all thrown from inside `responseSuccessOrException()`:

| Alias | Status |
|---|---|
| `GembootBadRequestException`, `GembootValidationFailException` | 400 |
| `GembootUnauthorizedException` | 401 |
| `GembootForbiddenException` | 403 |
| `GembootNotFoundException` | 404 |
| `GembootMethodNotAllowedException` | 405 |
| `GembootConflictException` | 409 |
| `GembootUnprocessableEntityException` | 422 |
| `GembootTooManyRequestsException` | 429 |
| `GembootServerErrorException` | 500 |
| `GembootServiceUnavailableException` | 503 |
| `GembootHttpErrorException` | any status you pass |

## File handler

`FileHandler` uploads a file to a separate file service at `gemboot.file_handler.base_url`:

```php
use Gemboot\FileHandler\FileHandler;

public function uploadImage(Request $request)
{
    return (new FileHandler($request->file_image))
        ->uploadImage('photo.jpeg', '/images/2026')
        ->object();
}
```

`uploadImage()` and `uploadDocument()` return an `Illuminate\Http\Client\Response`, so `->json()`, `->status()`, `->successful()` and the rest of the [Laravel HTTP client](https://laravel.com/docs/http-client) API are available.

## Legacy: gateway middleware

`Gemboot\Gateway\Middleware\CheckToken` is **deprecated since 8.0** and may be removed in a future major release. It checked every request against a gateway. Use the per-route middleware from the [Quick start](#3-protect-routes) instead.

## Support policy

Only the latest major version gets new features.

| Package version | Laravel | PHP |
|---|---|---|
| 0.5.x | < 5.5 | |
| 1.x | ^5.5, ^6, ^7 | 7.2 to 8.0 |
| 2.x | 8 | 7.3 to 8.1 |
| 3.x | 9 | 8.0 to 8.2 |
| 4.x | 10 | 8.1 to 8.3 |
| 5.x | 11 | 8.2 to 8.3 |
| 6.x | 11 | ^8.2 |
| 7.x | ^11, ^12 | ^8.2 |
| **8.x (current)** | **^12, ^13** | **^8.3** |

### Upgrading from 7.x to 8.x

There are no changes to public class or method signatures, the response envelope, config keys, or middleware aliases. What changed:

- PHP minimum is now **8.3** (was 8.2).
- **Laravel 11 is no longer supported.** It stopped receiving security fixes in March 2026, and recent Composer versions refuse to install its releases because of unpatched security advisories. Stay on 7.x if you can't upgrade Laravel yet.
- `guzzlehttp/guzzle` minimum is now **7.15.2**. Earlier releases are affected by security advisories, including host-based check bypasses, and Gemboot sends auth tokens through Guzzle.
- `laravel-notification-channels/telegram` accepts `^7.0`.

Behavior changes worth checking:

- `CoreService` caching now requires a cache store with tag support. See [Caching](#caching).
- The Telegram alert and the debug trace in error responses are read through `config()`, so they keep working after `php artisan config:cache`.

Full notes: [v8.0.0 release](https://github.com/gem-partij/gemboot-lara/releases/tag/8.0.0).

## Working on this package

This section is for contributors, including AI coding agents.

### Project map

```text
src/
  GembootServiceProvider.php    registers facades, the SSO guard, artisan commands, config publishing
  Middleware/                   TokenValidated, HasRole, HasPermissionTo (per-route auth)
  Libraries/AuthLibrary.php     HTTP client for the auth service (login, me, has-role, ...)
  Libraries/HttpClient.php      thin Guzzle wrapper used by AuthLibrary
  SSO/Auth/                     SSOGuard, SSOUserProvider, SSOUser (Laravel auth driver)
  Traits/JSONResponses.php      the response envelope and exception-to-status mapping
  Exceptions/                   one class per HTTP error status
  Controllers/                  CoreRestController, CoreRestResourceController (CRUD), CoreRestProxyController
  Services/CoreService.php      CRUD, search, sort, paging, tag cache, before/after hooks
  Models/CoreModel.php          base model; search scopes come from Traits/MainModelAbilities.php
  Observers/                    CoreEloquentCachingObserver (clears the tag cache on write)
  Commands/                     gemboot:make-model, make-service, make-controller
  Gateway/Middleware/CheckToken.php   deprecated, do not extend
config/gemboot.php              the only published config file
stubs/                          templates used by the artisan generators
tests/                          PHPUnit + orchestra/testbench
docs/                           longer guides (some still in Indonesian)
```

### Rules that keep consumers safe

This is a published library used in production, so every public symbol is a contract.

1. **Backward compatibility.** Class and method signatures, facade and class aliases, config keys, middleware aliases, and the response envelope don't change without a major version. State the SemVer impact (patch, minor, major) in every PR.
2. **The envelope `{ status, message, data }` is fixed.**
3. **Read settings with `config()`, never `env()`,** outside `config/` files. `env()` returns `null` after `php artisan config:cache`.
4. **No app-specific values.** URLs, header formats, role names: all configurable.
5. **Explicit nullable types** (`?Type $x = null`), required by PHP 8.4+.
6. **New public behavior needs a test** and a README or `docs/` update.
7. Don't build on the deprecated `CheckToken` middleware.

### Commands

```sh
composer install
composer test                              # all tests
vendor/bin/phpunit --filter GembootHttpTest
```

Tests that call real external services are opt-in. For example, the Telegram test runs only with `TEST_NOTIFICATION=true` plus `GEMBOOT_TELEGRAM_BOT_TOKEN` and `GEMBOOT_TELEGRAM_CHAT_ID` set in the environment. A `docker-compose.yml` with a PHP 8.4 container is included.

CI runs PHP 8.3, 8.4, and 8.5 against Laravel 12 and 13, plus a lowest-versions job. It fails if Composer installs a dev branch instead of a stable release.

### Releasing

Versions come from Git tags. Packagist updates when a tag is pushed. Cut a SemVer tag, then publish a GitHub release whose notes explain the upgrade risk.

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md).

## Security

If you find a security issue, please email anggerpputro@gmail.com instead of opening a public issue.

## Credits

- [Angger Priyardhan Putro](https://github.com/anggerpputro)
- [All contributors](../../contributors)

## License

MIT. See [LICENSE.md](LICENSE.md).

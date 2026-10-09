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
        return response()->json(['status' => 200, 'message' => 'OK', 'data' => User::all()], 200);
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
{ "status": 200, "message": "OK", "data": [ /* users */ ] }

// Error: throw new GembootNotFoundException() inside the callback
{ "status": 404, "message": "Not Found", "data": { "error": "Not Found" } }
```

Any exception thrown inside the callback becomes an error response. Gemboot exceptions map to their status code, and Eloquent's `ModelNotFoundException` becomes a 404, a denial from Laravel's authorization (`$this->authorize()`, policies) a 403, and a failed `$request->validate()` a 400. Anything else becomes a 500 and goes through Laravel's `report()`, so it reaches your logs and any error tracker or log channel you've set up.

## Documentation

Step-by-step guides with examples live in [`docs/`](docs/README.md):

| Guide | Covers |
|---|---|
| [Installation](docs/INSTALLATION.md) | Install, point Gemboot at your auth service, check it works |
| [Responses](docs/RESPONSES.md) | The response format, helpers, and exceptions as error responses |
| [Authentication](docs/AUTH.md) | Middleware, roles and permissions, the SSO guard, the auth service contract |
| [Models](docs/MODEL.md), [Services](docs/SERVICE.md), [Controllers](docs/CONTROLLER.md) | Building a CRUD API, search, hooks, validation |
| [Routes and query parameters](docs/ROUTES.md) | Search, sorting, and paging from the client side |
| [Caching](docs/CACHING.md) | Cached lists and records, cleared on changes, per user |
| [Testing your API](docs/TESTING.md) | Test protected routes without a running auth service (`GembootAuth::fake()`) |
| [Request IDs](docs/REQUEST_IDS.md) | Follow one user action through the logs of every service |
| [Commands](docs/COMMANDS.md), [Configuration](docs/CONFIGURATION.md), [File handler](docs/FILE_HANDLER.md) | Reference |

The rest of this README is an overview.

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

Then check the setup:

```sh
php artisan gemboot:doctor
```

It lists anything that's wrong (missing URL, unreachable auth service, TLS off, ...), each with a fix.

To check that the auth service also answers the way Gemboot expects, run `php artisan gemboot:contract-test` with a valid token.

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

`TokenValidated` calls the auth service's `me` endpoint and merges the result into the request as `user_login`, so `$request->user_login` holds the current user. Gemboot keeps that merged value out of the data `store()` and `update()` save. Cache keys keep it, so cached entries stay per user.

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
| `page_len` | `?page_len=50` | Page size (default 30, at most `gemboot.pagination.max_page_len`, default 1000). `all` returns up to 1000 rows without paging. |

Columns in the model's `$hidden` (passwords, tokens) can't be searched or sorted; naming one in `search_field` or `order` returns 400. A search without `search_field` skips them. An `atoz` other than `asc` or `desc` also returns 400.

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

Any status other than 200 counts as "no", and the middleware answers 401 or 403. When the auth service can't be reached or answers 5xx, the middleware answers **503** instead, so clients don't log users out during an outage.

The answers of `me`, `validate-token`, `has-role`, and `has-permission-to` can be cached per token with `GEMBOOT_AUTH_CACHE_TTL` (seconds, off by default). That saves one HTTP call per middleware per request. The trade-off: a token revoked at the auth service keeps working until its cache entries expire. `AuthLibrary::logout()` clears them for that token. Outages are never cached.

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

Then `auth:api` on a route and `auth()->user()` work as usual. Set `GEMBOOT_USER_SERVICE_URL` (the guard calls `{url}/user/me`) or `GEMBOOT_SSO_GET_USER_URL` for the full URL. Both have `_FALLBACK` variants. The fallback is tried when the first service can't be reached or doesn't accept the token, so a rejected token is also sent to the fallback host. (9.0 will try the fallback only during outages.)

The user is cached per token, so a token revoked at the auth service keeps working until `GEMBOOT_SSO_CACHE_TTL` runs out. Clear it on logout:

```php
Auth::guard('api')->forgetCachedUser();
```

## Configuration

All settings live in **one file, `config/gemboot.php`**. Every value comes from an environment variable, so you can usually leave the file as published and only edit `.env`.

| Key | Env variable | Purpose |
|---|---|---|
| `auth.base_api` | `GEMBOOT_AUTH_BASE_API` | Base URL of the auth service endpoints above |
| `auth.cache_ttl` | `GEMBOOT_AUTH_CACHE_TTL` | Seconds to cache auth service answers per token (default 0, off) |
| `http.verify` | `GEMBOOT_HTTP_VERIFY` | TLS certificate check for auth service calls: `true` (default), `false`, or a CA bundle path |
| `http.timeout`, `http.connect_timeout` | `GEMBOOT_HTTP_TIMEOUT`, `GEMBOOT_HTTP_CONNECT_TIMEOUT` | Seconds (defaults 30 and 10) |
| `pagination.max_page_len` | `GEMBOOT_MAX_PAGE_LEN` | Upper limit for `?page_len` (default 1000, `null` for no limit) |
| `sso.user_service_url`, `sso.get_user_url` | `GEMBOOT_USER_SERVICE_URL`, `GEMBOOT_SSO_GET_USER_URL` | Used by the SSO guard |
| `sso.fallback.*` | same names with `_FALLBACK` | Second user service for the SSO guard |
| `sso.cache_ttl` | `GEMBOOT_SSO_CACHE_TTL` | Seconds to cache an SSO user (default 300) |
| `file_handler.base_url` | `GEMBOOT_FILE_HANDLER_BASE_URL` | File upload service used by `FileHandler` |
| `notifications.telegram.token`, `.chat_id` (deprecated) | `GEMBOOT_TELEGRAM_BOT_TOKEN`, `GEMBOOT_TELEGRAM_CHAT_ID` | Telegram alert on every unhandled 500. Off when the token is empty. |
| `response.security_headers` | `GEMBOOT_SECURITY_HEADERS` | `Cache-Control: no-store` and `X-Content-Type-Options: nosniff` on every response (default on) |
| `response.send_header_error` | `GEMBOOT_SEND_HEADER_ERROR` | Adds an `x-gemboot-error-message` header to error responses (default on) |
| `response.compressed` | `GEMBOOT_RESPONSE_COMPRESSED` | **Deprecated, removed in 9.0.** gzip through `ob_gzhandler` (default off; leave compression to the web server) |

You don't have to publish the file: Gemboot merges its defaults, and a published file only needs the keys you change. A file published before a new top-level key was added still gets that key's default.

The publish tags `gemboot-auth`, `gemboot-gateway`, and `gemboot-file-handler` exist for backward compatibility. They publish the same `config/gemboot.php`.

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

**Cached results are per logged-in user.** Services and controllers often scope queries to the current user (for example `where('user_id', auth()->id())`), so a shared cache entry could show one user another user's data. The cache key includes `auth()->id()` whenever someone is logged in through a Laravel guard. If a service's results are the same for everyone, share them across users by returning `null`:

```php
class CountryService extends GembootService
{
    protected function cacheScope()
    {
        return null; // same list for every user
    }
}
```

`GembootResourceController` has the same `cacheScope()` method for its own `index`/`show` cache (`$cache_seconds`). That cache is also cleared by the caching observer on stores with tag support.

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

### Upgrading from 8.13 to 8.14

- **New: `php artisan gemboot:contract-test`** checks that an auth service answers `me`, `validate-token`, `has-role`, and `has-permission-to` the way Gemboot expects, before you point a service at it ([details](docs/COMMANDS.md#gembootcontract-test)).
- **8.14.1: the SSO guard answers 503 during an outage** (user service unreachable or answering 5xx), like the auth middleware. Before, it answered 500 when there was no connection, and 401 for a 5xx, which made clients log users out ([details](docs/AUTH.md#the-sso-guard)).

### Upgrading from 8.12 to 8.13

- **New, opt-in: search and sort allowlists on models.** `protected $searchableFields = ['name', 'category.name'];` and `protected $sortableFields = ['name', 'created_at'];` limit what clients may search and sort by; anything else gets a 400. Without the lists nothing changes. 9.0 will require them, so adding them now prepares that upgrade ([details](docs/MODEL.md#limiting-what-clients-can-search-and-sort)).

### Upgrading from 8.11 to 8.12

- **New, opt-in: a grace period for short auth outages.** With `GEMBOOT_AUTH_OUTAGE_GRACE=60`, users keep working during a short outage, with the last good answer for their token ([details](docs/AUTH.md#short-outages-keep-users-working)).
- **New: events for alerts and metrics.** `Gemboot\Events\AuthServiceUnavailable` and `Gemboot\Events\TokenRejected` ([details](docs/AUTH.md#events-for-alerts-and-metrics)).

### Upgrading from 8.10 to 8.11

- **New, opt-in: permissions as Laravel abilities.** With `GEMBOOT_PERMISSIONS_AS_ABILITIES=true` and the `gemboot` guard, `$this->authorize('report.read')`, `@can`, and `->can('report.read')` on routes check your auth service's permissions ([details](docs/AUTH.md#laravels-can-with-your-permissions)).
- **Authorization denials inside `responseSuccessOrException()` now answer 403** (or the status the policy chose, such as 404) instead of 500. Before, `$this->authorize()` failing in the callback was reported as a server error.
- **8.11.1: a failed `$request->validate()` inside `responseSuccessOrException()` now answers 400** with the errors under `data.error`, like Gemboot's own validation, instead of 500 ([details](docs/RESPONSES.md#validating-input-in-the-same-call)).

### Upgrading from 8.9 to 8.10

- **New, opt-in: the `gemboot` guard.** Set `'api' => ['driver' => 'gemboot']` in `config/auth.php`, and `auth()->user()`, `$request->user()`, `auth:api`, Gates, and policies get the user from your auth service's `me` answer. Routes with `token-validated` keep working as before, and when the `gemboot` guard is the default guard, they hand their user to it without a second call ([guide](docs/AUTH.md#one-user-everywhere-the-gemboot-guard)).
- **New: your own `TokenVerifier`.** Bind it to decide who a token belongs to. When it also returns roles and permissions, `role:` and `permission:` answer without calling the auth service.

### Upgrading from 8.8 to 8.9

- **New, opt-in: request IDs.** Add the `Gemboot\Middleware\AssignRequestId` middleware, and every request gets an ID that appears in each log line and is passed to the next service, so one user action can be followed through all logs ([guide](docs/REQUEST_IDS.md)). Gemboot's calls to the auth service, the SSO user service, and the file handler now send the `X-Request-Id` header whenever a request ID is set.
- **New: FormRequest classes in the resource controller.** `protected $storeRequest = StoreProductRequest::class;` (and `$updateRequest`) instead of overriding `validateStoreRequest()` ([details](docs/CONTROLLER.md#with-a-formrequest-class)).

### Upgrading from 8.7 to 8.8

- **New, opt-in: save only validated fields.** `protected $saveValidatedOnly = true;` on a resource controller drops every field without a validation rule, so clients can't set fields like `is_admin` on models with `$guarded = []` ([details](docs/CONTROLLER.md#save-only-validated-fields)).
- **Deprecated: Gemboot's Telegram alerts** (`GEMBOOT_TELEGRAM_*`, `TelegramLibrary`, `Gemboot\Notifications\Telegram`). They still work but log a deprecation notice, and will be removed in 9.0. Forward errors with a Laravel log channel instead ([how](docs/RESPONSES.md#error-alerts-telegram-slack-)).

### Upgrading from 8.6 to 8.7

- **New: faster paging modes.** `protected $pagination = 'cursor';` (or `'simple'`) on a controller skips the `COUNT(*)` query that every list request runs ([details](docs/ROUTES.md#faster-paging-for-large-tables)).
- **New, opt-in: standard parameter names** `sort`, `per_page`, and `filter[...]` with `GEMBOOT_STANDARD_QUERY_PARAMETERS=true` ([details](docs/ROUTES.md#standard-parameter-names-opt-in)).
- **`?order=` with a column the table doesn't have now answers 400** instead of a database error (500).

### Upgrading from 8.5 to 8.6

- **Failed authentication attempts are limited per client IP**: after 60 rejected tokens or failed logins within a minute, the client gets 429 until the minute is over, without a call to your auth service. Requests without a token don't count. Change it with `GEMBOOT_AUTH_MAX_FAILED_ATTEMPTS` (`0` turns it off). **Behind a proxy or load balancer, configure Laravel's trusted proxies**, or all clients share one limit.
- **Malformed and missing tokens are answered locally** (401) instead of being sent to the auth service.
- **Connections to the auth service are reused** between calls (several middleware in one request, and every request of an Octane worker).

See [Authentication: protection against token floods](docs/AUTH.md#protection-against-token-floods-and-password-guessing).

### Upgrading from 8.4 to 8.5

- **New: per-record authorization with Laravel policies.** Set `protected $authorizeWithPolicy = true;` on a resource controller, and each action checks the model's policy (`viewAny`, `view`, `create`, `update`, `delete`). Off by default ([guide](docs/CONTROLLER.md#who-may-see-or-change-which-record-policies)).
- **New: `php artisan gemboot:permissions`** lists the roles and permissions your routes check, and flags likely typos ([details](docs/COMMANDS.md#gembootpermissions)).

### Upgrading from 8.3 to 8.4

- **New: `GembootAuth::fake()`** replaces the auth service in your tests ([guide](docs/TESTING.md)).
- **The SSO guard now follows each request.** It used to keep the first request's user for the lifetime of the app, which affected tests with several requests and long-running servers such as Octane. Users set with `actingAs()` are kept as before.

### Upgrading from 8.2 to 8.3

- **New: `php artisan gemboot:doctor`** checks your setup and explains how to fix problems.
- **Every Gemboot response now carries `Cache-Control: no-store` and `X-Content-Type-Options: nosniff`.** If a client or proxy relied on caching Gemboot responses, change `response.security_headers` or set `GEMBOOT_SECURITY_HEADERS=false`.

### Upgrading from 8.1 to 8.2

No public signatures or config keys are removed. Check these changes:

- **Cached results are now per logged-in user** (see [Caching](#caching)). This fixes data leaking between users when a service scopes queries to `auth()->id()`, but it also means fewer cache hits. Override `cacheScope()` to return `null` where results really are the same for everyone.
- **The controller's `$cache_seconds` cache is cleared by the caching observer** on stores with tag support. Before, it kept serving old data until `cache_seconds` ran out.
- **New:** `SSOGuard::forgetCachedUser()` clears the cached SSO user, for logout.
- **Deprecated:** `response.compressed` (`GEMBOOT_RESPONSE_COMPRESSED`). Enabling it now logs a deprecation; it will be removed in 9.0.

### Upgrading from 8.0 to 8.1

No public signatures or config keys are removed, and the response envelope is unchanged. Check these behavior changes:

- **TLS certificates are now verified** for auth service calls (`GEMBOOT_HTTP_VERIFY`, default `true`). Before 8.1 verification was always off. If your auth service uses a self-signed certificate, point `GEMBOOT_HTTP_VERIFY` at its CA bundle, or set it to `false` for local development only.
- **Unexpected 500 errors show a generic message** (`"Internal Server Error"`) when `APP_DEBUG` is off, and are passed to `report()`. Gemboot's own exceptions (404, 403, ...) keep their messages.
- **The auth middleware answers 503** instead of 401/403 when the auth service is unreachable or answers 5xx.
- **`page_len` is limited to 1000** by default (`GEMBOOT_MAX_PAGE_LEN`).
- **Hidden columns can't be searched or sorted**, and an invalid `atoz` returns 400 instead of 500.
- **Class aliases are registered automatically.** The exception, controller, model, and service aliases (`GembootResourceController`, `GembootNotFoundException`, ...) were never registered before, despite the docs. If you added them to `config/app.php` yourself, you can remove them.
- **Config defaults are merged**, so publishing `config/gemboot.php` is optional. `response.compressed` now defaults to off.

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

### Upgrading from 6.x to 7.x

7.0 was released without upgrade notes; these were written later. Response envelope, middleware aliases, and config keys didn't change. What can break code that uses Gemboot's classes directly:

- **Exceptions** now extend Symfony's `HttpException` (through `GembootHttpErrorException`). The HTTP status is `$e->getStatusCode()`. `$e->getCode()` used to be the status and is now `0`.
  - Specific exceptions take `($message, $data = [], $previous = null)`, for example `new GembootNotFoundException('Order not found')`.
  - `GembootHttpErrorException` takes the status first: `new GembootHttpErrorException(418, "I'm a teapot")`.
- **New exceptions and aliases:** `GembootConflictException`, `GembootMethodNotAllowedException`, `GembootUnprocessableEntityException`, `GembootTooManyRequestsException`, `GembootServiceUnavailableException`, `GembootHttpErrorException`, `GembootValidationFailException`.
- **`GembootValidationFailException`** keeps the validation errors as data (`getData()`), and they appear under `data.error` in the response.
- **`HttpClient`** uses Guzzle instead of curl:
  - `get()` and `post()` no longer take a third `$absolute_url` argument.
  - The decoded body in `->data` is an array, not an object: `$response->data['user']`, not `$response->data->user`.
- **TLS certificates are verified.** The curl client had certificate checks off. In 8.x, `GEMBOOT_HTTP_VERIFY` points Gemboot at your own CA bundle.
- **8.14.1 also clarified `config/gemboot.php`:** `auth.base_api` is the setting Gemboot reads. `auth.base_url`, `sso.auth_service_url`, and `sso.validate_token_url` are not read at all.

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
docs/                           step-by-step guides (start at docs/README.md)
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

Tests that call real external services are opt-in. For example, the Telegram test runs only with `TEST_NOTIFICATION=true` plus `GEMBOOT_TELEGRAM_BOT_TOKEN` and `GEMBOOT_TELEGRAM_CHAT_ID` set in the environment, and `TEST_AUTH=true` needs `GEMBOOT_AUTH_BASE_API` pointing at a real auth service. The repository only contains placeholder hosts. A `docker-compose.yml` with a PHP 8.4 container is included.

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

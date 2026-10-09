# Authentication

After this guide, you'll be able to protect routes by login, role, and permission, read the current user with Laravel's `auth()->user()`, and set up the SSO guard. You'll also know exactly what your central auth service has to provide.

## How it works

Gemboot doesn't store users or check passwords. A separate **auth service** does that. Gemboot takes the bearer token from each request, asks the auth service about it, and lets the request through only if the auth service agrees.

```text
Client ──"Authorization: Bearer <token>"──▶ Your API (Gemboot middleware)
                                               │
                                               └─ GET me / has-role / has-permission-to ──▶ Auth service
```

There are two ways to use it:

- **The middleware** (`token-validated`, `role`, `permission`). This is the most common setup and the one this guide starts with.
- **The `gemboot` guard**, which asks the same auth service but plugs into Laravel's own `auth` system. It works together with the middleware. See [One user everywhere](#one-user-everywhere-the-gemboot-guard) below.
- **The SSO guard**, which asks a separate user service. See [The SSO guard](#the-sso-guard) below.

Both need `GEMBOOT_AUTH_BASE_API` (or the SSO URLs) set, as described in [Installation](INSTALLATION.md).

## Protecting routes

After registering the middleware names in `bootstrap/app.php` ([Installation, step 3](INSTALLATION.md#3-register-the-middleware)):

```php
// Any logged-in user
Route::middleware('token-validated')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
});

// Logged-in users with the "admin" role
Route::middleware(['token-validated', 'role:admin'])->group(function () {
    Route::apiResource('users', UserController::class);
});

// Logged-in users with the "report.read" permission
Route::middleware(['token-validated', 'permission:report.read'])
    ->get('/reports', [ReportController::class, 'index']);
```

What the client gets back:

| Situation | Answer |
|---|---|
| No token, or the auth service rejects it | `401 Unauthorized` |
| Token is fine, but the role or permission is missing | `403 Forbidden` |
| The auth service can't be reached, or answers with a 5xx error | `503 Service Unavailable` |

The 503 matters for your frontend: it means "try again later", not "log out". Gemboot answers 503 so that users aren't logged out during an auth service outage.

### Several roles

Each `role:` and `permission:` middleware checks one value. To accept one of several roles, separate them with `|`:

```php
Route::middleware(['token-validated', 'role:admin|editor'])->group(...);
```

Gemboot sends `admin|editor` to the auth service as-is, so your auth service decides how to read it (usually "any of these").

## Reading the current user

`token-validated` asks the auth service's `me` endpoint who the token belongs to, and puts the answer on the request as `user_login`:

```php
public function show(Request $request)
{
    $userId = $request->user_login['id'];
    $name   = $request->user_login['name'];
    // ...
}
```

The fields are whatever your auth service returns for `me`.

You don't need to worry about `user_login` ending up in your database. `GembootResourceController` removes it before saving.

To get the same user as a real Laravel user, with `auth()->user()` and policies, add the `gemboot` guard ([below](#one-user-everywhere-the-gemboot-guard)).

## Checking permissions inside your code

Sometimes the check depends on data, not on the route. Use `GembootPermission` (registered as a facade):

```php
use GembootPermission;

if (GembootPermission::hasRole('admin')) {
    // ...
}

if (GembootPermission::hasPermissionTo('invoice.approve')) {
    // ...
}

// Throws GembootForbiddenException (403) if the permission is missing
GembootPermission::requirePermission('invoice.approve');
```

`hasPermissionTo()` also accepts an array, and is true if the user has **any** of them:

```php
GembootPermission::hasPermissionTo(['invoice.approve', 'invoice.admin']);
```

Inside `responseSuccessOrException()`, the exception from `requirePermission()` becomes a 403 response automatically.

## Who may see which record

Route middleware decides who may call an endpoint at all. To decide which **records** a user may see or change (for example only their own orders), use a Laravel policy with the resource controller. See [Controllers: policies](CONTROLLER.md#who-may-see-or-change-which-record-policies).

## One user everywhere: the `gemboot` guard

With only the middleware, the current user is an array in `$request->user_login`. Laravel's `auth()->user()`, `$request->user()`, `Gate`, and policies don't see it. The `gemboot` guard fixes that. It asks the same `me` endpoint, so your auth service doesn't need anything new.

In `config/auth.php`:

```php
'defaults' => [
    'guard' => 'api',
    // ...
],

'guards' => [
    'api' => ['driver' => 'gemboot'],
],
```

No user provider is needed. Now both of these routes get a real user:

```php
// With Laravel's auth middleware
Route::middleware('auth:api')->get('/profile', function (Request $request) {
    $user = $request->user(); // Gemboot\Auth\GembootUser

    return GembootResponse::responseSuccess([
        'id'   => $user->getAuthIdentifier(),
        'name' => $user->name,
    ]);
});

// With Gemboot's middleware, as before
Route::middleware('token-validated')->get('/orders', function () {
    $userId = auth()->id(); // the same user as $request->user_login
    // ...
});
```

In the second route, `token-validated` hands the user it already has to the guard, so there's no second call to the auth service. That only happens when the `gemboot` guard is the **default** guard, as in the config above.

What the user offers:

| | |
|---|---|
| `$user->name`, `$user->email`, ... | The fields of your auth service's `me` answer, the same as `user_login` |
| `$user->getAuthIdentifier()`, `auth()->id()` | The `id` field, or `user.id` when the answer wraps the user in `user` |
| `$user->hasRole('admin')` | Same as `GembootPermission::hasRole()`: asks the auth service |
| `$user->hasPermissionTo('report.read')` | Same as `GembootPermission::hasPermissionTo()` |
| `$user->toArray()` | The whole `me` answer |

Policies receive this user too, including the [resource controller's policy checks](CONTROLLER.md#who-may-see-or-change-which-record-policies).

What the client gets back on `auth:api` routes:

| Situation | Answer |
|---|---|
| No token, or the auth service rejects it | `401`, in Laravel's format: `{"message": "Unauthenticated."}` |
| The auth service can't be reached | `503` |
| Too many failed attempts from this IP | `429` |

On `token-validated` routes, the answers stay in the Gemboot format, as described above. Caching with `GEMBOOT_AUTH_CACHE_TTL` and the protection against token floods apply to the guard as well.

### Your own verifier

The guard asks a `TokenVerifier` who a token belongs to. The default one calls `me`. You can bind your own, for example one that also knows the user's roles and permissions:

```php
use Gemboot\Auth\GembootUser;
use Gemboot\Auth\TokenVerifier;

class MyTokenVerifier implements TokenVerifier
{
    public function verify(string $token): ?GembootUser
    {
        // Look the token up however your setup allows.
        // Return null for an invalid token.
        // Throw GembootServiceUnavailableException when you can't tell.

        return new GembootUser($attributes, roles: ['admin'], permissions: ['report.read']);
    }
}
```

```php
// AppServiceProvider::register()
$this->app->singleton(TokenVerifier::class, MyTokenVerifier::class);
```

When the user's roles or permissions are known like this, `role:` and `permission:` answer from them, without calling `has-role` or `has-permission-to`. They do that only after the guard has found the user in the same request, for example behind `auth:api`. Otherwise they ask the auth service, as always.

### Laravel's `can` with your permissions

Laravel checks abilities by name: `$this->authorize('report.read')`, `@can('report.read')`, `->can('report.read')` on a route, or `Gate::allows('report.read')`. Gemboot can answer those names with the permissions from your auth service:

```dotenv
GEMBOOT_PERMISSIONS_AS_ABILITIES=true
```

Then this works with the `gemboot` guard:

```php
Route::middleware('auth:api')
    ->get('/reports', [ReportController::class, 'index'])
    ->can('report.read');

public function export()
{
    return $this->responseSuccessOrException(function () {
        $this->authorize('report.export'); // 403 if the user lacks it

        return $this->service->export();
    });
}
```

Each check asks the auth service's `has-permission-to`, cached with `GEMBOOT_AUTH_CACHE_TTL`, unless [your own verifier](#your-own-verifier) already knows the permissions. A name with `|` needs all its parts, as with `permission:`.

Gemboot only answers names that your app leaves open. Three cases stay with Laravel:

- **Abilities you define yourself** with `Gate::define('report.read', ...)`. Your definition decides.
- **Policy checks**, which pass a model or class: `$this->authorize('update', $order)`.
- **Other users**: guests, and users from other guards.

Gemboot only ever grants. A missing permission leaves the decision to Laravel, which denies an ability nobody defined. It's off by default because it changes what `Gate::allows()` answers for names your app never defined.

## Calling the auth service directly: `AuthLibrary`

`Gemboot\Libraries\AuthLibrary` (also the `GembootAuth` facade) talks to the auth service for you. A typical use is offering login and logout routes in your API:

```php
use Gemboot\Libraries\AuthLibrary;
use Illuminate\Http\Request;

Route::prefix('auth')->group(function () {
    Route::post('login', fn (Request $request) => (new AuthLibrary)->login($request->npp, $request->password, true));
    Route::get('me', fn () => (new AuthLibrary)->me(true));
    Route::post('logout', fn () => (new AuthLibrary)->logout(true));
});
```

With `true` as the last argument, the method returns the auth service's answer as a JSON response, with the same status code. If the auth service can't be reached, you get a `503` in the Gemboot format instead. Without it, you get the data as an array, or `false` if the auth service said no:

```php
$auth = new AuthLibrary();
$user = $auth->me();               // array, or false

if ($user === false && $auth->isAuthServiceUnavailable()) {
    // The auth service didn't answer (no connection or a 5xx), as opposed to "invalid token"
}
```

All methods: `login($npp, $password)`, `me()`, `validateToken()`, `hasRole($role)`, `hasPermissionTo($permission)`, `logout()`. Each reads the token from the current request.

## Caching auth answers

By default, every protected request calls the auth service: once for `token-validated`, and once more for each `role:` or `permission:` check. You can cache the answers per token:

```dotenv
GEMBOOT_AUTH_CACHE_TTL=60
```

Now the same token is checked against the auth service at most once a minute per question. The trade-off: if the auth service revokes a token, your API keeps accepting it until the cached answer expires. `AuthLibrary::logout()` clears the cache for that token immediately. Outages are never cached.

Leave it at `0` (the default) if tokens must stop working the moment they're revoked.

## Short outages: keep users working

When the auth service can't be reached, or answers with a 5xx error, every protected request answers 503. For short outages, such as a restart or a deploy, you can let users who were just working keep working:

```dotenv
GEMBOOT_AUTH_OUTAGE_GRACE=60
```

Now, during an outage, Gemboot answers with the last good answer the auth service gave for that token, if that answer is recent enough. How recent:

| `GEMBOOT_AUTH_CACHE_TTL` | The last good answer is used if it's at most ... old |
|---|---|
| `0` | 60 seconds (the grace period) |
| `30` | 90 seconds (30 seconds of cache, then 60 seconds of grace) |

This covers `token-validated`, `role:`, `permission:`, `GembootPermission`, and the `gemboot` guard. Only successful answers are reused. A token the auth service rejected stays rejected, and a token nobody used before the outage still gets a 503. `AuthLibrary::logout()` clears the saved answers for that token.

The trade-off: a token revoked at the auth service shortly before the outage keeps working until its last answer is too old. Leave it at `0` (the default) if that's not acceptable.

Every outage still fires the `AuthServiceUnavailable` event (below), so your alerts work even while users keep working.

## Events for alerts and metrics

Gemboot fires two Laravel events. Listen to them to send alerts or count failures, without changing Gemboot.

| Event | Fired when | Properties |
|---|---|---|
| `Gemboot\Events\AuthServiceUnavailable` | A call to the auth service (or the SSO guard's user service) gets no connection or a 5xx answer | `endpoint` (`me`, `has-role`, `user/me`, ...), `status` (`0` = no connection), `servedFromLastGoodAnswer`, `requestId` |
| `Gemboot\Events\TokenRejected` | A token is refused, by the format check or by the auth service | `reason` (`malformed` or `rejected`), `source` (`token-validated`, `token-validated:client`, `gemboot-guard`, `sso-guard`), `ip`, `requestId` |

The token itself is never part of an event. Requests without a token fire nothing. `requestId` is filled when you use [request IDs](REQUEST_IDS.md), so an alert points to the exact request in your logs.

For example, a Slack alert when the auth service is down, at most once every five minutes:

```php
use Gemboot\Events\AuthServiceUnavailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

// AppServiceProvider::boot()
Event::listen(function (AuthServiceUnavailable $event) {
    if (Cache::add('auth-down-alerted', true, now()->addMinutes(5))) {
        Log::channel('slack')->critical('Auth service unavailable', [
            'endpoint' => $event->endpoint,
            'status' => $event->status,
            'request_id' => $event->requestId,
        ]);
    }
});
```

## Protection against token floods and password guessing

Every request with a token makes your API ask the auth service. Without limits, someone sending thousands of fake tokens would make every Gemboot service flood your auth service. Gemboot protects it in two ways, for the middleware, the SSO guard, and `AuthLibrary`.

**Junk is rejected locally.** An `Authorization` value that can't be a token, such as one with spaces or `<` inside, or longer than 8 KB, gets a 401 without a call to the auth service. A missing token is also answered locally. Real tokens are never affected: Gemboot allows an optional scheme name ("Bearer") followed by the characters [RFC 6750](https://www.rfc-editor.org/rfc/rfc6750#section-2.1) permits, which covers JWTs and opaque tokens.

**Failed attempts are limited per client IP.** After 60 failed attempts within a minute, the client gets a 429 for the rest of that minute, again without a call to the auth service:

```json
{ "status": 429, "message": "Too Many Requests", "data": { "error": "Too many failed authentication attempts" } }
```

The answer includes a `Retry-After` header with the seconds to wait. What counts as a failed attempt:

| Counts | Doesn't count |
|---|---|
| a token the auth service rejects | a request without any token |
| a malformed token | an auth service outage (503) |
| a failed `AuthLibrary::login()` (password guessing) | a missing role or permission (403) |

While blocked, the client IP gets 429 even with a valid token, until the minute is over.

Settings:

```dotenv
GEMBOOT_AUTH_MAX_FAILED_ATTEMPTS=60        # 0 turns the limit off
GEMBOOT_AUTH_FAILED_ATTEMPTS_DECAY=60      # seconds
GEMBOOT_AUTH_MAX_TOKEN_LENGTH=8192
```

**Behind a proxy or load balancer**, configure Laravel's [trusted proxies](https://laravel.com/docs/requests#configuring-trusted-proxies). Otherwise every client appears with the proxy's IP and shares one limit.

## The SSO guard

Instead of the middleware, you can use Laravel's own authentication with the `gemboot-sso-token` driver. Then `auth()->user()`, `$request->user()`, and Laravel's `auth:` middleware work as usual.

In `config/auth.php`:

```php
'guards' => [
    'api' => ['driver' => 'gemboot-sso-token', 'provider' => 'gemboot-sso'],
],

'providers' => [
    'gemboot-sso' => ['driver' => 'gemboot-sso-provider'],
],
```

In `.env`, point it at the user service that answers "who is this token":

```dotenv
GEMBOOT_USER_SERVICE_URL=https://users.example.com      # the guard calls {url}/user/me
# or the full URL:
# GEMBOOT_SSO_GET_USER_URL=https://users.example.com/api/me
```

Then:

```php
Route::middleware('auth:api')->get('/profile', function () {
    $user = auth()->user();

    return GembootResponse::responseSuccess([
        'id'          => $user->getAuthIdentifier(),
        'roles'       => $user->roles,
        'permissions' => $user->permissions,
    ]);
});
```

The guard asks for the user's roles and permissions in the same call, and caches the user per token for `GEMBOOT_SSO_CACHE_TTL` seconds (default 300). The user service must return an `id` field.

**Logout:** clear the cached user, so the token stops working right away:

```php
Auth::guard('api')->forgetCachedUser();
```

**Outages:** when the user service can't be reached or answers with a 5xx error, the guard answers `503`, like the auth middleware, so clients don't log users out. Before 8.14.1 it answered 500 (no connection) or 401 (5xx).

**Fallback service:** `GEMBOOT_USER_SERVICE_URL_FALLBACK` (or `GEMBOOT_SSO_GET_USER_URL_FALLBACK`) names a second user service. It is tried when the first can't be reached, and also when the first rejects the token. So a rejected token is sent to the fallback as well. A later major version will only use the fallback during outages.

## What your auth service must provide

For the middleware and `AuthLibrary`, these endpoints under `GEMBOOT_AUTH_BASE_API`:

| Request | Used by | Expected answer |
|---|---|---|
| `GET me` | `token-validated` | 200 with the user (a `data` wrapper is fine) |
| `GET validate-token` | `token-validated:client` | 200 if the token is valid |
| `GET has-role?role_name=admin` | `role:` | 200 with `{"has_role": true}` |
| `GET has-permission-to?permission_name=report.read` | `permission:` | 200 with `{"has_permission_to": true}`, plus `has_any_permission` when several names are joined with `|` |
| `POST login` | `AuthLibrary::login()` | Body fields `npp`, `password`, `hwid` |
| `POST logout` | `AuthLibrary::logout()` | 200 |

Gemboot forwards the client's `Authorization` header as-is. Any status other than 200 means "no", except connection errors and 5xx, which mean "unavailable" (503).

To check a real auth service against this table, run [`php artisan gemboot:contract-test`](COMMANDS.md#gembootcontract-test).

### Service-to-service calls: `token-validated:client`

For routes called by other services rather than users, `token-validated:client` only asks `validate-token` and doesn't load a user. Its error answers are short: `{"status": "Unauthorized"}` (401) or `{"status": "Service Unavailable"}` (503).

## Next

- [Testing your API](TESTING.md): test protected routes with a fake auth service
- [Models](MODEL.md), [Services](SERVICE.md), and [Controllers](CONTROLLER.md): build the API itself
- [Configuration](CONFIGURATION.md): all auth-related settings, including TLS and timeouts

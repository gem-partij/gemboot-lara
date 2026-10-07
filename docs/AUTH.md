# Authentication

After this guide, you'll be able to protect routes by login, role, and permission, read the current user, and set up the SSO guard. You'll also know exactly what your central auth service has to provide.

## How it works

Gemboot doesn't store users or check passwords. A separate **auth service** does that. Gemboot takes the bearer token from each request, asks the auth service about it, and lets the request through only if the auth service agrees.

```text
Client ──"Authorization: Bearer <token>"──▶ Your API (Gemboot middleware)
                                               │
                                               └─ GET me / has-role / has-permission-to ──▶ Auth service
```

There are two ways to use it:

- **The middleware** (`token-validated`, `role`, `permission`). This is the most common setup and the one this guide starts with.
- **The SSO guard**, which plugs into Laravel's own `auth` system. See [The SSO guard](#the-sso-guard) below.

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

### Service-to-service calls: `token-validated:client`

For routes called by other services rather than users, `token-validated:client` only asks `validate-token` and doesn't load a user. Its error answers are short: `{"status": "Unauthorized"}` (401) or `{"status": "Service Unavailable"}` (503).

## Next

- [Testing your API](TESTING.md): test protected routes with a fake auth service
- [Models](MODEL.md), [Services](SERVICE.md), and [Controllers](CONTROLLER.md): build the API itself
- [Configuration](CONFIGURATION.md): all auth-related settings, including TLS and timeouts

# Installation

After this guide, Gemboot is installed, knows where your auth service is, and protects your first route.

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13
- A central auth service that issues tokens and answers the endpoints described in [Authentication](AUTH.md#what-your-auth-service-must-provide)

For Laravel 11, use Gemboot 7.x (`composer require gem-partij/gemboot-lara:^7.0`).

## 1. Install the package

```sh
composer require gem-partij/gemboot-lara
```

Laravel finds the package automatically. You don't need to add a service provider or aliases to `config/app.php`: the facades (`GembootResponse`, `GembootAuth`, ...) and class aliases (`GembootResourceController`, `GembootNotFoundException`, ...) are registered for you.

## 2. Tell Gemboot where your auth service is

Add the auth service URL to `.env`:

```dotenv
GEMBOOT_AUTH_BASE_API=https://auth.example.com/api/auth
```

Gemboot calls endpoints such as `me` and `has-role` relative to this URL, so with the value above it calls `https://auth.example.com/api/auth/me`.

That is the only required setting. Every other setting has a default. You can see all of them in [Configuration](CONFIGURATION.md).

### Optional: publish the config file

```sh
php artisan vendor:publish --tag=gemboot
```

This copies the defaults to `config/gemboot.php`. Publish it only if you want to change values in the file itself; otherwise `.env` is enough.

### If your auth service uses a self-signed certificate

Gemboot checks TLS certificates on every call to the auth service. For a certificate from your own certificate authority, point Gemboot at that authority's certificate:

```dotenv
GEMBOOT_HTTP_VERIFY=/etc/ssl/certs/company-ca.pem
```

Only on a local development machine, you can turn the check off with `GEMBOOT_HTTP_VERIFY=false`. Never do that in production: without the check, anyone on the network path can read your users' tokens.

## 3. Register the middleware

Gemboot's route protection comes as three middleware. Give them short names in `bootstrap/app.php`:

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

## 4. Protect a route and check that it works

Add a test route to `routes/api.php`:

```php
use GembootResponse;
use Illuminate\Http\Request;

Route::middleware('token-validated')->get('/whoami', function (Request $request) {
    return GembootResponse::responseSuccess($request->user_login);
});
```

Call it without a token:

```sh
curl -i http://localhost:8000/api/whoami
```

```json
{ "status": 401, "message": "Unauthorized", "data": [] }
```

Call it with a token from your auth service:

```sh
curl -i http://localhost:8000/api/whoami -H "Authorization: Bearer <your token>"
```

```json
{ "status": 200, "message": "OK", "data": { "id": 7, "name": "Ana" } }
```

The `data` part is whatever your auth service returns for `me`.

If you get **503** instead, Gemboot could not reach the auth service. Check `GEMBOOT_AUTH_BASE_API`, the network, and the certificate settings above. The details are in your Laravel log.

## 5. Let Gemboot check your setup

```sh
php artisan gemboot:doctor
```

It checks the settings above, calls your auth service once, and lists anything that's wrong, each with a fix:

```text
Gemboot setup check

  ✓ Auth service URL: https://auth.example.com/api/auth
  ✓ TLS certificates are checked.
  ✓ The auth service answers (HTTP 401 for a request without a token).
  ✓ Auth middleware aliases are registered.
  ✓ Class aliases are registered.
  i Cache store 'file' has no tag support. CoreService caching (setObserver) is skipped, ...
  ✓ Security headers are added to responses.

Everything looks good.
```

Run it again whenever something behaves strangely, such as every request answering 401. See [Commands](COMMANDS.md#gembootdoctor) for the details.

## Next

- [Responses](RESPONSES.md): how every Gemboot response is shaped
- [Authentication](AUTH.md): roles, permissions, and the SSO guard

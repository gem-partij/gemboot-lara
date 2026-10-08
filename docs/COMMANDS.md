# Artisan commands

Gemboot's generators create models, services, and controllers that are already wired together. This page lists every command and option.

## The quickest start: `gemboot:make-model --all`

```sh
php artisan gemboot:make-model Product --all
```

Creates three classes that work together right away:

| File | Class |
|---|---|
| `app/Models/Product.php` | `Product extends GembootModel` |
| `app/Services/ProductService.php` | `ProductService extends GembootService` |
| `app/Http/Controllers/Api/Resources/ProductController.php` | `ProductController extends GembootResourceController` |

Add `Route::apiResource('products', ProductController::class);` and you have a full CRUD API ([Controllers](CONTROLLER.md)).

## `gemboot:make-model`

```sh
php artisan gemboot:make-model Product [options]
```

| Option | Creates |
|---|---|
| (none) | only the model, in `app/Models` (or `app` if that folder doesn't exist) |
| `--service`, `-s` | also `ProductService` |
| `--all`, `-a` | also `ProductService` and a resource controller `ProductController` |
| `--controller`, `-c` | also a `ProductController` (`GembootController`) |
| `--resource`, `-r` | makes the controller a resource controller |
| `--force` | overwrites existing files |

With `--service` (or `--all`), the controller is connected to `ProductService`. Without it, the controller only gets the model, and Gemboot uses a plain `GembootService` behind the scenes.

## `gemboot:make-service`

```sh
php artisan gemboot:make-service ProductService Product [options]
```

The first argument is the service name, the second the model it works with. Creates `app/Services/ProductService.php`.

| Option | Creates |
|---|---|
| `--controller`, `-c` | also `ProductController`, connected to the model and this service |
| `--resource`, `-r` | makes that controller a resource controller |
| `--force` | overwrites an existing service |

## `gemboot:make-controller`

```sh
php artisan gemboot:make-controller ProductController [options]
```

Controllers go to `app/Http/Controllers/Api`, or `app/Http/Controllers/Api/Resources` with `--resource`.

What you get depends on the options:

| Options | Base class | Constructor takes |
|---|---|---|
| (none) | `GembootProxyController` | nothing |
| `--model=Product` | `GembootController` | `Product $model` |
| `--model=Product --resource` | `GembootResourceController` | `Product $model` |
| `--model=Product --service=ProductService` | `GembootController` | `Product $model, ProductService $service` |
| `--model=Product --service=ProductService --resource` | `GembootResourceController` | `Product $model, ProductService $service` |

`--resource` only has an effect together with `--model`. `--force` overwrites an existing controller.

See [Controllers](CONTROLLER.md) for what each base class does.

## `gemboot:doctor`

```sh
php artisan gemboot:doctor [--skip-network]
```

Checks your Gemboot setup and explains how to fix each problem. Most setup mistakes don't cause errors, they just make every request answer 401 or quietly turn caching off, so this is the first thing to run when something seems wrong.

It checks:

- `GEMBOOT_AUTH_BASE_API` is set, is a real URL, and uses `https://`
- TLS certificate checks are on, and a configured CA bundle file exists
- the auth service answers (one request to `me` without a token; skip it with `--skip-network`)
- the SSO guard, if you use it, has a user service URL
- the middleware aliases and class aliases are registered
- your cache store supports tags (needed for service caching)
- deprecated or risky settings, such as `GEMBOOT_RESPONSE_COMPRESSED`

Each line starts with `✓` (fine), `i` (information), `!` (warning), or `✗` (problem). Warnings and problems come with a `Fix:` line.

The command exits with code `1` when it finds a problem, so you can run it in a deploy script or CI pipeline:

```sh
php artisan gemboot:doctor --skip-network || exit 1
```

## `gemboot:permissions`

```sh
php artisan gemboot:permissions [--json]
```

Lists every role and permission your routes check with `role:` and `permission:`, and which routes need them. Middleware groups and class names are included, not only the short aliases.

```text
Roles (1)
  admin
      GET /api/users
      POST /api/users

Permissions (3)
  report.read
      GET /api/reports
  user.raed
      GET /api/users/export
  user.read
      GET /api/users/{user}

Possible typos (names that differ by one or two characters):
  ! user.raed  <->  user.read
```

Use it to tell the auth team which roles and permissions a service needs, and to catch typos before they turn into 403s in production. With `--json`, the same information comes as JSON (`roles`, `permissions`, `possible_typos`) for scripts.

Permissions checked inside your code, such as `GembootPermission::requirePermission('...')`, aren't listed; only route middleware is.

## Built-in help

Every command describes its options:

```sh
php artisan gemboot:make-model --help
```

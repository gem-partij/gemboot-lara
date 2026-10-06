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

The controller from `--controller` uses `ProductService`, so combine it with `--service` (or simply use `--all`). Otherwise the controller refers to a service that doesn't exist yet.

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

## Built-in help

Every command describes its options:

```sh
php artisan gemboot:make-model --help
```

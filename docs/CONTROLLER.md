# Controllers

After this guide, you'll have a full CRUD API for a model in a few lines, and you'll know how to customize validation, saved data, and caching.

## A full CRUD API in one class

```sh
php artisan gemboot:make-model Product --all
```

Among other files, this creates `app/Http/Controllers/Api/Resources/ProductController.php`:

```php
namespace App\Http\Controllers\Api\Resources;

use GembootResourceController;
use App\Models\Product;
use App\Services\ProductService;

class ProductController extends GembootResourceController
{
    public function __construct(Product $model, ProductService $service)
    {
        parent::__construct($model, $service);
    }
}
```

Register it in `routes/api.php`:

```php
use App\Http\Controllers\Api\Resources\ProductController;

Route::middleware('token-validated')->apiResource('products', ProductController::class);
```

That's a complete API:

| Request | Method | Answer `data` |
|---|---|---|
| `GET /api/products` | `index` | a paginated list |
| `GET /api/products/5` | `show` | the product |
| `POST /api/products` | `store` | `{ "saved": { ...the new product } }` |
| `PUT /api/products/5` | `update` | `{ "saved": { ...the updated product } }` |
| `DELETE /api/products/5` | `destroy` | `{ "deleted": { ...the deleted product } }` |

Everything goes through the [response format](RESPONSES.md). A missing id gives `404`, a database error gives `500`, and the list supports search, sorting, and paging ([query parameters](ROUTES.md#query-parameters-for-index)).

For example, `POST /api/products` with `{"name": "Kopi", "price": 15000}`:

```json
{
    "status": 200,
    "message": "OK",
    "data": {
        "saved": { "id": 3, "name": "Kopi", "price": 15000, "created_at": "2026-10-06T07:55:00.000000Z", "updated_at": "2026-10-06T07:55:00.000000Z" }
    }
}
```

## Customizing the resource controller

### Validation

Override `validateStoreRequest()` and `validateUpdateRequest()`. Return a Laravel validator:

```php
protected function validateStoreRequest($request)
{
    return \Validator::make($request->all(), [
        'name'  => 'required|max:100',
        'price' => 'required|integer|min:0',
    ]);
}

protected function validateUpdateRequest($request, $id)
{
    return \Validator::make($request->all(), [
        'price' => 'integer|min:0',
    ]);
}
```

If validation fails, the client gets a 400 with the errors under `data.errors`:

```json
{ "status": 400, "message": "Bad Request", "data": { "errors": { "name": ["The name field is required."] } } }
```

Nothing is saved, and the database transaction is rolled back.

### Fixed values on save

Values the client must not choose, such as a tenant or owner id, go into these properties. They are merged into the client's data and win over it:

```php
protected $merge_store_data_with = ['status' => 'draft'];
```

For values that depend on the request, set them in the constructor or a hook:

```php
protected function beforeStoreHooks($request)
{
    $this->merge_store_data_with['created_by'] = $request->user_login['id'];
}
```

`$merge_update_data_with` does the same for `update`.

### Hooks

Override these to add behavior around saving:

| Hook | Runs | Typical use |
|---|---|---|
| `beforeStoreHooks($request)` | before saving | set fixed values; return something to skip the normal save and use your return value instead |
| `afterStoreHooks($savedData, $request)` | after saving, still inside the transaction | write related rows |
| `afterStoreCommitHooks($savedData, $request)` | after the transaction is committed | send emails, dispatch jobs |
| `beforeUpdateHooks($request, $id)` | before updating | same as for store |
| `afterUpdateHooks($savedData, $request, $id)` | after updating, inside the transaction | |
| `afterUpdateCommitHooks($savedData, $request)` | after the commit | |

`store` and `update` run inside a database transaction. If anything throws before the commit, everything is rolled back. Put side effects that can't be undone, like emails, in the `...CommitHooks`.

Data logic that isn't about HTTP belongs in the [service hooks](SERVICE.md#adding-logic-before-and-after-saving-hooks).

### Eager loading relations

```php
protected $with = ['category'];
```

`index` and `show` then load the category with each product. To load it only in `index`, also set `protected $addWithOnShow = false;`.

### Caching

```php
protected $cache_seconds = [
    'index' => 60,
    'show'  => 300,
];
```

See [Caching](CACHING.md#the-controller-cache) for how the cache is keyed per user and cleared when data changes.

### Replacing an action

Every action is a normal method, so you can override it. Keep using `responseSuccessOrException()` so errors keep their format:

```php
public function index()
{
    return $this->responseSuccessOrException(function () {
        return $this->service->listAll(Product::where('active', true));
    });
}
```

## The other two controllers

`GembootResourceController` is the one you'll use most. Gemboot has two more:

- **`GembootController`** gives you `$this->model`, `$this->service`, and all [response helpers](RESPONSES.md#responding-directly), but no actions. Use it for endpoints that aren't plain CRUD:

  ```php
  class ReportController extends GembootController
  {
      public function __construct(Order $model, OrderService $service)
      {
          parent::__construct($model, $service);
      }

      public function monthly()
      {
          return $this->responseSuccessOrException(fn () => $this->service->monthlyTotals());
      }
  }
  ```

- **`GembootProxyController`** passes any method call it doesn't have on to its service. A route to `ReportController@monthlyTotals` would call `$service->monthlyTotals()` directly. Note that the result isn't wrapped in the response format; the other two controllers are usually the better choice.

## Next

- [Routes and query parameters](ROUTES.md)
- [Caching](CACHING.md)

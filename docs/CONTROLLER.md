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

#### With a FormRequest class

If you already keep your rules in Laravel `FormRequest` classes, name them instead of overriding the two methods:

```php
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;

class ProductController extends GembootResourceController
{
    protected $storeRequest = StoreProductRequest::class;
    protected $updateRequest = UpdateProductRequest::class;
}
```

The class is an ordinary FormRequest:

```php
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GembootPermission::hasPermissionTo('product.update');
    }

    public function rules(): array
    {
        return [
            'price' => 'integer|min:0',
            // The route parameter works as usual: "product" in /products/{product}.
            'sku' => [Rule::unique('products')->ignore($this->route('product'))],
        ];
    }
}
```

Everything a FormRequest normally does still runs: `prepareForValidation()`, `authorize()`, the rules, and `passedValidation()`. The answers stay in the Gemboot format:

| What happens | The client gets |
|---|---|
| A rule fails | 400 with the errors under `data.errors`, the same as above (not Laravel's usual 422) |
| `authorize()` returns `false` | 403 with `data.error` |

The hooks receive the FormRequest as `$request`, so they can call `$request->validated()`. The saved data is the FormRequest's input, including any changes `prepareForValidation()` made. When both are set, `$storeRequest` wins over `validateStoreRequest()`.

Don't override `failedValidation()` or `failedAuthorization()` in these classes. Gemboot answers both cases itself.

### Who may see or change which record: policies

By default, any user who passes your route middleware can read, change, or delete **any** record by its id. For data that belongs to users, such as orders or profiles, that's usually wrong: user 7 shouldn't be able to open `/api/orders/12` if order 12 belongs to someone else.

Write a normal [Laravel policy](https://laravel.com/docs/authorization#creating-policies) and turn on policy checks in the controller:

```php
namespace App\Policies;

use App\Models\Order;

class OrderPolicy
{
    public function viewAny($user): bool             { return true; }
    public function view($user, Order $order): bool  { return $order->user_id == $user->id; }
    public function create($user): bool              { return true; }
    public function update($user, Order $order): bool { return $order->user_id == $user->id; }
    public function delete($user, Order $order): bool { return false; }
}
```

```php
class OrderController extends GembootResourceController
{
    protected $authorizeWithPolicy = true;
}
```

Laravel finds `App\Policies\OrderPolicy` for `App\Models\Order` automatically. Now each action checks the matching policy method:

| Action | Policy method |
|---|---|
| `index` | `viewAny($user)` |
| `show` | `view($user, $record)` |
| `store` | `create($user)` |
| `update` | `update($user, $record)` |
| `destroy` | `delete($user, $record)` |

When a method returns `false`, the client gets a 403 and nothing is saved:

```json
{ "status": 403, "message": "Forbidden", "data": { "error": "This action is unauthorized." } }
```

Notes:

- **Which `$user`:** with the SSO guard, it's the guard's user. With the `token-validated` middleware, it's the `user_login` data, so `$user->id` and the other fields from your auth service's `me` answer work. To use your own user model instead, override `policyUser()` in the controller.
- **Guests** (no user at all) are denied unless a policy method accepts a nullable user (`?User $user`).
- **Cached records are checked too.** The policy runs after the record is loaded, also when it comes from the `show` cache.
- Policy checks are **off by default**, so existing policies elsewhere in your app don't suddenly change your API. Controllers without `$authorizeWithPolicy = true` behave as before.
- **No policy found means no access.** With `$authorizeWithPolicy = true` but no policy for the model (for example a wrong namespace or a typo in the class name), every action answers 500, and your log names the missing policy. The checks are never skipped silently.

### Save only validated fields

By default, `store` and `update` pass everything the client sent to the model, filtered only by its `$fillable`. A model with `$guarded = []` then accepts **any** field, including ones like `is_admin` or `balance` that clients must never set.

Turn on this option to save only the fields that have a validation rule:

```php
class ProductController extends GembootResourceController
{
    protected $saveValidatedOnly = true;

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
            'name'  => 'sometimes|max:100',
            'price' => 'sometimes|integer|min:0',
        ]);
    }
}
```

A field without a rule is dropped, even if the model would accept it. Fixed values from `$merge_store_data_with` and `$merge_update_data_with` are still added.

With a [FormRequest class](#with-a-formrequest-class), the fields with a rule in its `rules()` are saved.

With the option on but no rules in `validateStoreRequest()` or `validateUpdateRequest()` (or the FormRequest's `rules()`), nothing could ever be saved. Instead of silently storing nothing, that action answers 500, and your log names the method that needs rules.

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

### Default sort order

```php
protected $orderBy = ['created_at' => 'desc'];
```

`index` then returns the newest records first, unless the client sends its own `?order=`. List several columns in order of priority. A plain list such as `['name', 'price']` sorts ascending.

### Paging mode

```php
protected $pagination = 'cursor';   // or 'simple'; default 'paginate'
```

`simple` and `cursor` skip the `COUNT(*)` query that `paginate` runs on every list. See [Faster paging for large tables](ROUTES.md#faster-paging-for-large-tables).

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

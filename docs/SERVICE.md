# Services

After this guide, you'll know what a Gemboot service does for you, how to use its methods from a controller, and how to add your own logic before and after saving.

## What a service is

A service holds the data logic for one model: listing with search and paging, finding, saving, and deleting. The controller stays small and only deals with HTTP.

Generate one for an existing model:

```sh
php artisan gemboot:make-service ProductService Product
```

This creates `app/Services/ProductService.php`:

```php
namespace App\Services;

use Gemboot\Services\CoreService as GembootService;
use App\Models\Product;

class ProductService extends GembootService
{
    public function __construct(?Product $model = null, $with = [], $orderBy = [])
    {
        if (empty($model)) {
            $model = new Product();
        }
        parent::__construct($model, $with, $orderBy);
    }
}
```

That's already a working service. Everything below is inherited from `GembootService` (full name `Gemboot\Services\CoreService`).

## Using a service

```php
$service = new ProductService();

$page    = $service->listAll();          // paginated list, honoring ?search, ?order, ?page_len
$count   = $service->countAll();         // number of rows, honoring ?search
$product = $service->findOrFail(5);      // one record, or a 404 inside responseSuccessOrException()

$product = $service->store(['name' => 'Kopi', 'price' => 15000]);
$product = $service->update(['price' => 17000], 5);
$deleted = $service->delete(5);
```

Inside a controller you usually get the service injected and call it within `responseSuccessOrException()`:

```php
public function index()
{
    return $this->responseSuccessOrException(fn () => $this->service->listAll());
}
```

`GembootResourceController` already does this for `index`, `show`, `store`, `update`, and `destroy`. See [Controllers](CONTROLLER.md).

### All methods

| Method | Does |
|---|---|
| `listAll($query = null)` | Paginated list. Reads `?search`, `?search_field`, `?order`, `?atoz`, `?page_len` from the request ([details](ROUTES.md#query-parameters-for-index)). |
| `countAll($query = null)` | Counts the rows that `listAll()` would find. |
| `findOrFail($id)` | One record by primary key. |
| `firstOrFail($query)` | The first record of your own query. |
| `store($data, $mergeWith = [])` | Creates a record from `$data` plus `$mergeWith`. |
| `update($data, $id, $mergeWith = [])` | Updates the record with that id. |
| `updateOrCreate($where, $data, $mergeWith = [])` | Eloquent's `updateOrCreate()`. |
| `updateUseModel($model, $data, $mergeWith = [])` | Updates a model you already loaded. |
| `delete($id)` | Deletes the record and returns it. |
| `setWith(['category'])` | Relations to eager load in `listAll()` and `findOrFail()`. |
| `setOrderBy(['created_at' => 'desc'])` | Default sort for `listAll()`, used when the request has no `?order=`. `['name']` sorts ascending. |
| `setPagination('cursor')` | How `listAll()` pages: `'paginate'` (default), `'simple'`, or `'cursor'` ([details](ROUTES.md#faster-paging-for-large-tables)). |

`store()` and `update()` only set the fields listed in the model's `$fillable`.

The constructor takes the same two settings: `new ProductService(null, ['category'], ['created_at' => 'desc'])`. When a controller creates or receives the service, the controller's `$with` and `$orderBy` properties are used instead.

### Your own query

Pass a query to `listAll()` to start from it. Search, sorting, and paging from the request are added on top:

```php
// Only active products, still searchable and paginated
$service->listAll(Product::where('active', true));
```

### Fixed values on save: `$mergeWith`

The second argument of `store()` is merged into the data, after the client's input. Use it for values the client must not choose:

```php
$service->store($request->all(), ['created_by' => $request->user_login['id']]);
```

## Adding logic before and after saving: hooks

Override these methods in your service. They receive the data **by reference**, so you can change it before it's saved:

```php
class ProductService extends GembootService
{
    protected function beforeStoreHooks(&$requestData, &$merge_data_with)
    {
        $requestData['slug'] = Str::slug($requestData['name']);
    }

    protected function afterStoreHooks(&$savedData, &$requestData, &$merge_data_with)
    {
        event(new ProductCreated($savedData));
    }
}
```

| Hook | Runs |
|---|---|
| `beforeStoreHooks(&$requestData, &$merge_data_with)` | before `store()` saves |
| `afterStoreHooks(&$savedData, &$requestData, &$merge_data_with)` | after `store()` saves |
| `beforeUpdateHooks(&$requestData, &$id, &$merge_data_with)` | before `update()` saves |
| `afterUpdateHooks(&$savedData, &$requestData, &$id, &$merge_data_with)` | after `update()` saves |
| `beforeDeleteHooks(&$id)` | before `delete()` |
| `afterDeleteHooks(&$deletedData, &$id)` | after `delete()` |

To stop an operation, throw a Gemboot exception in a `before` hook, for example `throw new GembootConflictException('Name already used');`. Inside `responseSuccessOrException()`, it becomes the matching error response.

## Customizing the list query

To change how `listAll()` builds its query, for example to always limit results to the current user, override `getQueryListAll()`:

```php
protected function getQueryListAll($model = null, $disable_search = false)
{
    $query = Product::where('owner_id', auth()->id());

    return parent::getQueryListAll($query, $disable_search);
}
```

If you cache results, read the per-user note in [Caching](CACHING.md#cached-results-are-per-user).

## Caching

Services can cache `listAll()` and `findOrFail()`, and clear the cache automatically when data changes. See [Caching](CACHING.md).

## Next

- [Controllers](CONTROLLER.md)
- [Caching](CACHING.md)

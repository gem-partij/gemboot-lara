# Routes and query parameters

After this guide, you'll know how to register Gemboot routes, and how clients search, sort, and page through lists.

## Registering routes

A resource controller needs one line in `routes/api.php`:

```php
use App\Http\Controllers\Api\Resources\ProductController;

Route::middleware('token-validated')->group(function () {
    Route::apiResource('products', ProductController::class);
});
```

`apiResource()` registers `index`, `store`, `show`, `update`, and `destroy`. To expose only some of them:

```php
Route::apiResource('products', ProductController::class)->only(['index', 'show']);
```

Different protection per action, for example reading for everyone logged in and writing for admins:

```php
Route::middleware('token-validated')->group(function () {
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('products', ProductController::class)->except(['index', 'show']);
    });
});
```

See [Authentication](AUTH.md) for `token-validated`, `role:`, and `permission:`.

## Query parameters for `index`

Every `index` of a resource controller, and every `listAll()` call of a service, understands these parameters.

### Searching

| Parameter | Example | Finds |
|---|---|---|
| `search` + `search_field` | `?search=kopi&search_field=name` | `name` contains "kopi" |
| `search` alone | `?search=kopi` | any column contains "kopi" (except the id, timestamps, and hidden columns) |
| `search_exact` + `search_field` | `?search_exact=3&search_field=category_id` | `category_id` is exactly 3 |
| `search_mode` | `?search=kopi&search_field=name&search_mode=and` | `or` (default) or `and`, how this search combines with others |
| relation | `?search=drinks&search_field=category.name` | the related category's name contains "drinks" ([rules](MODEL.md#searching-related-models)) |
| date | `?search=2026-10&search_field=created_at` | created in October 2026 ([formats](MODEL.md#searching-by-date)) |

Several fields at once, as arrays:

```text
?search[]=kopi&search_field[]=name&search[]=drinks&search_field[]=category.name&search_mode=and
```

finds products whose name contains "kopi" **and** whose category name contains "drinks".

### Sorting

| Parameter | Example | Effect |
|---|---|---|
| `order` | `?order=price` | sort by `price`, ascending |
| `atoz` | `?order=price&atoz=desc` | `asc` (default) or `desc` |

Several columns: `?order[]=category_id&order[]=price&atoz[]=asc&atoz[]=desc`.

### Paging

| Parameter | Example | Effect |
|---|---|---|
| `page` | `?page=2` | page number (Laravel's standard parameter) |
| `page_len` | `?page_len=50` | rows per page. Default 30, at most 1000 (`GEMBOOT_MAX_PAGE_LEN`). |
| `page_len=all` | `?page_len=all` | everything in one plain list (no paginator) if there are at most 1000 rows; otherwise pages of 999 |

A paginated answer looks like this (shortened):

```json
{
    "status": 200,
    "message": "OK",
    "data": {
        "current_page": 1,
        "data": [
            { "id": 1, "name": "Kopi", "price": 15000 }
        ],
        "per_page": 30,
        "total": 42,
        "last_page": 2,
        "next_page_url": "https://api.example.com/api/products?page=2",
        "prev_page_url": null
    }
}
```

The records are in `data.data`. The rest is Laravel's standard paginator information.

### Errors you may see

All return `400 Bad Request`:

- `search_field` names a relation that doesn't exist or isn't allowed
- `search_field` or `order` names a hidden column (password, tokens, ...)
- `atoz` is anything other than `asc` or `desc`

## Next

- [Caching](CACHING.md)
- [Services](SERVICE.md#your-own-query): start `listAll()` from your own query

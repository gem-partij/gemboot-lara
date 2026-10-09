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

Without `?order=`, the list uses the controller's or service's default sort (`$orderBy`), if one is set. Otherwise the database decides the order.

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

#### Faster paging for large tables

The answer above includes `total`, which costs an extra `COUNT(*)` query on every request. On large tables that query gets slow. A controller (or service) can choose another mode:

```php
class LogController extends GembootResourceController
{
    protected $pagination = 'cursor';
}
```

| Mode | Answer includes | Client asks for the next page with | Count query |
|---|---|---|---|
| `paginate` (default) | `total`, `last_page`, page links | `?page=2` | yes |
| `simple` | `next_page_url`, `prev_page_url` | `?page=2` | no |
| `cursor` | `next_cursor`, `prev_cursor`, and their URLs | `?cursor=<next_cursor>` | no |

`cursor` stays fast on any page of any table, because it doesn't skip rows with `OFFSET`. It works with every `order`; Gemboot adds the primary key as a tie-breaker, so rows with equal sort values are never skipped or shown twice. Clients can't jump to a page number in this mode.

### Standard parameter names (opt-in)

Many frontends and API tools use the names from JSON:API and Spatie's query builder. Turn them on to accept them next to Gemboot's own names:

```dotenv
GEMBOOT_STANDARD_QUERY_PARAMETERS=true
```

| Standard name | Example | Same as |
|---|---|---|
| `sort` | `?sort=-created_at,name` | `order` + `atoz`: comma-separated, `-` for descending |
| `per_page` | `?per_page=50` | `page_len` |
| `filter[...]` | `?filter[status]=paid&filter[category.name]=drinks` | `search_exact` per field, all required |

When both names are sent, Gemboot's own (`order`, `page_len`) win. Filters combine with `search` (both must match). Filters go through the same checks as search: hidden columns and invalid relation names get a 400.

They're off by default in 8.x because some clients may already send these names and rely on them being ignored. In 9.0 they will be on by default.

### Errors you may see

All return `400 Bad Request`:

- `search_field` names a relation that doesn't exist or isn't allowed
- `search_field` or `order` names a hidden column (password, tokens, ...)
- `search_field` or `order` names a field the model doesn't list in `$searchableFields` or `$sortableFields` ([details](MODEL.md#limiting-what-clients-can-search-and-sort))
- `atoz` is anything other than `asc` or `desc`
- `order` or `sort` names a column the table doesn't have
- a `filter[...]` value is an array instead of a single value

## Next

- [Caching](CACHING.md)
- [Services](SERVICE.md#your-own-query): start `listAll()` from your own query

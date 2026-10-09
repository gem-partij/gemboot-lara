# Caching

After this guide, you'll be able to cache lists and records, have the cache cleared automatically when data changes, and avoid showing one user another user's data.

Gemboot has two independent caches:

- **The service cache** for `listAll()` and `findOrFail()`. It's cleared automatically when a record is saved or deleted.
- **The controller cache** for a resource controller's `index` and `show`. It expires after a number of seconds you choose.

There's a third, unrelated cache for answers from the auth service. It's described in [Authentication](AUTH.md#caching-auth-answers).

## The service cache

### 1. Use a cache store with tags

The service cache needs a cache store that supports tags: **redis**, **memcached**, or **array** (for tests). In `.env`:

```dotenv
CACHE_STORE=redis
```

On `file` or `database` stores, Gemboot simply doesn't cache. It can't clear old entries there, and serving old data would be worse than no cache.

### 2. Create an observer

The observer clears the cache whenever a record is saved, deleted, or restored. Set `$cacheTag` to the model's table name:

```php
namespace App\Observers;

use Gemboot\Observers\CoreEloquentCachingObserver;

class ProductCachingObserver extends CoreEloquentCachingObserver
{
    protected $cacheTag = 'products';
}
```

### 3. Register it on the model, and turn caching on in the service

In `app/Providers/AppServiceProvider.php`:

```php
use App\Models\Product;
use App\Observers\ProductCachingObserver;

public function boot(): void
{
    Product::observe(ProductCachingObserver::class);
}
```

In the service:

```php
class ProductService extends GembootService
{
    public function __construct(?Product $model = null, $with = [], $orderBy = [])
    {
        parent::__construct($model ?? new Product(), $with, $orderBy);

        $this->setObserver(new ProductCachingObserver());
        $this->setDefaultCacheLifetime(60 * 60);   // seconds; default is 24 hours
    }
}
```

Now `listAll()` and `findOrFail()` are cached. Each combination of query parameters gets its own entry. Saving or deleting any product clears all of them.

Changes that bypass Eloquent's events, such as `Product::query()->update([...])` or raw SQL, don't clear the cache. Use model methods (`save()`, `update()`, `delete()`) or clear it yourself with `Cache::tags(['products'])->flush()`.

## The controller cache

```php
class ProductController extends GembootResourceController
{
    protected $cache_seconds = [
        'index' => 60,    // cache lists for a minute
        'show'  => 300,   // cache single products for five minutes
    ];
}
```

With a tag-capable store and the observer from above, this cache is also cleared when a product changes. On other stores it simply expires after the given seconds.

## Cached results are per user

Lists often depend on who is asking, for example "my orders". If two users shared a cache entry, one could see the other's data. So by default, **every cached result belongs to the logged-in user**: the cache key includes `auth()->id()` (when someone is logged in through a Laravel guard) and, with `token-validated`, the current user's data.

The cost is fewer cache hits, since each user fills their own cache.

When the results really are the same for everyone, such as a list of countries, share them by returning `null` from `cacheScope()`:

```php
class CountryService extends GembootService
{
    protected function cacheScope()
    {
        return null;   // same list for every user
    }
}
```

The resource controller has the same `cacheScope()` method for its own cache.

Only share when you're sure the query doesn't depend on the user, including code in `getQueryListAll()` overrides and in the queries you pass to `listAll()`.

## Laravel 13: the cache allow-list

Laravel 13 can limit which classes come back out of the cache, with `serializable_classes` in `config/cache.php`. New Laravel 13 projects may set it to `false` (no classes at all), and older projects don't have the key, which means no limit.

The service cache and the controller cache store **objects**: your models and the paginator around them. With the allow-list on, they come back broken (`__PHP_Incomplete_Class`), and the request fails with an error. List the classes they need:

```php
// config/cache.php
'serializable_classes' => [
    // Every model you cache, and the models of the relations it loads ($with).
    App\Models\Product::class,
    App\Models\Category::class,

    // Always needed.
    Illuminate\Database\Eloquent\Collection::class,

    // The paginator of the paging mode you use.
    Illuminate\Pagination\LengthAwarePaginator::class,  // 'paginate' (the default)
    Illuminate\Pagination\Paginator::class,             // 'simple'
    Illuminate\Pagination\CursorPaginator::class,       // 'cursor'
    Illuminate\Pagination\Cursor::class,                // 'cursor'
],
```

A quick check after changing the list: call a cached list twice. The second call comes from the cache and must answer the same.

Gemboot's **auth** caches (`GEMBOOT_AUTH_CACHE_TTL`, the outage grace period, the SSO guard's user cache) only store plain arrays. The allow-list doesn't affect them.

## Next

- [Configuration](CONFIGURATION.md)

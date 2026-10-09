# Models

After this guide, your Eloquent models work with Gemboot's services and controllers, and you know how to search them, both from code and through the API.

## Turning a model into a Gemboot model

Change one line: extend `GembootModel` instead of Eloquent's `Model`.

Before:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'price', 'category_id'];
}
```

After:

```php
namespace App\Models;

use GembootModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends GembootModel
{
    use HasFactory;

    protected $fillable = ['name', 'price', 'category_id'];
}
```

`GembootModel` (full name `Gemboot\Models\CoreModel`) extends Eloquent's `Model`. Everything you know from Eloquent keeps working. Gemboot only adds search scopes, which the services use to answer `?search=` in the API.

Two Eloquent settings matter more than usual, because a Gemboot API exposes your models directly:

- **`$fillable`** decides which fields a client can set through `store` and `update`. Keep it to the fields clients may really change.
- **`$hidden`** keeps fields out of JSON responses. Put passwords, tokens, and other secrets there. Gemboot also refuses to search or sort by hidden fields.
- **`$searchableFields` and `$sortableFields`** decide what clients may search and sort by. See [Limiting what clients can search and sort](#limiting-what-clients-can-search-and-sort).

## Searching from code

The search scopes are available on every Gemboot model:

```php
// LIKE '%ana%' on one column
Product::search('ana', 'name')->get();

// Exact match
Product::searchExact(42, 'category_id')->get();

// LIKE on every column (except the primary key, timestamps, and $hidden columns)
Product::search('ana')->get();

// Several columns at once: name LIKE '%kopi%' AND category_id = 3
Product::searchMultiple(['kopi'], ['name'], 'and')
    ->searchExactMultiple([3], ['category_id'], 'and')
    ->get();
```

Notes:

- On PostgreSQL, the services use `ILIKE`, so text search ignores upper and lower case.
- If the search text contains `%`, Gemboot uses it as your own pattern: `search('kopi%', 'name')` finds names that *start* with "kopi".
- The third argument is the mode: `'or'` (the default) or `'and'`. It decides how this condition joins the conditions **before** it.

### Searching inside an already limited query

With the default `'or'` mode, a search added to a limited query **widens** it:

```php
// WRONG: finds my orders OR any order named "kopi"
Order::where('user_id', $userId)->search('kopi', 'name')->get();
```

Wrap the search in its own group, so it can only narrow the query:

```php
// Right: my orders whose name contains "kopi"
Order::where('user_id', $userId)
    ->where(fn ($q) => $q->search('kopi', 'name'))
    ->get();
```

Gemboot's services do this for you: searches from the request (`?search=`, `?search_exact=`) are always grouped this way since 8.6.1, so a list you limit with `listAll($query)` or `getQueryListAll()` stays limited.

### Searching by date

For `created_at`, `updated_at`, and `deleted_at`, the search value is read as a date:

| Value | Finds |
|---|---|
| `2026-10-06` | that day |
| `2026-10` | that month |
| `2026` | that year |

```php
Product::search('2026-10', 'created_at')->get();   // created in October 2026
```

### Searching related models

Use `relation.column` to search a related model. With a `category()` relation on `Product`:

```php
public function category(): BelongsTo
{
    return $this->belongsTo(Category::class);
}
```

```php
Product::search('drinks', 'category.name')->get();
```

Through the API this is `?search=drinks&search_field=category.name`. Because clients can choose the relation name, Gemboot only accepts real relations:

- a public method on your model, with no required parameters
- not a method inherited from Eloquent or Gemboot (`save`, `delete`, `truncate`, ...)
- if it declares a return type, that type must be a relation (`BelongsTo`, `HasMany`, ...)

Anything else gets a `400 Bad Request`.

To allow only specific relations, list them. This is the safest option for models exposed through the API:

```php
class Product extends GembootModel
{
    protected $searchableRelations = ['category'];
}
```

We also recommend declaring return types on relations (`: BelongsTo`). Then Gemboot can tell a relation from any other public method.

## Limiting what clients can search and sort

Without any list, clients can search and sort by every column that isn't in `$hidden`. That's convenient, but it also means a new column is searchable the moment you add it, and a search on a large unindexed text column can slow down the whole table.

List what clients may use instead:

```php
class Product extends GembootModel
{
    protected $searchableFields = ['name', 'sku', 'created_at', 'category.name'];
    protected $sortableFields   = ['name', 'price', 'created_at'];
}
```

Now:

| Request | Answer |
|---|---|
| `?search=kopi&search_field=name` | searches `name` |
| `?search=drinks&search_field=category.name` | searches the related category's name |
| `?search=kopi&search_field=description` | `400 Bad Request`: not listed |
| `?search=kopi` (no field) | searches only the listed columns of the table itself: `name` and `sku` here |
| `?order=price` | sorts by price |
| `?order=stock` | `400 Bad Request`: not listed |

Details:

- **Relations** are listed as `relation.column`, and only that exact column of that relation is allowed. `category.name` doesn't allow `category.secret_note`.
- **Search without a field** skips the primary key and the date columns, as before, even when they're listed. If nothing is left to search, it finds nothing instead of everything.
- **Date search** (`?search=2026-10&search_field=created_at`) needs the date column in the list.
- **`filter[...]`** ([standard parameters](ROUTES.md#standard-parameter-names-opt-in)) follows `$searchableFields`, and **`sort`** follows `$sortableFields`.
- **Your own default sort** (`$orderBy` on a controller or service) isn't limited. Only what the client asks for is.
- `$hidden` still applies on top of the lists: a hidden column stays unsearchable even if you list it.

The two lists are independent. You can set only one of them.

Both lists are optional in 8.x. **9.0 will require them** on models exposed through the API, so adding them now makes that upgrade a no-op for your app.

## Composite primary keys

For tables whose primary key spans two columns, add the `HasCompositePrimaryKey` trait:

```php
use Gemboot\Traits\HasCompositePrimaryKey;

class Enrollment extends GembootModel
{
    use HasCompositePrimaryKey;

    protected $primaryKey = ['student_id', 'course_id'];
    public $incrementing = false;
}
```

Saving and updating then use both columns. To find a record, pass the values in the same order as `$primaryKey`:

```php
$enrollment = Enrollment::find([7, 12]);         // student 7, course 12
$enrollment = Enrollment::findOrFail([7, 12]);
```

## Next

- [Services](SERVICE.md): where your queries and saving logic go
- [Routes and query parameters](ROUTES.md#searching): search from the client side

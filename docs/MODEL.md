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
- The third argument is the mode: `'or'` (the default) or `'and'`. It decides how this condition combines with others.

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

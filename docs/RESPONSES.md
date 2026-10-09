# Responses

After this guide, you'll know what every Gemboot response looks like, which helper to use for which answer, and how to return errors by throwing exceptions.

## Every response has the same shape

```json
{
    "status": 200,
    "message": "OK",
    "data": { "id": 1, "name": "Ana" }
}
```

- `status` is the HTTP status code, repeated in the body.
- `message` is the standard HTTP status text: `OK`, `Bad Request`, `Not Found`, and so on.
- `data` is your payload. For errors it usually holds an `error` key.

Clients can parse every Gemboot service the same way. This shape never changes within a major version.

## The usual way: `responseSuccessOrException()`

Wrap your code in a callback. Whatever the callback returns becomes `data`. Anything it throws becomes an error response.

```php
use GembootResponse;
use App\Models\User;

public function show($id)
{
    return GembootResponse::responseSuccessOrException(function () use ($id) {
        return User::findOrFail($id);
    });
}
```

Found:

```json
{ "status": 200, "message": "OK", "data": { "id": 1, "name": "Ana", "email": "ana@example.test" } }
```

Not found (`findOrFail()` throws Eloquent's `ModelNotFoundException`):

```json
{ "status": 404, "message": "Not Found", "data": { "error": "Data Not Found!" } }
```

Inside a controller that extends a Gemboot controller, the same methods are available on `$this`:

```php
return $this->responseSuccessOrException(fn () => User::findOrFail($id));
```

### Validating input in the same call

Pass validation rules as the second argument. Gemboot validates `request()->all()` before running your callback:

```php
return $this->responseSuccessOrException(function () {
    return User::create(request()->only('name', 'email'));
}, [
    'name'  => 'required',
    'email' => 'required|email',
]);
```

If validation fails, the callback doesn't run and the client gets:

```json
{
    "status": 400,
    "message": "Bad Request",
    "data": {
        "error": {
            "email": ["The email field must be a valid email address."],
            "name": ["The name field is required."]
        }
    }
}
```

A third argument takes custom validation messages, as in Laravel's `Validator::make()`.

Laravel's own validation inside the callback gives the same answer, so you can also write:

```php
return $this->responseSuccessOrException(function () use ($request) {
    $data = $request->validate(['name' => 'required', 'email' => 'required|email']);

    return User::create($data);
});
```

A failed `$request->validate()` or `Validator::validate()` answers 400 with the errors under `data.error`, exactly as above. Before 8.11.1, it became a 500.

### Two variants

- `responseSuccessOrExceptionUsingTransaction($callback, $rules, $messages)` runs the callback inside a database transaction. It commits on success and rolls back if anything throws. Use it when the callback writes to more than one table.
- `responseSuccessOrExceptionRaw($callback, $rules, $messages)` returns whatever the callback returns, without wrapping it, but still turns exceptions into error responses. Use it when the callback already builds its own response, such as a file download.

## Returning errors: throw an exception

Throw a Gemboot exception anywhere inside the callback, even deep inside a service:

```php
use GembootNotFoundException;

return $this->responseSuccessOrException(function () use ($id) {
    $order = Order::find($id);
    if (! $order) {
        throw new GembootNotFoundException('Order not found');
    }
    return $order;
});
```

```json
{ "status": 404, "message": "Not Found", "data": { "error": "Order not found" } }
```

The text you pass goes into `data.error`. The `message` field always stays the standard status text.

To send extra details instead, pass an array as the second argument. It replaces `data`:

```php
throw new GembootForbiddenException('Not your record', ['record_id' => 5]);
```

```json
{ "status": 403, "message": "Forbidden", "data": { "record_id": 5 } }
```

All Gemboot exceptions, with their status codes:

| Exception | Status |
|---|---|
| `GembootBadRequestException` | 400 |
| `GembootValidationFailException` | 400 |
| `GembootUnauthorizedException` | 401 |
| `GembootForbiddenException` | 403 |
| `GembootNotFoundException` | 404 |
| `GembootMethodNotAllowedException` | 405 |
| `GembootConflictException` | 409 |
| `GembootUnprocessableEntityException` | 422 |
| `GembootTooManyRequestsException` | 429 |
| `GembootServerErrorException` | 500 |
| `GembootServiceUnavailableException` | 503 |
| `GembootHttpErrorException` | the status you pass: `new GembootHttpErrorException(418, "I'm a teapot")` |

The aliases above are registered automatically. In code that prefers full class names, they live in `Gemboot\Exceptions\` (for example `Gemboot\Exceptions\NotFoundException`).

## Security headers

Every Gemboot JSON response, including errors, carries two headers:

```text
Cache-Control: no-store
X-Content-Type-Options: nosniff
```

- `Cache-Control: no-store` tells proxies and browsers not to keep a copy. API responses usually contain personal data.
- `X-Content-Type-Options: nosniff` tells browsers to treat the body as JSON and never guess another type.

To turn them off, set `GEMBOOT_SECURITY_HEADERS=false`. To change them, for example to let a public endpoint be cached, publish the config and edit `response.security_headers`:

```php
'security_headers' => [
    'Cache-Control' => 'public, max-age=60',
    'X-Content-Type-Options' => 'nosniff',
],
```

## Laravel authorization

A denial from Laravel's authorization, such as `$this->authorize()`, `Gate::authorize()`, or a policy, becomes a 403 with its message:

```json
{ "status": 403, "message": "Forbidden", "data": { "error": "This action is unauthorized." } }
```

A policy that denies with another status keeps it. For example, `Response::denyAsNotFound()` gives a 404. Before 8.11, these denials became a 500.

## Unexpected errors

Any other exception, such as a database error or a bug, becomes a 500:

```json
{ "status": 500, "message": "Internal Server Error", "data": { "error": "Internal Server Error" } }
```

The client only sees a generic message, because the real one can contain internals such as SQL queries or host names. The real exception goes to Laravel's `report()`, so it shows up in your log and in any error tracker you have set up (Sentry, Nightwatch, ...).

With `APP_DEBUG=true`, the response also includes the real message and a `trace`, which helps during development.

### Error alerts (Telegram, Slack, ...)

Because unexpected errors go through `report()`, Laravel's log channels can forward them anywhere. For Telegram, add a channel with Monolog's built-in handler to `config/logging.php`:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'telegram'],
    ],

    'telegram' => [
        'driver' => 'monolog',
        'handler' => Monolog\Handler\TelegramBotHandler::class,
        'with' => [
            'apiKey' => env('TELEGRAM_BOT_TOKEN'),
            'channel' => env('TELEGRAM_CHAT_ID'),
        ],
        'level' => 'error',
    ],
],
```

Laravel's built-in `slack` driver works the same way for Slack.

Gemboot's own Telegram alerts (`GEMBOOT_TELEGRAM_BOT_TOKEN` and `GEMBOOT_TELEGRAM_CHAT_ID`, and the `TelegramLibrary` class) are **deprecated since 8.8 and will be removed in 9.0**. They still work in 8.x, but log a deprecation notice. To switch, set up the log channel above and remove the two `GEMBOOT_TELEGRAM_*` variables. `php artisan gemboot:doctor` warns while they're set.

## Responding directly

When you don't need the callback, call a helper directly. Each one takes `$data`, then an optional `$message` that replaces the status text:

| Helper | Status | Example |
|---|---|---|
| `responseSuccess($data)` | 200 | `GembootResponse::responseSuccess(['id' => 1])` |
| `responseBadRequest($data)` | 400 | `responseBadRequest(['error' => 'Missing date'])` |
| `responseUnauthorized($data)` | 401 | |
| `responseForbidden($data)` | 403 | |
| `responseNotFound($data)` | 404 | |
| `responseError($data)` | 500 | |
| `responseHttpError($status, $data)` | any | `responseHttpError(409, ['error' => 'Already paid'])` |
| `responseValidationError($errors)` | 400 | takes a Laravel `MessageBag` |

`responseSuccess(['id' => 1])` returns:

```json
{ "status": 200, "message": "OK", "data": { "id": 1 } }
```

## Validating without a callback: `GembootValidator`

`GembootValidator` wraps Laravel's validator and adds a method that throws a `GembootValidationFailException`:

```php
use GembootValidator;

GembootValidator::makeAndThrow($request->all(), [
    'email' => 'required|email',
]);
// Continues only if valid. Otherwise responseSuccessOrException() turns the
// exception into the 400 response shown above.
```

`make()`, `fails()`, and `errors()` work like Laravel's validator, if you'd rather check the result yourself.

## Next

- [Authentication](AUTH.md)
- [Controllers](CONTROLLER.md): the resource controller uses these helpers for you

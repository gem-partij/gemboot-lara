# Request IDs

After this guide, you'll be able to take one failed user action and find every log line it left, in every Gemboot service it passed through.

## The problem

A user clicks "Pay". The frontend calls the orders service, which calls the auth service and the payments service. Something fails, and the user reports "it didn't work at 10:14". Each service has its own log, with hundreds of lines around 10:14.

With request IDs, each of those log lines carries the same ID, for example `9f0c2d4e-6b1a-4c55-9a3e-2f1d8b7c6a10`. Search all logs for it, and you get exactly that one action.

## Turn it on

Add the middleware first in the global stack, in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->prepend(\Gemboot\Middleware\AssignRequestId::class);
})
```

Do this in every Gemboot service. That's all the setup.

## What you get

Every response now carries the ID in a header:

```text
HTTP/1.1 200 OK
X-Request-Id: 9f0c2d4e-6b1a-4c55-9a3e-2f1d8b7c6a10
```

Every log line written during that request carries it too. You don't change your `Log::` calls:

```text
[2026-10-08 10:14:03] production.ERROR: Payment declined {"order_id":42} {"request_id":"9f0c2d4e-6b1a-4c55-9a3e-2f1d8b7c6a10"}
```

The frontend can show the header's value in its error message ("Error code 9f0c2d4e..."). When a user reports it, you search your logs for it.

A browser lets a frontend on another domain read only the response headers the API allows. Add the header to `exposed_headers` in `config/cors.php`:

```php
'exposed_headers' => ['X-Request-Id'],
```

## How the ID travels

The middleware takes the ID from the incoming `X-Request-Id` header. If there is none, it creates a new one (a UUID). Then:

1. It stores the ID in Laravel's `Context` under `request_id`. Laravel adds `Context` values to every log line, and to every queued job dispatched during the request, so the job's log lines carry the same ID.
2. Gemboot sends the ID along with its own calls: to the auth service (`token-validated`, `role:`, `permission:`, `GembootAuth`), to the SSO guard's user service, and to the file handler.
3. It puts the ID in the response header.

When service A calls service B and B also runs the middleware, B reuses A's ID. That is how one ID ends up in the logs of every service.

## Your own calls to other services

Gemboot only adds the header to its own calls. For calls you make with Laravel's `Http` client, add it like this:

```php
use Gemboot\Support\RequestId;

Http::withHeaders(RequestId::headers())->post($paymentsUrl, $payment);
```

`RequestId::headers()` returns `['X-Request-Id' => '...']` during a request with an ID, and an empty array otherwise. You can read the ID itself with `RequestId::current()` or `Context::get('request_id')`.

To add it to every `Http` call of the app at once, put this in `AppServiceProvider::boot()`:

```php
Http::globalRequestMiddleware(function ($request) {
    foreach (RequestId::headers() as $name => $value) {
        $request = $request->withHeader($name, $value);
    }

    return $request;
});
```

## Where the first ID comes from

The first service in the chain usually creates it. If a gateway or proxy sits in front of your services, let it create the ID instead, so the gateway's own logs carry it too. With nginx:

```nginx
proxy_set_header X-Request-Id $request_id;
```

## Settings

| Key | Env variable | Default | Purpose |
|---|---|---|---|
| `request_id.header` | `GEMBOOT_REQUEST_ID_HEADER` | `X-Request-Id` | Header name, for incoming, outgoing, and the response |
| `request_id.accept_incoming` | `GEMBOOT_REQUEST_ID_ACCEPT_INCOMING` | `true` | Reuse the ID a caller sent |

An incoming ID is reused only if it has at most 128 characters, and only letters, digits, and `.` `_` `:` `-`. Anything else gets a new ID instead. That keeps line breaks and other tricks that could forge log lines out of your logs.

Set `GEMBOOT_REQUEST_ID_ACCEPT_INCOMING=false` on a service that browsers or other outside clients call directly, without a gateway in front. Otherwise a client can choose the IDs in your logs, for example to make its requests look like someone else's.

## Next

- [Responses](RESPONSES.md#unexpected-errors): where unexpected errors are logged
- [Configuration](CONFIGURATION.md): all settings

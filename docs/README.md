# Gemboot Lara documentation

These guides explain how to build a Laravel API service with Gemboot, step by step. Each guide starts with a working example and then explains the details.

If you are new, read them in this order:

| # | Guide | You will learn |
|---|---|---|
| 1 | [Installation](INSTALLATION.md) | Install the package, point it at your auth service, and check that it works |
| 2 | [Responses](RESPONSES.md) | The `{ status, message, data }` format, the response helpers, and how exceptions become error responses |
| 3 | [Authentication](AUTH.md) | Protect routes with the auth middleware or the SSO guard, and what your auth service must provide |
| 4 | [Models](MODEL.md) | Turn an Eloquent model into a Gemboot model and search it |
| 5 | [Services](SERVICE.md) | Put your queries and saving logic in a service class |
| 6 | [Controllers](CONTROLLER.md) | Get a full CRUD API from `GembootResourceController` and customize it |
| 7 | [Routes and query parameters](ROUTES.md) | Register routes and use search, sorting, and paging from the client side |
| 8 | [Caching](CACHING.md) | Cache lists and records safely, per user |
| 9 | [Testing your API](TESTING.md) | Test protected routes without a running auth service, with `GembootAuth::fake()` |
| 10 | [Request IDs](REQUEST_IDS.md) | Follow one user action through the logs of every service it passes through |

Reference pages, for when you need one specific fact:

- [Artisan commands](COMMANDS.md): the `gemboot:make-*` generators and all their options
- [Configuration](CONFIGURATION.md): every key in `config/gemboot.php` and its environment variable
- [File handler](FILE_HANDLER.md): uploading files to a separate file service

For what changed between versions, see the [upgrade notes in the main README](../README.md#support-policy) and the [GitHub releases](https://github.com/gem-partij/gemboot-lara/releases).

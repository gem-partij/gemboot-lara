# Gemboot Lara

[![Latest Stable Version](https://poser.pugx.org/gem-partij/gemboot-lara/v/stable)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Total Downloads](https://poser.pugx.org/gem-partij/gemboot-lara/downloads)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Latest Unstable Version](https://poser.pugx.org/gem-partij/gemboot-lara/v/unstable)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![License](https://poser.pugx.org/gem-partij/gemboot-lara/license)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Monthly Downloads](https://poser.pugx.org/gem-partij/gemboot-lara/d/monthly)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Daily Downloads](https://poser.pugx.org/gem-partij/gemboot-lara/d/daily)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![composer.lock](https://poser.pugx.org/gem-partij/gemboot-lara/composerlock)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/5615a2e5-ef3f-4cdf-ac7f-6dee5fe4f811/mini.png)](https://insight.sensiolabs.com/projects/5615a2e5-ef3f-4cdf-ac7f-6dee5fe4f811)

Laravel package for supporting SMVC development method

## What It Does

Before installing gemboot package:

```php
use App\Models\User;

class UserControllerApi extends Controller {

    // method to return all users
    public function index() {
        $status = 200;
        $message = 'Success!';
        $data = [];

        try {
            // add user data to response
            $data = User::all();
        } catch(\Exception $e) {
            // if catch error...

            // log error
            \Log::error($e->getMessage());
            \Log::error($e->getTraceAsString());

            // add error response
            $status = 500;
            $message = "Internal Server Error";
            $data = [
                'error' => $e->getMessage(),
            ];
        }

        // return response json
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

}
```

After installing gemboot package:

```php
use GembootResponse;
use App\Models\User;

class UserControllerApi extends Controller {

    // method to return all users
    public function index() {
        return GembootResponse::responseSuccessOrException(function() {
            return User::all();
        });
    }

}
```

will get the same response:

```json
/* Success Response */
{
    "status": 200,
    "message": "Success!",
    "data": [
        /* all user data... */
    ]
}

/* Error Response */
{
    "status": 500, /* it could be 400 to 500 error status code */
    "message": "Error!",
    "data": [
        /* all error data... */
    ]
}
```

## Documentation, Installation, and Usage Instructions

See the [DOCUMENTATION](https://github.com/gem-partij/gemboot-lara/tree/master/docs) for detailed installation and usage instructions.

## Support Policy

Only the latest version will get new features.

| Package Version  | Laravel Version  | PHP Version |
| ---------------- | ---------------- | ----------- |
| 0.5.x            | < 5.5            |             |
| 1.x              | ^5.5, ^6, ^7     | 7.2 - 8.0   |
| 2.x              | 8                | 7.3 - 8.1   |
| 3.x              | 9                | 8.0 - 8.2   |
| 4.x              | 10               | 8.1 - 8.3   |
| 5.x              | 11               | 8.2 - 8.3   |
| 6.x              | 11               | ^8.2        |
| 7.x              | ^11, ^12         | ^8.2        |
| 8.x *(current)*  | ^12, ^13         | ^8.3        |

### Upgrading from 7.x to 8.x

8.x adds Laravel 13 and PHP 8.5 support. There are no breaking changes for consumer code, only minimum requirement bumps:

- PHP minimum is now **8.3** (was 8.2)
- Laravel 11 is no longer supported. Laravel 11 stopped receiving security fixes in March 2026, and recent Composer versions refuse to install its releases because of unpatched security advisories. Stay on 7.x if you cannot upgrade Laravel yet.
- All internal method signatures use explicit nullable types (`?Type` instead of implicit `Type = null`) to satisfy PHP 8.4+ deprecation rules
- `guzzlehttp/guzzle` minimum is now **7.15.2**. Earlier releases are affected by security advisories, including host-based check bypasses, and gemboot-lara sends auth tokens through Guzzle.
- `laravel-notification-channels/telegram` accepts `^7.0` (Laravel 13 ecosystem)
- PHPUnit `^12.0` is supported in dev dependencies

Behavior changes worth checking:

- `CoreService` caching (enabled with `setObserver()`) now requires a cache store with tag support (redis, memcached, array). On file or database stores, `listAll()` and `findOrFail()` read from the database directly, because cached entries could not be invalidated.
- The Telegram exception notification and the debug trace in error responses are now read through `config()`, so they keep working after `php artisan config:cache`.

If your project is already on PHP 8.3+ and Laravel 12/13, `composer require gem-partij/gemboot-lara:^8.0` should be a drop-in upgrade.

## Installation

Require the `gem-partij/gemboot-lara` package in your `composer.json` and update your dependencies:

```sh
composer require gem-partij/gemboot-lara
```

Optional: The service provider will automatically get registered. Or you may manually add the service provider in your config/app.php file:

```php
'providers' => [
    // ...
    \Gemboot\GembootServiceProvider::class,
];
```

Optional: The aliases will automatically get registered. Or you may manually add the gemboot aliases in your `config/app.php` file:

```php
'aliases' => [
    // ...

    // Exceptions
    'GembootBadRequestException'          => Gemboot\Exceptions\BadRequestException::class,
    'GembootConflictException'            => Gemboot\Exceptions\ConflictException::class,
    'GembootForbiddenException'           => Gemboot\Exceptions\ForbiddenException::class,
    'GembootHttpErrorException'           => Gemboot\Exceptions\HttpErrorException::class,
    'GembootMethodNotAllowedException'    => Gemboot\Exceptions\MethodNotAllowedException::class,
    'GembootNotFoundException'            => Gemboot\Exceptions\NotFoundException::class,
    'GembootServerErrorException'         => Gemboot\Exceptions\ServerErrorException::class,
    'GembootServiceUnavailableException'  => Gemboot\Exceptions\ServiceUnavailableException::class,
    'GembootTooManyRequestsException'     => Gemboot\Exceptions\TooManyRequestsException::class,
    'GembootUnauthorizedException'        => Gemboot\Exceptions\UnauthorizedException::class,
    'GembootUnprocessableEntityException' => Gemboot\Exceptions\UnprocessableEntityException::class,
    'GembootValidationFailException'      => Gemboot\Exceptions\ValidationFailException::class,

    // Facades
    'GembootAuth'                         => Gemboot\Facades\GembootAuthFacade::class,
    'GembootPermission'                   => Gemboot\Facades\GembootPermissionFacade::class,
    'GembootRequest'                      => Gemboot\Facades\GembootRequestFacade::class,
    'GembootResponse'                     => Gemboot\Facades\GembootResponseFacade::class,
    'GembootValidator'                    => Gemboot\Facades\GembootValidatorFacade::class,

    // Controllers
    'GembootController'                   => Gemboot\Controllers\CoreRestController::class,
    'GembootProxyController'              => Gemboot\Controllers\CoreRestProxyController::class,
    'GembootResourceController'           => Gemboot\Controllers\CoreRestResourceController::class,

    // Model
    'GembootModel'                        => Gemboot\Models\CoreModel::class,

    // Service
    'GembootService'                      => Gemboot\Services\CoreService::class,
];
```

## Gemboot Gateway (Additional Package)

> ⚠️ **Deprecated since v8.0.** The `CheckToken` gateway middleware is no longer recommended for new projects and may be removed in a future major release. Use the per-route auth middleware described under [Gemboot Auth](#gemboot-auth-additional-package) instead.

### Middleware (legacy)

To use Gemboot Gateway for all your routes, register the `CheckToken` middleware globally in `bootstrap/app.php`:

```php
// bootstrap/app.php
use Illuminate\Foundation\Configuration\Middleware;

->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Gemboot\Gateway\Middleware\CheckToken::class);
})
```

### Configuration

The defaults are set in `config/gemboot_gw.php`. Publish the config to copy the file to your own config:

```sh
php artisan vendor:publish --tag="gemboot-gateway"
```

## Gemboot Auth (Additional Package)

### Middleware

Register the `TokenValidated`, `HasRole`, and `HasPermissionTo` middleware aliases in `bootstrap/app.php`:

```php
// bootstrap/app.php
use Illuminate\Foundation\Configuration\Middleware;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'token-validated' => \Gemboot\Middleware\TokenValidated::class,
        'role'            => \Gemboot\Middleware\HasRole::class,
        'permission'      => \Gemboot\Middleware\HasPermissionTo::class,
    ]);
})
```

Then apply them to your routes:

```php
Route::middleware(['token-validated', 'role:admin'])->group(function () {
    // ...
});
```

### Configuration

The defaults are set in `config/gemboot_auth.php`. Publish the config to copy the file to your own config:

```sh
php artisan vendor:publish --tag="gemboot-auth"
```

### Routes

add Gemboot AuthLibrary in your routes if you want to use it, example:

```php
use Illuminate\Http\Request;
use Gemboot\Libraries\AuthLibrary;

Route::middleware('api')->prefix('auth')->group(function() {
    Route::post('login', function(Request $request) {
        return (new AuthLibrary)->login($request->npp, $request->password, true);
    });

    Route::get('me', function() {
        return (new AuthLibrary)->me(true);
    });

    Route::get('validate-token', function() {
        return (new AuthLibrary)->validateToken(true);
    });

    Route::get('has-role', function(Request $request) {
        return (new AuthLibrary)->hasRole($request->role_name, true);
    });

    Route::get('has-permission-to', function(Request $request) {
        return (new AuthLibrary)->hasPermissionTo($request->permission_name, true);
    });

    Route::post('logout', function() {
        return (new AuthLibrary)->logout(true);
    });
});
```

## Gemboot File Handler (Additional Package)

### Configuration

The defaults are set in `config/gemboot_file_handler.php`. Publish the config to copy the file to your own config:

```sh
php artisan vendor:publish --tag="gemboot-file-handler"
```

### File Handler Usage

Now you can upload image or document using gemboot file handler.

```php
use Illuminate\Http\Request;
use Gemboot\FileHandler\FileHandler;

class ExampleController extends Controller {

    public function uploadImage(Request $request) {
        $image = $request->file_image;
        $new_filename = "Gambar.jpeg";
        $save_path = "/gambar/2020";

        return (new FileHandler($image))
                ->uploadImage($new_filename, $save_path)
                ->object();
    }

}
```

The **uploadImage**, **uploadDocument** method returns an instance of **Illuminate\Http\Client\Response**, which provides a variety of methods that may be used to inspect the response:

```php
$response->body() : string;
$response->json($key = null) : array|mixed;
$response->object() : object;
$response->collect($key = null) : Illuminate\Support\Collection;
$response->status() : int;
$response->ok() : bool;
$response->successful() : bool;
$response->redirect(): bool;
$response->failed() : bool;
$response->serverError() : bool;
$response->clientError() : bool;
$response->header($header) : string;
$response->headers() : array;
```

more at: https://laravel.com/docs/http-client#making-requests

## Testing

Run the tests with:

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email anggerpputro@gmail.com instead of using the issue tracker.

## Credits

- [Angger Priyardhan Putro](https://github.com/anggerpputro)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

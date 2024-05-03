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

| Package Version | Laravel Version | PHP Version |
| --------------- | --------------- | ----------- |
| 0.5.x           | < 5.5           |             |
| 1.x             | ^5.5, ^6, ^7    | 7.2 - 8.0   |
| 2.x             | 8               | 7.3 - 8.1   |
| 3.x             | 9               | 8.0 - 8.2   |
| 4.x             | 10              | 8.1 - 8.3   |
| 5.x             | 11              | 8.2 - 8.3   |

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

Optional: The aliases will automatically get registered. Or you may manually add the gemboot aliases in your config/app.php file:

```php
'aliases' => [
    // ...
    'GembootBadRequestException' => Gemboot\Exceptions\BadRequestException::class,
    'GembootForbiddenException' => Gemboot\Exceptions\ForbiddenException::class,
    'GembootNotFoundException' => Gemboot\Exceptions\NotFoundException::class,
    'GembootServerErrorException' => Gemboot\Exceptions\ServerErrorException::class,
    'GembootUnauthorizedException' => Gemboot\Exceptions\UnauthorizedException::class,

    'GembootRequest' => Gemboot\Facades\GembootRequestFacade::class,
    'GembootResponse' => Gemboot\Facades\GembootResponseFacade::class,

    'GembootController' => Gemboot\Controllers\CoreRestController::class,
    'GembootProxyController' => Gemboot\Controllers\CoreRestProxyController::class,
    'GembootResourceController' => Gemboot\Controllers\CoreRestResourceController::class,

    'GembootModel' => Gemboot\Models\CoreModel::class,

    'GembootService' => Gemboot\Services\CoreService::class,
];
```

## Gemboot Gateway (Additional Package)

### Middleware

To use Gemboot Gateway for all your routes, add the `CheckToken` middleware in the `$middleware` property of `app/Http/Kernel.php` class:

```php
protected $middleware = [
    // ...
    \Gemboot\Gateway\Middleware\CheckToken::class,
];
```

### Configuration

The defaults are set in `config/gemboot_gw.php`. Publish the config to copy the file to your own config:

```sh
php artisan vendor:publish --tag="gemboot-gateway"
```

## Gemboot Auth (Additional Package)

### Middleware

To use Gemboot Auth middleware for your routes, add the `TokenValidated`, `HasRole`, `HasPermissionTo` middleware in the `$routeMiddleware` property of `app/Http/Kernel.php` class:

```php
protected $routeMiddleware = [
    // ...
    'token-validated' => \Gemboot\Middleware\TokenValidated::class,
    'role' => \Gemboot\Middleware\HasRole::class,
    'permission' => \Gemboot\Middleware\HasPermissionTo::class,
];
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

more at: https://laravel.com/docs/9.x/http-client#making-requests

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

-   [Angger Priyardhan Putro](https://github.com/anggerpputro)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

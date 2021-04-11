# Gemboot Lara

[![Latest Stable Version](https://poser.pugx.org/gem-partij/gemboot-lara/v/stable)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Total Downloads](https://poser.pugx.org/gem-partij/gemboot-lara/downloads)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Latest Unstable Version](https://poser.pugx.org/gem-partij/gemboot-lara/v/unstable)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![License](https://poser.pugx.org/gem-partij/gemboot-lara/license)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Monthly Downloads](https://poser.pugx.org/gem-partij/gemboot-lara/d/monthly)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Daily Downloads](https://poser.pugx.org/gem-partij/gemboot-lara/d/daily)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![composer.lock](https://poser.pugx.org/gem-partij/gemboot-lara/composerlock)](https://packagist.org/packages/gem-partij/gemboot-lara)

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


## Support Policy

Only the latest version will get new features.

| Package Version | Laravel Version |
|-----------------|-----------------|
| 0.5.x           | < 5.5           |
| 1.x             | ^5.5, ^6, ^7    |
| 2.x             | 8               |


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

To use Gemboot Gateway for all your routes, add the `CheckToken` middleware in the `$middleware` property of  `app/Http/Kernel.php` class:

```php
protected $middleware = [
    // ...
    \Gemboot\Gateway\Middleware\CheckToken::class,
];
```

### Configuration

The defaults are set in `config/gemboot_gw.php`. Publish the config to copy the file to your own config:
```sh
php artisan vendor:publish --tag="gemboot"
```


## Testing

Run the tests with:

``` bash
composer test
```


## Security

If you discover any security-related issues, please email anggerpputro@gmail.com instead of using the issue tracker.


## Credits

- [Angger Priyardhan Putro](https://github.com/anggerpputro)
- [All Contributors](../../contributors)


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

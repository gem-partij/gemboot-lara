# Gemboot Installation

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

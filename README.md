# Gemboot Lara

[![Latest Stable Version](https://poser.pugx.org/gem-partij/gemboot-lara/v/stable)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Total Downloads](https://poser.pugx.org/gem-partij/gemboot-lara/downloads)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Latest Unstable Version](https://poser.pugx.org/gem-partij/gemboot-lara/v/unstable)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![License](https://poser.pugx.org/gem-partij/gemboot-lara/license)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Monthly Downloads](https://poser.pugx.org/gem-partij/gemboot-lara/d/monthly)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![Daily Downloads](https://poser.pugx.org/gem-partij/gemboot-lara/d/daily)](https://packagist.org/packages/gem-partij/gemboot-lara)
[![composer.lock](https://poser.pugx.org/gem-partij/gemboot-lara/composerlock)](https://packagist.org/packages/gem-partij/gemboot-lara)

Laravel package for supporting SMVC development method

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

After updating composer, add the ServiceProvider to the providers array in config/app.php
```php
'providers' => [
    // ...
    \Gemboot\GembootServiceProvider::class,
];
```


## Gemboot Gateway

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

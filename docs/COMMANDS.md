# Gemboot Lara Commands

Gemboot menyediakan artisan console generator untuk membuat gemboot class melalui console/terminal, beberapa class yang bisa digenerate:
- [Controller](#controller)
- [Model](#model)
- [Service](#service)


## Controller
untuk melihat opsi yang tersedia bisa ketikkan di console
```sh
php artisan gemboot:make-controller --help
```

#### 1.1. Basic Controller
untuk generate basic gemboot controller silahkan ketikkan di console
```sh
php artisan gemboot:make-controller BasicController
```
- _BasicController_ adalah nama controller yang akan digenerate, bisa diisi bebas sesuai kebutuhan

#### 1.2. Add Model to Controller
untuk generate gemboot controller + connect ke model silahkan ketikkan di console
```sh
php artisan gemboot:make-controller UserController --model=User
```
- _UserController_ adalah nama controller yang akan digenerate, bisa diisi bebas sesuai kebutuhan
- _--model=User_ adalah nama model yang akan digenerate (jika belum ada model user), bisa diisi bebas sesuai kebutuhan, dan akan otomatis di import ke controller yang akan digenerate

#### 1.3. Add Model & Service to Controller
untuk generate gemboot controller + connect ke model dan juga service silahkan ketikkan di console
```sh
php artisan gemboot:make-controller UserController --model=User --service=UserService
```
- _UserController_ adalah nama controller yang akan digenerate, bisa diisi bebas sesuai kebutuhan
- _--model=User_ adalah nama model yang akan digenerate (jika belum ada model user), bisa diisi bebas sesuai kebutuhan, dan akan otomatis di import ke controller yang akan digenerate
- _--service=UserService_ adalah nama service yang akan digenerate (jika belum ada servicenya), bisa diisi bebas sesuai kebutuhan, dan akan otomatis di import ke controller yang akan digenerate

#### 1.4. Resource Controller
untuk generate gemboot resource controller bisa tambahkan flag --resource
```sh
php artisan gemboot:make-controller UserController --resource
```
- _UserController_ adalah nama controller yang akan digenerate, bisa diisi bebas sesuai kebutuhan
- info lebih lanjut mengenai resource controller bisa dibaca di docs laravel: [Laravel Resource Controllers](https://laravel.com/docs/8.x/controllers#resource-controllers)

## Model
untuk melihat opsi yang tersedia bisa ketikkan di console

```sh
php artisan gemboot:make-model --help
```


## Service
untuk melihat opsi yang tersedia bisa ketikkan di console

```sh
php artisan gemboot:make-service --help
```

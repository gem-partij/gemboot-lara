# Gemboot Lara Controllers

Untuk menggunakan Gemboot Controller silahkan install dulu dengan mengikuti panduan di halaman [INSTALLATION](https://github.com/gem-partij/gemboot-lara/tree/master/docs/INSTALLATION.md).
Silahkan tambahkan alias juga di config untuk kemudahan pemanggilan (optional).


## Fungsi Gemboot Controller

Sebelum install gemboot:

```php
namespace App\Http\Controllers;

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

Setelah install gemboot:

```php
namespace App\Http\Controllers;

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

Dengan menggunakan gemboot codingan kita jadi lebih ringkas, (kedua code di atas akan memberikan response yang sama).


## Gemboot Response
Untuk menggunakan gemboot response, ada beberapa cara:

### 1. Menggunakan Facade
untuk menggunakan GembootResponse facade, pastikan di config/app.php di bagian alias sudah ada GembootResponse:
```php
'aliases' => [
    // ...
    'GembootResponse' => Gemboot\Facades\GembootResponseFacade::class,
];
```

jika sudah ada tinggal dipakai saja, contohnya seperti ini:
```php
namespace App\Http\Controllers;

use GembootResponse;
use App\Models\Post;

class PostController extends Controller {

    public function index() {
        $data = Post::paginate(30);
        return GembootResponse::responseSuccess($data);
    }

}
```

kelebihan menggunakan gemboot facade kita tidak perlu mensetting/ merubah codingan apapun di controller kita, hanya tinggal mengimport GembootResponse saja sudah bisa digunakan.

### 2. Menggunakan Gemboot Controller
cara ini juga cukup simple, tinggal extends GembootController.
agar bisa memanggil GembootController secara langsung, pastikan di config/app.php di bagian alias sudah ada GembootController:
```php
'aliases' => [
    // ...
    'GembootController' => Gemboot\Controllers\CoreRestController::class,
    'GembootProxyController' => Gemboot\Controllers\CoreRestProxyController::class,
    'GembootResourceController' => Gemboot\Controllers\CoreRestResourceController::class,
];
```
_(di contoh ini ada 3 controller, karena gemboot controller ada 3 macam, penjelasan lebih lanjut tentang controller ini baca sampai bawah)_

jika sudah di aliaskan di config/app.php, sekarang tinggal kita panggil di controller kita:
```php
namespace App\Http\Controllers;

use GembootController;
use App\Models\Post;
use App\Services\PostService;

class PostController extends GembootController {

    public function __construct(Post $post, PostService $service) {
        parent::__construct($post, $service);
    }

    public function index() {
        return $this->responseSuccessOrException(function() {
            return $this->service->listAll();
        });
    }
}
```
_(di contoh ini kita menggunakan service PostService, lalu di constructor kita memberitahu gemboot bahwa kita akan menggunakan model Post dan service PostService, setelah itu di method index(), kita memanggil GembootResponse dengan $this)_

kelebihan menggunakan cara ini adalah jika kita ingin menggunakan gemboot service. dokumentasi tentang gemboot service bisa dibaca di [GEMBOOT SERVICE](https://github.com/gem-partij/gemboot-lara/tree/master/docs/SERVICE.md)

#### Available Methods
macam-macam method yang bisa digunakan untuk menampilkan response:

| Nama Method | Fungsinya |
|-------------|-----------|
| responseSuccess($data= [], $message= 'Success!') | HTTP Response 200 |
| responseBadRequest($data= [], $message= 'Bad Request!') | HTTP Response 400 |
| responseUnauthorized($data= [], $message= 'Unauthorized!') | HTTP Response 401 |
| responseForbidden($data= [], $message= 'Forbidden!') | HTTP Response 403 |
| responseNotFound($data= [], $message= 'Not Found!') | HTTP Response 404 |
| responseError($data= [], $message= 'Server Error!') | HTTP Response 500 |
| responseException($exception) | HTTP Response 500 |
| responseSuccessOrException(callable $callback) | HTTP Response 200 atau 500 |
| responseSuccessOrExceptionUsingTransaction(callable $callback) | HTTP Response 200 atau 500 |

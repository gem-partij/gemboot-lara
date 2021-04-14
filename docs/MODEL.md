# Gemboot Lara Models

Agar semua model bisa di baca oleh gemboot controller maupun service, maka ada beberapa hal yang harus disesuaikan.

Jika pada umumnya model akan extends Eloquent, maka jika ingin menggunakan gemboot, alih-alih meng-extends Eloquent, model harus meng-extends GembootModel.

Berikut ini contohnya:

1. Tanpa gemboot:
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sembarangan extends Model
{
    use HasFactory;
}
```

2. Pakai gemboot:
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Gemboot\Models\CoreModel as GembootModel;

class Sembarangan extends GembootModel
{
    use HasFactory;
}
```

Jika diperhatikan tidak ada yang berubah, kecuali hanya mengganti extendsnya saja.

Pada dasarnya GembootModel sebenarnya mengextends Eloquent, maka dari itu semua method yang ada di Eloquent juga bisa di pakai walaupun class Sembarangan extends GembootModel.

GembootModel tidak mengubah apapun method yang ada di Eloquent, GembootModel hanya menambahkan beberapa fitur/method yang nantinya bisa digunakan oleh GembootService.

Info lebih lanjut mengenai Eloquent bisa dibaca di website laravel: [Laravel Eloquent](https://laravel.com/docs/8.x/eloquent)

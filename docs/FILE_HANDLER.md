# File handler

`FileHandler` uploads images and documents to a separate file service, using the current user's token. After this page, you'll be able to upload a file from a controller and read the file service's answer.

## Setup

```dotenv
GEMBOOT_FILE_HANDLER_BASE_URL=https://files.example.com
```

## Uploading an image

```php
use Gemboot\FileHandler\FileHandler;
use Illuminate\Http\Request;

public function uploadPhoto(Request $request)
{
    $response = (new FileHandler($request->file('photo')))
        ->uploadImage('profile-7.jpg', '/profiles/2026');

    return $this->responseSuccess($response->json());
}
```

- The first argument is the file name to store, the second the folder on the file service.
- Gemboot sends the file as `photo` to `{base_url}/api/upload/foto`, together with `path` and `filename`.
- The client's bearer token is forwarded, so the file service can check who is uploading.

## Uploading a document

```php
$response = (new FileHandler($request->file('contract')))
    ->uploadDocument('contract-12.pdf', '/contracts/2026');
```

This sends the file as `document` to `{base_url}/api/upload/document`.

## The answer

Both methods return a Laravel HTTP client response (`Illuminate\Http\Client\Response`):

```php
$response->json();          // decoded body
$response->status();        // HTTP status
$response->successful();    // true for 2xx
```

If the file service answers with an error, the method throws Laravel's `RequestException`. Inside `responseSuccessOrException()` that becomes a 500 response.

## Other options

| Method | Use |
|---|---|
| `setFileContent($content)` | Upload content you already have (for example a generated PDF) instead of an uploaded file. The file name argument is then used as the original name. |
| `setToken($token)` | Use this token instead of the one from the current request, for example in a queued job |
| `setRequestUrl($url)` | Post to this full URL instead of the default upload path |
| `setBaseUrl($url)` | Use another file service than the configured one |
| `ping()` | Check that the file service answers (`{base_url}/api/ping`) |

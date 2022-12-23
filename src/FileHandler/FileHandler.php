<?php

namespace Gemboot\FileHandler;

use Gemboot\Traits\GembootRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response as HttpResponse;

class FileHandler
{
    use GembootRequest;

    protected $baseUrl;
    protected $file;

    public function __construct($file)
    {
        $this->baseUrl = app('config')->get('gemboot_file_handler.base_url');
        $this->setFile($file);
    }

    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    public function uploadImage($filename, $path): HttpResponse
    {
        $token = $this->getRequestToken();

        $img = $this->file;
        $photo = fopen($img->getRealPath(), 'r');

        $http = Http::withToken($token)
            ->attach(
                'photo',
                $photo,
                $img->getClientOriginalName()
            )
            ->post($this->baseUrl . "/api/upload/foto", [
                'path' => $path,
                'filename' => $filename,
            ]);

        if ($http->failed()) {
            $http->throw();
        }

        return $http;
    }

    public function uploadDocument($filename, $path): HttpResponse
    {
        $token = $this->getRequestToken();

        $file = $this->file;
        $document = fopen($file->getRealPath(), 'r');

        $http = Http::withToken($token)
            ->attach(
                'document',
                $document,
                $file->getClientOriginalName()
            )
            ->post($this->baseUrl . "/api/upload/document", [
                'path' => $path,
                'filename' => $filename,
            ]);

        if ($http->failed()) {
            $http->throw();
        }

        return $http;
    }
}

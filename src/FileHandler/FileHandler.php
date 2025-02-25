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
    protected $token;
    protected $request;
    protected $fileContent;
    protected $requestUrl;

    public function __construct($file = null)
    {
        $this->baseUrl = app('config')->get('gemboot.file_handler.base_url');
        $this->setFile($file);
    }

    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    public function setFileContent($fileContent)
    {
        $this->fileContent = $fileContent;
        return $this;
    }

    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    public function setRequestUrl($requestUrl)
    {
        $this->requestUrl = $requestUrl;
        return $this;
    }

    public function ping()
    {
        $token = $this->token ? $this->token : $this->getRequestToken($this->request, true);

        $http = Http::withToken($token)
            ->get($this->baseUrl . "/api/ping");

        if ($http->failed()) {
            $http->throw();
        }

        return $http;
    }

    public function uploadImage($filename, $path): HttpResponse
    {
        $token = $this->token ? $this->token : $this->getRequestToken($this->request, true);

        if (empty($this->file) && empty($this->fileContent)) {
            throw new \Exception("File is required!");
        }

        $originalFileName = null;
        $photoContent = null;
        if (!empty($this->file)) {
            $img = $this->file;
            $originalFileName = $img->getClientOriginalName();
            $photoContent = fopen($img->getRealPath(), 'r');
        } elseif (!empty($this->fileContent)) {
            $originalFileName = $filename;
            $photoContent = $this->fileContent;
        }

        if (empty($photoContent)) {
            throw new \Exception("Cannot read file!");
        }

        $http = Http::withToken($token)
            ->attach(
                'photo',
                $photoContent,
                $originalFileName
            )
            ->post($this->requestUrl ?: $this->baseUrl . "/api/upload/foto", [
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
        $token = $this->token ? $this->token : $this->getRequestToken($this->request, true);

        if (empty($this->file) && empty($this->fileContent)) {
            throw new \Exception("File is required!");
        }

        $originalFileName = null;
        $documentContent = null;
        if (!empty($this->file)) {
            $file = $this->file;
            $originalFileName = $file->getClientOriginalName();
            $documentContent = fopen($file->getRealPath(), 'r');
        } elseif (!empty($this->fileContent)) {
            $originalFileName = $filename;
            $documentContent = $this->fileContent;
        }

        if (empty($documentContent)) {
            throw new \Exception("Cannot read file!");
        }

        $http = Http::withToken($token)
            ->attach(
                'document',
                $documentContent,
                $originalFileName
            )
            ->post($this->requestUrl ?: $this->baseUrl . "/api/upload/document", [
                'path' => $path,
                'filename' => $filename,
            ]);

        if ($http->failed()) {
            $http->throw();
        }

        return $http;
    }
}

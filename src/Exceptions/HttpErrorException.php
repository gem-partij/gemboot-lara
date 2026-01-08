<?php

namespace Gemboot\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;
use Throwable;

class HttpErrorException extends SymfonyHttpException
{
    /**
     * Data tambahan untuk response error (misal: field validasi error)
     * @var array
     */
    protected array $data = [];

    /**
     * @param int $statusCode HTTP Status Code
     * @param string $message Error message
     * @param array $data Error payload data
     * @param Throwable|null $previous Previous exception
     * @param array $headers HTTP Headers
     * @param int $code Internal Exception code
     */
    public function __construct(
        int $statusCode,
        string $message = 'Http Error',
        array $data = [],
        Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        $this->data = $data;
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    public function getData(): array
    {
        return $this->data;
    }
}
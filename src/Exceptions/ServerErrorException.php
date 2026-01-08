<?php

namespace Gemboot\Exceptions;

use Throwable;

class ServerErrorException extends HttpErrorException
{
    public function __construct(string $message = 'Internal Server Error', array $data = [], Throwable $previous = null)
    {
        parent::__construct(500, $message, $data, $previous);
    }
}
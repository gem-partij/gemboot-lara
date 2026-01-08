<?php

namespace Gemboot\Exceptions;

use Throwable;

class TooManyRequestsException extends HttpErrorException
{
    public function __construct(string $message = 'Too Many Requests', array $data = [], Throwable $previous = null)
    {
        parent::__construct(429, $message, $data, $previous);
    }
}
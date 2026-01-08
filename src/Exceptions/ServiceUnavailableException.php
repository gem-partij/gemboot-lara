<?php

namespace Gemboot\Exceptions;

use Throwable;

class ServiceUnavailableException extends HttpErrorException
{
    public function __construct(string $message = 'Service Unavailable', array $data = [], Throwable $previous = null)
    {
        parent::__construct(503, $message, $data, $previous);
    }
}
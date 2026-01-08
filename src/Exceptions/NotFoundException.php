<?php

namespace Gemboot\Exceptions;

use Throwable;

class NotFoundException extends HttpErrorException
{
    public function __construct(string $message = 'Not Found', array $data = [], Throwable $previous = null)
    {
        parent::__construct(404, $message, $data, $previous);
    }
}
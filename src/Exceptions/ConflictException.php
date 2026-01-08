<?php

namespace Gemboot\Exceptions;

use Throwable;

class ConflictException extends HttpErrorException
{
    public function __construct(string $message = 'Conflict', array $data = [], Throwable $previous = null)
    {
        parent::__construct(409, $message, $data, $previous);
    }
}
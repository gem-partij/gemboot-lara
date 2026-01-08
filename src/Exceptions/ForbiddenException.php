<?php

namespace Gemboot\Exceptions;

use Throwable;

class ForbiddenException extends HttpErrorException
{
    public function __construct(string $message = 'Forbidden', array $data = [], Throwable $previous = null)
    {
        parent::__construct(403, $message, $data, $previous);
    }
}
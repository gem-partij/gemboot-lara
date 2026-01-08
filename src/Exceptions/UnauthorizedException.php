<?php

namespace Gemboot\Exceptions;

use Throwable;

class UnauthorizedException extends HttpErrorException
{
    public function __construct(string $message = 'Unauthorized', array $data = [], Throwable $previous = null)
    {
        parent::__construct(401, $message, $data, $previous);
    }
}
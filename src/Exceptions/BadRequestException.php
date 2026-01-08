<?php

namespace Gemboot\Exceptions;

use Throwable;

class BadRequestException extends HttpErrorException
{
    public function __construct(string $message = 'Bad Request', array $data = [], Throwable $previous = null)
    {
        parent::__construct(400, $message, $data, $previous);
    }
}
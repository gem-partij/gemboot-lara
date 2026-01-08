<?php

namespace Gemboot\Exceptions;

use Throwable;

class UnprocessableEntityException extends HttpErrorException
{
    public function __construct(string $message = 'Unprocessable Entity', array $data = [], Throwable $previous = null)
    {
        parent::__construct(422, $message, $data, $previous);
    }
}
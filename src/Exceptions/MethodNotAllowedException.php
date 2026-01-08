<?php

namespace Gemboot\Exceptions;

use Throwable;

class MethodNotAllowedException extends HttpErrorException
{
    public function __construct(string $message = 'Method Not Allowed', array $data = [], Throwable $previous = null)
    {
        parent::__construct(405, $message, $data, $previous);
    }
}
<?php

namespace Gemboot\Exceptions;

class ValidationFailException extends \Exception
{
    protected $message = "Bad Request";
    protected $code = 400;
}

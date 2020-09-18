<?php
namespace Gemboot\Exceptions;

class BadRequestException extends \Exception {

    protected $message = "Bad Request";
    protected $code = 400;

}

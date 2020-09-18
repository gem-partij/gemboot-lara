<?php
namespace Gemboot\Exceptions;

class UnauthorizedException extends \Exception {

    protected $message = "Unauthorized";
    protected $code = 401;

}

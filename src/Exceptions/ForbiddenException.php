<?php
namespace Gemboot\Exceptions;

class ForbiddenException extends \Exception {

    protected $message = "Forbidden";
    protected $code = 403;

}

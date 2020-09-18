<?php
namespace Gemboot\Exceptions;

class NotFoundException extends \Exception {

    protected $message = "Not Found";
    protected $code = 404;

}

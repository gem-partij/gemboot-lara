<?php
namespace Gemboot\Exceptions;

class HttpErrorException extends \Exception {

    protected $message = "Http Error";
    protected $code = 500;

}

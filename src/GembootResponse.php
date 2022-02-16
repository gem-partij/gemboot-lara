<?php
namespace Gemboot;

use Gemboot\Traits\JSONResponses;
use Illuminate\Http\Response as LaravelResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class GembootResponse extends LaravelResponse {

    use JSONResponses;

}

<?php
namespace Gemboot\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class CoreController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;    
}

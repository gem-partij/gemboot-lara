<?php

namespace Gemboot\Tests\Controllers;

use Gemboot\Controllers\CoreRestController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;

class TestAuthorizeController extends CoreRestController
{
    public function report()
    {
        return $this->responseSuccessOrException(function () {
            $this->authorize('report.read');

            return ['report' => 'ok'];
        });
    }

    public function notYours()
    {
        return $this->responseSuccessOrException(function () {
            throw new AuthorizationException('Not yours');
        });
    }

    public function hidden()
    {
        return $this->responseSuccessOrException(function () {
            // Policies can hide a record's existence with a 404.
            Response::denyAsNotFound()->authorize();
        });
    }
}

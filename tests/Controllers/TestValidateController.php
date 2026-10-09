<?php

namespace Gemboot\Tests\Controllers;

use Gemboot\Controllers\CoreRestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TestValidateController extends CoreRestController
{
    public function requestValidate(Request $request)
    {
        return $this->responseSuccessOrException(function () use ($request) {
            return $request->validate(['name' => 'required', 'email' => 'required|email']);
        });
    }

    public function validatorValidate(Request $request)
    {
        return $this->responseSuccessOrException(function () use ($request) {
            return Validator::validate($request->all(), ['name' => 'required']);
        });
    }

    public function gembootRules()
    {
        // Gemboot's own validation, for comparison: the answers must match.
        return $this->responseSuccessOrException(fn () => request()->only('name', 'email'), [
            'name' => 'required',
            'email' => 'required|email',
        ]);
    }
}

<?php

namespace Gemboot\Tests\Controllers;

use Gemboot\Controllers\CoreController as GembootController;
use Illuminate\Http\Request;
use Gemboot\Libraries\AuthLibrary;

class TestAuthLibraryController extends GembootController
{

    public function login(Request $request) {
        return (new AuthLibrary)->login($request->npp, $request->password, true, $request);
    }

    public function me() {
        return (new AuthLibrary)->me(true);
    }

    public function validateToken() {
        return (new AuthLibrary)->validateToken(true);
    }

    public function hasRole(Request $request) {
        return (new AuthLibrary)->hasRole($request->role_name, true);
    }

    public function hasPermissionTo(Request $request) {
        return (new AuthLibrary)->hasPermissionTo($request->permission_name, true);
    }

    public function logout() {
        return (new AuthLibrary)->logout(true);
    }

}

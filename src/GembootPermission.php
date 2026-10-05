<?php

namespace Gemboot;

use Gemboot\Libraries\AuthLibrary;
use Gemboot\Exceptions\ForbiddenException;

class GembootPermission
{

    public function hasRole($role_name)
    {
        if (!is_array($role_name)) {
            $role_name = [$role_name];
        }

        $has_role_response = (new AuthLibrary)->hasRole(implode("|", $role_name));

        // AuthLibrary returns the decoded body as an array, or false when the call fails.
        return (bool) data_get($has_role_response, 'has_role', false);
    }

    public function hasPermissionTo($permission_name)
    {
        $is_aslinya_array = true;
        if (!is_array($permission_name)) {
            $permission_name = [$permission_name];
            $is_aslinya_array = false;
        }

        $has_permission_to_response = (new AuthLibrary)->hasPermissionTo(implode("|", $permission_name));

        return (bool) data_get(
            $has_permission_to_response,
            $is_aslinya_array ? 'has_any_permission' : 'has_permission_to',
            false
        );
    }

    public function requirePermission($permission_name, $throw_exception = true)
    {
        if ($this->hasPermissionTo($permission_name)) {
            return true;
        }

        if ($throw_exception) {
            throw new ForbiddenException("permission required to access this endpoint");
        }

        return false;
    }
}

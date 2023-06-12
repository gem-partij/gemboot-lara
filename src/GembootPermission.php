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

        return $has_role_response->has_role;
    }

    public function hasPermissionTo($permission_name)
    {
        $is_aslinya_array = true;
        if (!is_array($permission_name)) {
            $permission_name = [$permission_name];
            $is_aslinya_array = false;
        }

        $has_permission_to_response = (new AuthLibrary)->hasPermissionTo(implode("|", $permission_name));

        return $is_aslinya_array
            ? $has_permission_to_response->has_any_permission
            : $has_permission_to_response->has_permission_to;
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

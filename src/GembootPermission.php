<?php

namespace Gemboot;

use Gemboot\Libraries\AuthLibrary;

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
        if (!is_array($permission_name)) {
            $permission_name = [$permission_name];
        }

        $has_permission_to_response = (new AuthLibrary)->hasPermissionTo(implode("|", $permission_name));

        return $has_permission_to_response->has_permission_to;
    }
}

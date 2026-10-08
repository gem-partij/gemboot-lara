<?php

namespace Gemboot\Auth;

use Illuminate\Support\Facades\Gate;

/**
 * Answers Laravel ability checks with the gemboot user's permissions, so
 * $this->authorize('report.read'), @can('report.read'), ->can('report.read')
 * on routes, and Gate::allows('report.read') ask the auth service.
 *
 * Off unless gemboot.authorization.permissions_as_abilities is true. Only
 * abilities without arguments that the app hasn't defined with Gate::define()
 * are answered, so policies and the app's own gates keep working. It only
 * grants: a missing permission leaves the decision to Laravel, which denies
 * an undefined ability.
 */
final class PermissionGate
{
    /**
     * Gate::before() callback.
     *
     * @internal
     */
    public static function before($user, string $ability, array $arguments): ?bool
    {
        if (!config('gemboot.authorization.permissions_as_abilities', false)) {
            return null;
        }

        // Policies receive a model or class; app-defined gates decide themselves.
        if (!$user instanceof GembootUser || $arguments !== [] || Gate::has($ability)) {
            return null;
        }

        return $user->hasPermissionTo($ability) ? true : null;
    }
}

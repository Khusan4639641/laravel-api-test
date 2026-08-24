<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRolePermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $role = $request->user()?->role;
        $apiPermissions = (array) config('role_permissions.api_permissions', []);
        $allowedRoles = $apiPermissions[$permission] ?? [];

        if (! is_array($allowedRoles) || ! in_array($role, $allowedRoles, true)) {
            abort(403, 'Role permission denied.');
        }

        return $next($request);
    }
}

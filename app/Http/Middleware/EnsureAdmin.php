<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowedRoles = $roles === [] ? ['super_admin'] : $roles;

        if (! in_array($request->user()?->role, $allowedRoles, true)) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}

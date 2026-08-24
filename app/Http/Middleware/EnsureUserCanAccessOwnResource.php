<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessOwnResource
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $parameter): Response
    {
        $user = $request->user();
        $resource = $request->route($parameter);

        if (! $user || ! $this->ownsResource($user, $resource)) {
            abort(403, 'You cannot access this resource.');
        }

        return $next($request);
    }

    private function ownsResource(User $user, mixed $resource): bool
    {
        if ($resource instanceof User) {
            return $resource->is($user);
        }

        if ($resource instanceof Model && isset($resource->user_id)) {
            return (int) $resource->user_id === (int) $user->id;
        }

        return false;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $role = $request->user()?->role ?: 'user';
        $permissions = config("role_permissions.roles.{$role}");

        if (! is_array($permissions)) {
            $role = 'user';
            $permissions = config('role_permissions.roles.user');
        }

        return response()->json([
            'role' => $role,
            'label' => $permissions['label'] ?? 'Partner',
            'redirect_after_login' => $permissions['redirect_after_login'] ?? '/dashboard',
            'allowed_routes' => $permissions['allowed_routes'] ?? [],
            'menu' => $permissions['menu'] ?? [],
        ]);
    }
}

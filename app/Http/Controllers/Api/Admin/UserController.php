<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
            ->withCount('referrals')
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($users, UserResource::class, 'users', $request);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => UserResource::make(
                $user->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
                    ->loadCount('referrals')
            ),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Notifications\UserRegisteredNotification;
use App\Services\BinaryTreeService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly BinaryTreeService $binaryTreeService,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'login' => $validated['login'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'sponsor_id' => $validated['sponsor_id'] ?? null,
                'current_package_id' => $validated['package_id'] ?? null,
                'status' => 'active',
            ]);

            $user->profile()->create();
            $this->walletService->createUserWallets($user);

            if (isset($validated['sponsor_id'], $validated['branch'])) {
                $sponsor = User::query()->findOrFail($validated['sponsor_id']);
                $this->binaryTreeService->placeUser($user, $sponsor, $validated['branch']);
            }

            $user->notify(new UserRegisteredNotification());

            return $user->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode']);
        });

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $identifier = $validated['login'] ?? $validated['email'];
        $field = isset($validated['login']) ? 'login' : 'email';

        $user = User::query()->where($field, $identifier)->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                $field => __('auth.failed'),
            ]);
        }

        return response()->json([
            'user' => $user->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode']),
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()?->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode']),
        ]);
    }
}

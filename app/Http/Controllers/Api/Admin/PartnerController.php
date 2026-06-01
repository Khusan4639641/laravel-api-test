<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartnerRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\BinaryTreeService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PartnerController extends Controller
{
    public function __construct(
        private readonly BinaryTreeService $binaryTreeService,
        private readonly WalletService $walletService,
    ) {
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $plainPassword = $validated['password'];
        $sponsor = ! empty($validated['sponsor_id'])
            ? User::query()->findOrFail((int) $validated['sponsor_id'])
            : null;

        $user = DB::transaction(function () use ($validated, $plainPassword, $sponsor): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'login' => $validated['login'],
                'email' => $validated['email'],
                'password' => Hash::make($plainPassword),
                'sponsor_id' => $sponsor?->id,
                'current_package_id' => null,
                'status' => 'user',
                'role' => $validated['role'] ?? User::ROLE_USER,
            ]);

            $nameParts = explode(' ', trim($validated['name']), 2);
            $user->profile()->create([
                'first_name' => $nameParts[0] ?? null,
                'last_name' => $nameParts[1] ?? null,
                'phone' => $validated['phone'] ?? null,
                'country' => 'Казахстан',
            ]);

            $this->walletService->createUserWallets($user);

            if ($sponsor) {
                $this->binaryTreeService->placeUser($user, $sponsor, $validated['branch'] ?? null);
            }

            return $user->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
                ->loadCount('referrals');
        });

        return response()->json([
            'user' => UserResource::make($user),
            'credentials' => [
                'login' => $user->login,
                'email' => $user->email,
                'password' => $plainPassword,
                'login_url' => 'https://safilife.kz/login',
            ],
        ], 201);
    }
}

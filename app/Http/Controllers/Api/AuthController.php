<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Package;
use App\Models\User;
use App\Notifications\UserRegisteredNotification;
use App\Services\BinaryTreeService;
use App\Services\PackageService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly BinaryTreeService $binaryTreeService,
        private readonly PackageService $packageService,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sponsor = $this->resolveSponsor($validated);
        $package = $this->resolvePackage($validated);

        if (! empty($validated['referral_code']) && ! $sponsor) {
            throw ValidationException::withMessages([
                'referral_code' => ['Некорректная реферальная ссылка'],
            ]);
        }

        if ($sponsor && empty($validated['branch'])) {
            throw ValidationException::withMessages([
                'branch' => ['Некорректная реферальная ссылка'],
            ]);
        }

        $user = DB::transaction(function () use ($validated, $sponsor, $package): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'login' => $validated['login'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'sponsor_id' => $sponsor?->id,
                'current_package_id' => null,
                'status' => 'user',
            ]);

            $user->profile()->create();
            $this->walletService->createUserWallets($user);

            if ($sponsor && isset($validated['branch'])) {
                $this->binaryTreeService->placeUser($user, $sponsor, $validated['branch']);
            }

            if ($package) {
                $user = $this->packageService->upgradePackage($user->refresh(), $package);
            }

            $user->notify(new UserRegisteredNotification());

            return $user->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])->loadCount('referrals');
        });

        return response()->json([
            'user' => UserResource::make($user),
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
            'user' => UserResource::make($user->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])->loadCount('referrals')),
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
            'user' => UserResource::make($request->user()?->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])->loadCount('referrals')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveSponsor(array $validated): ?User
    {
        if (! empty($validated['sponsor_id'])) {
            return User::query()->find((int) $validated['sponsor_id']);
        }

        $referralCode = trim((string) ($validated['referral_code'] ?? ''));

        if ($referralCode === '') {
            return null;
        }

        $normalizedCode = strtolower($referralCode);
        $optionalCodeColumns = array_filter(
            ['referral_code', 'partner_id', 'code'],
            fn (string $column): bool => Schema::hasColumn('users', $column),
        );

        return User::query()
            ->where(function ($query) use ($referralCode, $normalizedCode, $optionalCodeColumns): void {
                $query->whereRaw('LOWER(login) = ?', [$normalizedCode]);

                if (ctype_digit($referralCode)) {
                    $query->orWhere('id', (int) $referralCode);
                }

                foreach ($optionalCodeColumns as $column) {
                    $query->orWhereRaw("LOWER({$column}) = ?", [$normalizedCode]);
                }
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolvePackage(array $validated): ?Package
    {
        if (empty($validated['package_id'])) {
            return null;
        }

        $package = Package::query()->find((int) $validated['package_id']);

        if (! $package || ! $package->is_active || $package->status !== 'active' || ! in_array($package->code, Package::PUBLIC_CODES, true)) {
            throw ValidationException::withMessages([
                'package_id' => ['Selected package is inactive.'],
            ]);
        }

        return $package;
    }
}

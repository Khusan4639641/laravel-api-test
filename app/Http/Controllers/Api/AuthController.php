<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\PartnerRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly PartnerRegistrationService $partnerRegistrationService,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sponsor = $this->partnerRegistrationService->resolveSponsorByReferralCode(
            $validated['referral_code'] ?? null,
            isset($validated['sponsor_id']) ? (int) $validated['sponsor_id'] : null,
        );
        $hasReferralCode = trim((string) ($validated['referral_code'] ?? '')) !== '';
        $hasReferralInput = $hasReferralCode || ! empty($validated['sponsor_id']);

        if ($hasReferralInput && ! $sponsor) {
            $sponsorField = $hasReferralCode ? 'referral_code' : 'sponsor_id';

            throw ValidationException::withMessages([
                $sponsorField => ['Пригласитель не найден или недоступен'],
            ]);
        }

        if ($sponsor && ! $sponsor->canInvitePartners()) {
            $sponsorField = $hasReferralCode ? 'referral_code' : 'sponsor_id';

            throw ValidationException::withMessages([
                $sponsorField => ['У пригласителя нет активного пакета. Регистрация по этой ссылке недоступна.'],
            ]);
        }

        if (! config('safi.public_registration_enabled', false) && ! $sponsor) {
            return response()->json([
                'message' => __('api.registration_disabled'),
            ], 403);
        }

        if ($sponsor && empty($validated['branch'])) {
            throw ValidationException::withMessages([
                'branch' => ['Некорректная реферальная ссылка'],
            ]);
        }

        $user = $this->partnerRegistrationService->register(
            data: $validated,
            payReferralBonus: true,
            source: $sponsor ? 'public_referral_registration' : 'public_registration',
            notifyRegisteredUser: true,
        );

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $identifier = trim((string) ($validated['login'] ?? $validated['email']));
        $field = isset($validated['login']) ? 'login' : 'email';

        $user = User::query()
            ->when($field === 'email', fn ($query) => $query->where('email', $identifier))
            ->when($field === 'login', function ($query) use ($identifier): void {
                $query->where(function ($query) use ($identifier): void {
                    $query->where('login', $identifier)
                        ->orWhereHas('profile', fn ($profileQuery) => $profileQuery->where('phone', $identifier));
                });
            })
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                $field => __('auth.failed'),
            ]);
        }

        if (in_array($user->account_status, ['blocked', 'inactive', 'deleted', 'archived'], true)) {
            throw ValidationException::withMessages([
                $field => ['Аккаунт заблокирован'],
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
            'message' => __('api.logged_out'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => UserResource::make($request->user()?->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])->loadCount('referrals')),
        ]);
    }
}

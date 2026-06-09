<?php

namespace App\Services;

use App\Models\Package;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PartnerRegistrationService
{
    public function __construct(
        private readonly BinaryTreeService $binaryTreeService,
        private readonly BonusService $bonusService,
        private readonly PvService $pvService,
        private readonly ReferralBonusBaseResolver $referralBonusBaseResolver,
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(
        array $data,
        ?User $actor = null,
        bool $payReferralBonus = true,
        string $source = 'public_registration',
        bool $notifyRegisteredUser = false,
    ): User {
        return DB::transaction(function () use ($data, $actor, $payReferralBonus, $source, $notifyRegisteredUser): User {
            $referralCode = $data['referral_code'] ?? $data['ref'] ?? $data['sponsor_code'] ?? null;
            $sponsor = $this->resolveSponsorByReferralCode(
                $referralCode,
                isset($data['sponsor_id']) ? (int) $data['sponsor_id'] : null,
            );
            $package = $this->resolveInitialPackage($data['package_id'] ?? null);

            if ($this->hasSponsorInput($referralCode, $data['sponsor_id'] ?? null) && ! $sponsor) {
                throw ValidationException::withMessages([
                    'referral_code' => ['Пригласитель не найден или недоступен'],
                ]);
            }

            $user = $this->createUser($data, $sponsor, (string) $data['password']);
            $this->walletService->createUserWallets($user);
            $this->placeInBinaryTree($user, $sponsor, $data['branch'] ?? null);
            $this->assignInitialPackage($user, $package, $payReferralBonus, $source, $actor);

            if ($notifyRegisteredUser) {
                $user->notify(new UserRegisteredNotification());
            }

            return $this->loadPartner($user->refresh());
        });
    }

    public function resolveSponsorByReferralCode(mixed $referralCode = null, ?int $sponsorId = null): ?User
    {
        if ($sponsorId) {
            return User::query()
                ->eligibleSponsor()
                ->find($sponsorId);
        }

        $referralCode = trim((string) ($referralCode ?? ''));

        if ($referralCode === '') {
            return null;
        }

        $normalizedCode = strtolower($referralCode);
        $optionalCodeColumns = array_filter(
            ['referral_code', 'partner_id', 'code'],
            fn (string $column): bool => Schema::hasColumn('users', $column),
        );

        return User::query()
            ->eligibleSponsor()
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

    private function hasSponsorInput(mixed $referralCode, mixed $sponsorId): bool
    {
        return trim((string) ($referralCode ?? '')) !== ''
            || (is_numeric($sponsorId) && (int) $sponsorId > 0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createUser(array $data, ?User $sponsor, string $plainPassword): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'login' => $data['login'],
            'email' => $data['email'],
            'password' => Hash::make($plainPassword),
            'sponsor_id' => $sponsor?->id,
            'current_package_id' => null,
            'status' => 'user',
            'account_status' => 'active',
            'role' => $data['role'] ?? User::ROLE_USER,
        ]);

        $nameParts = explode(' ', trim((string) $data['name']), 2);
        $user->profile()->create([
            'first_name' => $nameParts[0] ?? null,
            'last_name' => $nameParts[1] ?? null,
            'phone' => $data['phone'] ?? null,
            'country' => $data['country'] ?? 'Казахстан',
        ]);

        return $user;
    }

    public function placeInBinaryTree(User $user, ?User $sponsor, mixed $branch = null): void
    {
        if (! $sponsor) {
            return;
        }

        $this->binaryTreeService->placeUser($user, $sponsor, is_string($branch) ? $branch : null);
    }

    public function assignInitialPackage(
        User $user,
        ?Package $package,
        bool $payReferralBonus,
        string $source,
        ?User $actor = null,
    ): void {
        if (! $package) {
            return;
        }

        $user->forceFill([
            'current_package_id' => $package->id,
        ])->save();

        $activityPv = $package->activityPv();
        $turnoverPv = $package->turnoverPv();

        $this->pvService->addUserPv($user, $activityPv);
        $this->accrueTurnoverToUplines($user, $package, $turnoverPv, $source);
        $this->createPackageTransaction($user, $package, $source, $actor);
        $this->createReferralBonusIfNeeded($user->refresh(), $package, $payReferralBonus, $source);
    }

    public function accrueTurnoverToUplines(User $user, Package $package, string $turnoverPv, string $source): void
    {
        $this->pvService->accrueTurnoverToUplines(
            $user,
            $turnoverPv,
            $this->packageTurnoverSource($package),
            [
                'package_id' => $package->id,
                'package_code' => $package->code,
                'activity_pv' => $package->activityPv(),
                'turnover_pv' => $turnoverPv,
                'registration_source' => $source,
            ],
        );
    }

    public function createPackageTransaction(User $user, Package $package, string $source, ?User $actor = null): ?WalletTransaction
    {
        $amount = (string) $package->price;

        if (bccomp($amount, '0', 2) <= 0) {
            return null;
        }

        $this->walletService->createUserWallets($user);

        $wallet = $user->wallets()
            ->where('type', 'main')
            ->lockForUpdate()
            ->firstOrFail();

        $transactionType = str_starts_with($source, 'admin') || str_starts_with($source, 'bulk')
            ? 'package_assignment'
            : 'package_activation';

        $metadata = [
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_price' => (string) $package->price,
            'transaction_amount' => $amount,
            'activity_pv' => $package->activityPv(),
            'turnover_pv' => $package->turnoverPv(),
            'affects_balance' => false,
            'source' => $source,
        ];

        if ($actor) {
            $metadata['actor_id'] = $actor->id;
            $metadata['actor_role'] = $actor->role;
        }

        return $this->walletService->recordNonBalanceOperation(
            $wallet,
            $amount,
            $transactionType,
            $package,
            $metadata,
            $transactionType === 'package_assignment'
                ? "Super Admin назначил пакет {$package->code}"
                : "Покупка пакета {$package->code}: операция ".$this->formatMoney($amount).' ₸',
        );
    }

    public function createReferralBonusIfNeeded(User $user, Package $package, bool $payReferralBonus, string $source): void
    {
        if (! $payReferralBonus || ! $user->sponsor_id || ! in_array($package->code, Package::STARTER_CODES, true)) {
            return;
        }

        $sponsor = User::query()
            ->eligibleSponsor()
            ->find($user->sponsor_id);

        if (! $sponsor) {
            return;
        }

        $eligibleReferralAmount = $this->referralBonusBaseResolver->resolveForPackageActivation($package, [
            'buyer_id' => $user->id,
        ]);

        if (bccomp($eligibleReferralAmount, '0', 2) <= 0) {
            return;
        }

        $this->bonusService->accrueReferralBonus(
            $sponsor,
            $user,
            $eligibleReferralAmount,
            [
                'source' => $source,
                'base_resolver' => ReferralBonusBaseResolver::class,
                'package_id' => $package->id,
                'package_code' => $package->code,
            ],
            "{$source}:referral_bonus:{$user->id}:{$package->id}",
        );
    }

    private function resolveInitialPackage(mixed $packageId): ?Package
    {
        if ($packageId === null || $packageId === '') {
            return null;
        }

        $package = Package::query()->find((int) $packageId);

        if (! $package || ! $package->is_active || $package->status !== 'active' || ! in_array($package->code, Package::STARTER_CODES, true)) {
            throw ValidationException::withMessages([
                'package_id' => ['Selected package is not available for first registration.'],
            ]);
        }

        return $package;
    }

    private function packageTurnoverSource(Package $package): string
    {
        return match ($package->code) {
            'START' => 'package_start',
            'VIP' => 'package_vip',
            default => 'package_activation',
        };
    }

    private function loadPartner(User $user): User
    {
        return $user->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
            ->loadCount(['referrals', 'invitedUsers as invited_count']);
    }

    private function formatMoney(string $amount): string
    {
        return number_format((float) $amount, 0, '.', ' ');
    }
}

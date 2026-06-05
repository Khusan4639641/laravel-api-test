<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\StatusBonusDefinition;
use App\Models\User;
use App\Models\UserStatusBonus;
use App\Notifications\BonusAccruedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatusBonusService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * @return Collection<int, UserStatusBonus>
     */
    public function awardEligible(User $user): Collection
    {
        return $this->checkMissedStatusBonuses($user);
    }

    /**
     * @return Collection<int, UserStatusBonus>
     */
    public function checkMissedStatusBonuses(User $user): Collection
    {
        return DB::transaction(function () use ($user): Collection {
            $user = User::query()
                ->with('currentPackage')
                ->lockForUpdate()
                ->findOrFail($user->id);
            $created = collect();

            if (! $this->hasElitePackage($user)) {
                return $created;
            }

            $weakLegPv = $this->weakLegPv($user);

            $definitions = StatusBonusDefinition::query()
                ->where('is_active', true)
                ->where('threshold_pv', '<=', $weakLegPv)
                ->orderBy('threshold_pv')
                ->get();

            foreach ($definitions as $definition) {
                $exists = UserStatusBonus::query()
                    ->where('user_id', $user->id)
                    ->where('status_bonus_definition_id', $definition->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $cashAmount = $this->cashAmount($definition);
                $bonusTransaction = $this->createCashBonusTransaction($user, $definition, $cashAmount);

                $created->push(UserStatusBonus::query()->create([
                    'user_id' => $user->id,
                    'status_bonus_definition_id' => $definition->id,
                    'bonus_transaction_id' => $bonusTransaction?->id,
                    'status_code' => $definition->status_code,
                    'amount' => $cashAmount,
                    'currency' => $definition->currency,
                    'reward_text' => $definition->reward_text,
                    'awarded_at' => now(),
                    'metadata' => [
                        'threshold_pv' => (string) $definition->threshold_pv,
                        'user_total_pv' => (string) $user->total_pv,
                        'weak_leg_pv' => $weakLegPv,
                        'left_pv' => (string) $user->left_pv,
                        'right_pv' => (string) $user->right_pv,
                        'elite_required' => true,
                        'package_id' => $user->current_package_id,
                        'package_code' => $user->currentPackage?->code,
                        'reward_type' => $definition->reward_type,
                        'cash_amount' => $cashAmount,
                        'compensation_amount' => (string) ($definition->compensation_amount ?? '0.00'),
                        'compensation_available' => (bool) ($definition->compensation_available ?? false),
                        'compensation_paid' => false,
                    ],
                ]));
            }

            return $created;
        });
    }

    public function awardManualStatusBonus(User $user, string $statusCode): ?UserStatusBonus
    {
        return DB::transaction(function () use ($user, $statusCode): ?UserStatusBonus {
            $user = User::query()
                ->with('currentPackage')
                ->lockForUpdate()
                ->findOrFail($user->id);

            $definition = StatusBonusDefinition::query()
                ->where('status_code', $statusCode)
                ->where('is_active', true)
                ->first();

            if (! $definition) {
                return null;
            }

            $existing = UserStatusBonus::query()
                ->where('user_id', $user->id)
                ->where('status_bonus_definition_id', $definition->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $cashAmount = $this->cashAmount($definition);
            $bonusTransaction = $this->createCashBonusTransaction($user, $definition, $cashAmount, true);

            return UserStatusBonus::query()->create([
                'user_id' => $user->id,
                'status_bonus_definition_id' => $definition->id,
                'bonus_transaction_id' => $bonusTransaction?->id,
                'status_code' => $definition->status_code,
                'amount' => $cashAmount,
                'currency' => $definition->currency,
                'reward_text' => $definition->reward_text,
                'awarded_at' => now(),
                'metadata' => [
                    'manual_status_assignment' => true,
                    'threshold_pv' => (string) $definition->threshold_pv,
                    'user_total_pv' => (string) $user->total_pv,
                    'weak_leg_pv' => $this->weakLegPv($user),
                    'left_pv' => (string) $user->left_pv,
                    'right_pv' => (string) $user->right_pv,
                    'package_id' => $user->current_package_id,
                    'package_code' => $user->currentPackage?->code,
                    'reward_type' => $definition->reward_type,
                    'cash_amount' => $cashAmount,
                    'compensation_amount' => (string) ($definition->compensation_amount ?? '0.00'),
                    'compensation_available' => (bool) ($definition->compensation_available ?? false),
                    'compensation_paid' => false,
                ],
            ]);
        });
    }

    private function createCashBonusTransaction(
        User $user,
        StatusBonusDefinition $definition,
        string $cashAmount,
        bool $manualStatusAssignment = false,
    ): ?BonusTransaction
    {
        if (! $definition->is_cash_bonus || bccomp($cashAmount, '0', 2) <= 0) {
            return null;
        }

        $this->walletService->createUserWallets($user);

        $wallet = $user->wallets()
            ->where('type', 'main')
            ->lockForUpdate()
            ->firstOrFail();

        $bonusTransaction = BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => 'status',
            'amount' => $cashAmount,
            'status' => 'completed',
            'metadata' => [
                'status_code' => $definition->status_code,
                'status_bonus_definition_id' => $definition->id,
                'threshold_pv' => (string) $definition->threshold_pv,
                'weak_leg_pv' => $this->weakLegPv($user),
                'left_pv' => (string) $user->left_pv,
                'right_pv' => (string) $user->right_pv,
                'elite_required' => true,
                'package_id' => $user->current_package_id,
                'package_code' => $user->currentPackage?->code,
                'reward_text' => $definition->reward_text,
                'reward_type' => $definition->reward_type,
                'cash_amount' => $cashAmount,
                'compensation_amount' => (string) ($definition->compensation_amount ?? '0.00'),
                'compensation_available' => (bool) ($definition->compensation_available ?? false),
                'compensation_paid' => false,
                'manual_status_assignment' => $manualStatusAssignment,
            ],
            'calculated_at' => now(),
        ]);

        $walletTransaction = $this->walletService->credit(
            $wallet,
            $cashAmount,
            'status_bonus',
            $bonusTransaction,
            [
                'status_code' => $definition->status_code,
                'manual_status_assignment' => $manualStatusAssignment,
            ],
            $manualStatusAssignment
                ? "Manual status assignment: {$definition->status_code}"
                : "Status bonus: {$definition->status_code}",
        );

        $bonusTransaction->forceFill([
            'wallet_transaction_id' => $walletTransaction->id,
        ])->save();

        $bonusTransaction = $bonusTransaction->refresh();
        $user->notify(new BonusAccruedNotification($bonusTransaction));

        return $bonusTransaction;
    }

    private function cashAmount(StatusBonusDefinition $definition): string
    {
        $cashAmount = (string) ($definition->cash_amount ?? '0.00');

        return bccomp($cashAmount, '0', 2) > 0 ? $cashAmount : (string) $definition->amount;
    }

    private function hasElitePackage(User $user): bool
    {
        return strtoupper((string) $user->currentPackage?->code) === 'ELITE';
    }

    private function weakLegPv(User $user): string
    {
        $leftPv = (string) ($user->left_pv ?? '0');
        $rightPv = (string) ($user->right_pv ?? '0');

        return bccomp($leftPv, $rightPv, 2) <= 0 ? $leftPv : $rightPv;
    }
}

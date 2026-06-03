<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\StatusBonusDefinition;
use App\Models\User;
use App\Models\UserStatusBonus;
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
        return DB::transaction(function () use ($user): Collection {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $created = collect();

            $definitions = StatusBonusDefinition::query()
                ->where('is_active', true)
                ->where('threshold_pv', '<=', $user->total_pv)
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

    private function createCashBonusTransaction(User $user, StatusBonusDefinition $definition, string $cashAmount): ?BonusTransaction
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
                'reward_text' => $definition->reward_text,
                'reward_type' => $definition->reward_type,
                'cash_amount' => $cashAmount,
                'compensation_amount' => (string) ($definition->compensation_amount ?? '0.00'),
                'compensation_available' => (bool) ($definition->compensation_available ?? false),
                'compensation_paid' => false,
            ],
            'calculated_at' => now(),
        ]);

        $walletTransaction = $this->walletService->credit(
            $wallet,
            $cashAmount,
            'status_bonus',
            $bonusTransaction
        );

        $bonusTransaction->forceFill([
            'wallet_transaction_id' => $walletTransaction->id,
        ])->save();

        return $bonusTransaction->refresh();
    }

    private function cashAmount(StatusBonusDefinition $definition): string
    {
        $cashAmount = (string) ($definition->cash_amount ?? '0.00');

        return bccomp($cashAmount, '0', 2) > 0 ? $cashAmount : (string) $definition->amount;
    }
}

<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\User;
use App\Models\UserX2Bonus;
use App\Models\X2BonusDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class X2BonusService
{
    public function __construct(
        private readonly StatusService $statusService,
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * @return Collection<int, UserX2Bonus>
     */
    public function awardEligible(User $user): Collection
    {
        return DB::transaction(function () use ($user): Collection {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $created = collect();

            $definitions = X2BonusDefinition::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($definitions as $definition) {
                $qualifiedCount = $this->qualifiedFirstLineCount($user, $definition->required_status);

                if ($qualifiedCount < $definition->required_count) {
                    continue;
                }

                $exists = UserX2Bonus::query()
                    ->where('user_id', $user->id)
                    ->where('x2_bonus_definition_id', $definition->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $bonusTransaction = $this->createCashBonusTransaction($user, $definition, $qualifiedCount);

                $created->push(UserX2Bonus::query()->create([
                    'user_id' => $user->id,
                    'x2_bonus_definition_id' => $definition->id,
                    'bonus_transaction_id' => $bonusTransaction?->id,
                    'code' => $definition->code,
                    'qualified_count' => $qualifiedCount,
                    'amount' => $definition->amount,
                    'currency' => $definition->currency,
                    'reward_text' => $definition->reward_text,
                    'awarded_at' => now(),
                    'metadata' => [
                        'required_status' => $definition->required_status,
                        'required_count' => $definition->required_count,
                        'reward_type' => $definition->reward_type,
                    ],
                ]));
            }

            return $created;
        });
    }

    private function qualifiedFirstLineCount(User $user, string $requiredStatus): int
    {
        $requiredRank = $this->statusService->rankForStatus($requiredStatus);

        if ($requiredRank === 0) {
            return 0;
        }

        return $user->referrals()
            ->get(['id', 'status'])
            ->filter(fn (User $referral): bool => $this->statusService->rankForStatus($referral->status) >= $requiredRank)
            ->count();
    }

    private function createCashBonusTransaction(
        User $user,
        X2BonusDefinition $definition,
        int $qualifiedCount,
    ): ?BonusTransaction {
        if (! $definition->is_cash_bonus || bccomp((string) $definition->amount, '0', 2) <= 0) {
            return null;
        }

        $this->walletService->createUserWallets($user);

        $wallet = $user->wallets()
            ->where('type', 'main')
            ->lockForUpdate()
            ->firstOrFail();

        $bonusTransaction = BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => 'bonus_x2',
            'amount' => $definition->amount,
            'status' => 'completed',
            'metadata' => [
                'x2_bonus_definition_id' => $definition->id,
                'code' => $definition->code,
                'required_status' => $definition->required_status,
                'qualified_count' => $qualifiedCount,
                'reward_text' => $definition->reward_text,
            ],
            'calculated_at' => now(),
        ]);

        $walletTransaction = $this->walletService->credit(
            $wallet,
            $definition->amount,
            'x2_bonus',
            $bonusTransaction
        );

        $bonusTransaction->forceFill([
            'wallet_transaction_id' => $walletTransaction->id,
        ])->save();

        return $bonusTransaction->refresh();
    }
}

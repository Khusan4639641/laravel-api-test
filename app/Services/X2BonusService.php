<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\BinaryNode;
use App\Models\User;
use App\Models\UserX2Bonus;
use App\Models\X2BonusDefinition;
use App\Notifications\X2BonusAwardedNotification;
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
            $user = User::query()
                ->with('binaryNode')
                ->activeAccount()
                ->lockForUpdate()
                ->findOrFail($user->id);
            $created = collect();

            $definitions = X2BonusDefinition::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($definitions as $definition) {
                $qualification = $this->firstLineQualification($user, $definition->required_status);

                if (! $this->meetsDistribution($qualification, $definition->required_count)) {
                    continue;
                }

                $exists = UserX2Bonus::query()
                    ->where('user_id', $user->id)
                    ->where('x2_bonus_definition_id', $definition->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $bonusTransaction = $this->createCashBonusTransaction($user, $definition, $qualification);

                $userX2Bonus = UserX2Bonus::query()->create([
                    'user_id' => $user->id,
                    'x2_bonus_definition_id' => $definition->id,
                    'bonus_transaction_id' => $bonusTransaction?->id,
                    'code' => $definition->code,
                    'qualified_count' => $qualification['qualified_count'],
                    'amount' => $definition->amount,
                    'currency' => $definition->currency,
                    'reward_text' => $definition->reward_text,
                    'awarded_at' => now(),
                    'metadata' => [
                        'required_status' => $definition->required_status,
                        'required_count' => $definition->required_count,
                        'left_count' => $qualification['left_count'],
                        'right_count' => $qualification['right_count'],
                        'qualified_user_ids' => $qualification['qualified_user_ids'],
                        'reward_type' => $definition->reward_type,
                    ],
                ]);

                $user->notify(new X2BonusAwardedNotification($userX2Bonus->refresh()));
                $created->push($userX2Bonus);
            }

            return $created;
        });
    }

    /**
     * @return array{qualified_count: int, left_count: int, right_count: int, qualified_user_ids: array<int, int>}
     */
    private function firstLineQualification(User $user, string $requiredStatus): array
    {
        $requiredRank = $this->statusService->rankForStatus($requiredStatus);

        if ($requiredRank === 0) {
            return [
                'qualified_count' => 0,
                'left_count' => 0,
                'right_count' => 0,
                'qualified_user_ids' => [],
            ];
        }

        $leftCount = 0;
        $rightCount = 0;
        $qualifiedUserIds = [];

        $user->referrals()
            ->with('binaryNode')
            ->activeAccount()
            ->get(['id', 'sponsor_id', 'status'])
            ->each(function (User $referral) use ($user, $requiredRank, &$leftCount, &$rightCount, &$qualifiedUserIds): void {
                if ($this->statusService->rankForStatus($referral->status) < $requiredRank) {
                    return;
                }

                $side = $this->branchSideForReferral($user, $referral);

                if ($side === 'L') {
                    $leftCount++;
                    $qualifiedUserIds[] = $referral->id;
                }

                if ($side === 'R') {
                    $rightCount++;
                    $qualifiedUserIds[] = $referral->id;
                }
            });

        return [
            'qualified_count' => $leftCount + $rightCount,
            'left_count' => $leftCount,
            'right_count' => $rightCount,
            'qualified_user_ids' => $qualifiedUserIds,
        ];
    }

    /**
     * @param  array{qualified_count: int, left_count: int, right_count: int, qualified_user_ids: array<int, int>}  $qualification
     */
    private function meetsDistribution(array $qualification, int $requiredCount): bool
    {
        return $qualification['qualified_count'] >= $requiredCount
            && $qualification['left_count'] >= 2
            && $qualification['right_count'] >= 2;
    }

    private function branchSideForReferral(User $sponsor, User $referral): ?string
    {
        $sponsorNode = $sponsor->binaryNode;
        $referralNode = $referral->binaryNode;

        if (! $sponsorNode || ! $referralNode) {
            return null;
        }

        $sponsorPath = trim((string) $sponsorNode->path, '.');
        $referralPath = trim((string) $referralNode->path, '.');

        if ($sponsorPath === '' || $referralPath === '' || $referralPath === $sponsorPath) {
            return null;
        }

        $prefix = $sponsorPath.'.';

        if (! str_starts_with($referralPath, $prefix)) {
            return null;
        }

        $relativePath = substr($referralPath, strlen($prefix));
        $branchRootUserId = (int) explode('.', $relativePath)[0];

        return BinaryNode::query()
            ->where('parent_id', $sponsorNode->id)
            ->where('user_id', $branchRootUserId)
            ->where('is_active', true)
            ->value('position');
    }

    /**
     * @param  array{qualified_count: int, left_count: int, right_count: int, qualified_user_ids: array<int, int>}  $qualification
     */
    private function createCashBonusTransaction(
        User $user,
        X2BonusDefinition $definition,
        array $qualification,
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
                'qualified_count' => $qualification['qualified_count'],
                'left_count' => $qualification['left_count'],
                'right_count' => $qualification['right_count'],
                'qualified_user_ids' => $qualification['qualified_user_ids'],
                'reward_text' => $definition->reward_text,
            ],
            'calculated_at' => now(),
        ]);

        $walletTransaction = $this->walletService->credit(
            $wallet,
            $definition->amount,
            'x2_bonus',
            $bonusTransaction,
            [
                'source' => 'x2_bonus',
                'x2_bonus_definition_id' => $definition->id,
                'code' => $definition->code,
            ],
            "X2 bonus: {$definition->code}",
        );

        $bonusTransaction->forceFill([
            'wallet_transaction_id' => $walletTransaction->id,
        ])->save();

        return $bonusTransaction->refresh();
    }
}

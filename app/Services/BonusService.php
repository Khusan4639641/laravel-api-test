<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\BinaryBonusCalculation;
use App\Models\BinaryBonusRun;
use App\Models\BinaryNode;
use App\Models\PvTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\BonusAccruedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BonusService
{
    private const REFERRAL_PERCENT = '10';

    private const DEPOSIT_CASHBACK_PERCENT = '20';

    private const PV_MONEY_RATE = '500';

    private const BINARY_PERIOD_DAYS = 15;

    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function accrueReferralBonus(
        User $sponsor,
        User $referral,
        float|string $baseAmount,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): ?BonusTransaction
    {
        return DB::transaction(function () use ($sponsor, $referral, $baseAmount, $metadata, $idempotencyKey): ?BonusTransaction {
            if ($sponsor->trashed() || $referral->trashed() || $sponsor->account_status !== 'active' || $referral->account_status !== 'active') {
                return null;
            }

            if ($idempotencyKey !== null) {
                $existingBonus = $this->findExistingReferralBonus($sponsor, $referral, $idempotencyKey);

                if ($existingBonus) {
                    return $existingBonus;
                }
            }

            $sponsor->loadMissing('currentPackage');
            $percent = self::REFERRAL_PERCENT;

            if (bccomp($percent, '0', 2) <= 0) {
                return null;
            }

            $amount = bcdiv(bcmul((string) $baseAmount, $percent, 2), '100', 2);

            if (bccomp($amount, '0', 2) <= 0) {
                return null;
            }

            $this->walletService->createUserWallets($sponsor);

            $wallet = $sponsor->wallets()
                ->where('type', 'main')
                ->lockForUpdate()
                ->firstOrFail();

            $bonusMetadata = array_merge($metadata, [
                'base_amount' => (string) $baseAmount,
                'percent_source' => 'business_tz',
                'referral_percent' => $percent,
                'sponsor_package_id' => $sponsor->current_package_id,
                'referral_package_id' => $referral->current_package_id,
            ]);

            if ($idempotencyKey !== null) {
                $bonusMetadata['referral_bonus_key'] = $idempotencyKey;
            }

            $bonusTransaction = BonusTransaction::query()->create([
                'user_id' => $sponsor->id,
                'source_user_id' => $referral->id,
                'bonus_type' => 'referral',
                'amount' => $amount,
                'status' => 'completed',
                'metadata' => $bonusMetadata,
                'calculated_at' => now(),
            ]);

            $walletTransaction = $this->walletService->credit(
                $wallet,
                $amount,
                'referral_bonus',
                $bonusTransaction,
                [
                    'source' => 'referral_bonus',
                    'referral_user_id' => $referral->id,
                ],
                "Referral bonus: {$referral->login}",
            );

            $bonusTransaction->forceFill([
                'wallet_transaction_id' => $walletTransaction->id,
            ])->save();

            $sponsor->notify(new BonusAccruedNotification($bonusTransaction->refresh()));

            return $bonusTransaction->refresh();
        });
    }

    private function findExistingReferralBonus(User $sponsor, User $referral, string $idempotencyKey): ?BonusTransaction
    {
        return BonusTransaction::query()
            ->where('user_id', $sponsor->id)
            ->where('source_user_id', $referral->id)
            ->where('bonus_type', 'referral')
            ->get()
            ->first(fn (BonusTransaction $bonus): bool => ($bonus->metadata['referral_bonus_key'] ?? null) === $idempotencyKey);
    }

    public function calculateBinaryBonus(User $user): ?BonusTransaction
    {
        return DB::transaction(function () use ($user): ?BonusTransaction {
            $user = User::query()
                ->with('currentPackage')
                ->activeMlm()
                ->lockForUpdate()
                ->findOrFail($user->id);

            if ($this->hasActiveBinaryRun($user)) {
                return null;
            }

            if (! $this->hasDirectReferralInEachBinaryBranch($user)) {
                return null;
            }

            $pvSnapshot = $this->binaryPvSnapshot($user);
            $basePv = $this->minDecimal($pvSnapshot['left_available_pv'], $pvSnapshot['right_available_pv']);
            $percent = (string) ($user->currentPackage?->binary_percent ?? 0);

            if (bccomp($basePv, '0', 2) <= 0 || bccomp($percent, '0', 2) <= 0) {
                return null;
            }

            $moneyBaseAmount = bcmul($basePv, self::PV_MONEY_RATE, 2);
            $amount = bcdiv(bcmul($moneyBaseAmount, $percent, 2), '100', 2);

            if (bccomp($amount, '0', 2) <= 0) {
                return null;
            }

            $mainAmount = bcdiv(bcmul($amount, '90', 2), '100', 2);
            $bonusAmount = bcsub($amount, $mainAmount, 2);
            $periodStart = now();
            $periodEnd = $periodStart->copy()->addDays(self::BINARY_PERIOD_DAYS);
            $carryLeftPv = bcsub($pvSnapshot['left_available_pv'], $basePv, 2);
            $carryRightPv = bcsub($pvSnapshot['right_available_pv'], $basePv, 2);
            $diagnostics = [
                ...$pvSnapshot,
                'weak_leg_pv' => $basePv,
                'binary_total' => $amount,
                'main_wallet_amount' => $mainAmount,
                'deposit_amount' => $bonusAmount,
            ];

            Log::info('Binary calculation input', [
                'user_id' => $user->id,
                'left_total_pv' => $diagnostics['left_total_pv'],
                'right_total_pv' => $diagnostics['right_total_pv'],
                'left_excluded_deleted_pv' => $diagnostics['left_excluded_deleted_pv'],
                'right_excluded_deleted_pv' => $diagnostics['right_excluded_deleted_pv'],
                'left_excluded_elite_non_bonusable_pv' => $diagnostics['left_excluded_elite_non_bonusable_pv'],
                'right_excluded_elite_non_bonusable_pv' => $diagnostics['right_excluded_elite_non_bonusable_pv'],
                'left_bonusable_pv' => $diagnostics['left_bonusable_pv'],
                'right_bonusable_pv' => $diagnostics['right_bonusable_pv'],
                'weak_leg_pv' => $diagnostics['weak_leg_pv'],
                'binary_total' => $diagnostics['binary_total'],
                'main_wallet_amount' => $diagnostics['main_wallet_amount'],
                'deposit_amount' => $diagnostics['deposit_amount'],
            ]);

            $user->forceFill([
                'remaining_left_pv' => $carryLeftPv,
                'remaining_right_pv' => $carryRightPv,
            ])->save();

            $run = BinaryBonusRun::query()->create([
                'user_id' => $user->id,
                'status' => 'completed',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'weak_leg_pv' => $basePv,
                'used_left_pv' => $basePv,
                'used_right_pv' => $basePv,
                'carry_left_pv' => $carryLeftPv,
                'carry_right_pv' => $carryRightPv,
                'amount' => $amount,
                'pending_amount' => '0.00',
                'metadata' => [
                    'pv_money_rate' => self::PV_MONEY_RATE,
                    'binary_percent' => $percent,
                    'package_id' => $user->current_package_id,
                    'diagnostics' => $diagnostics,
                ],
            ]);

            $this->walletService->createUserWallets($user);

            $mainWallet = $user->wallets()
                ->where('type', 'main')
                ->lockForUpdate()
                ->firstOrFail();
            $depositWallet = $user->wallets()
                ->where('type', 'deposit')
                ->lockForUpdate()
                ->firstOrFail();

            $bonusTransaction = BonusTransaction::query()->create([
                'user_id' => $user->id,
                'bonus_type' => 'binary',
                'amount' => $amount,
                'left_pv' => $pvSnapshot['left_total_pv'],
                'right_pv' => $pvSnapshot['right_total_pv'],
                'matched_pv' => $basePv,
                'status' => 'completed',
                'metadata' => [
                    'base_pv' => $basePv,
                    'money_base_amount' => $moneyBaseAmount,
                    'pv_money_rate' => self::PV_MONEY_RATE,
                    'binary_percent' => $percent,
                    'main_percent' => '90.00',
                    'deposit_percent' => '10.00',
                    'main_amount' => $mainAmount,
                    'deposit_amount' => $bonusAmount,
                    'package_id' => $user->current_package_id,
                    'period_start' => $periodStart->toISOString(),
                    'period_end' => $periodEnd->toISOString(),
                    'used_left_pv' => $basePv,
                    'used_right_pv' => $basePv,
                    'carry_left_pv' => $carryLeftPv,
                    'carry_right_pv' => $carryRightPv,
                    'remaining_left_pv_after' => $carryLeftPv,
                    'remaining_right_pv_after' => $carryRightPv,
                    'diagnostics' => $diagnostics,
                ],
                'calculated_at' => now(),
            ]);

            $mainWalletTransaction = $this->walletService->credit(
                $mainWallet,
                $mainAmount,
                'binary_bonus_main',
                $bonusTransaction,
                [
                    'source' => 'binary_bonus',
                    'wallet_part' => 'main',
                    'base_pv' => $basePv,
                    'binary_percent' => $percent,
                ],
                'Binary bonus: main wallet 90%',
            );
            $depositWalletTransaction = $this->walletService->credit(
                $depositWallet,
                $bonusAmount,
                'binary_bonus_deposit',
                $bonusTransaction,
                [
                    'source' => 'binary_bonus',
                    'wallet_part' => 'deposit',
                    'base_pv' => $basePv,
                    'binary_percent' => $percent,
                ],
                'Binary bonus: deposit wallet 10%',
            );

            $metadata = $bonusTransaction->metadata;
            $metadata['main_wallet_transaction_id'] = $mainWalletTransaction->id;
            $metadata['deposit_wallet_transaction_id'] = $depositWalletTransaction->id;

            $bonusTransaction->forceFill([
                'wallet_transaction_id' => $mainWalletTransaction->id,
                'metadata' => $metadata,
            ])->save();

            $run->forceFill([
                'bonus_transaction_id' => $bonusTransaction->id,
            ])->save();

            BinaryBonusCalculation::query()->create([
                'binary_bonus_run_id' => $run->id,
                'user_id' => $user->id,
                'bonus_transaction_id' => $bonusTransaction->id,
                'left_pv' => $pvSnapshot['left_total_pv'],
                'right_pv' => $pvSnapshot['right_total_pv'],
                'weak_leg_pv' => $basePv,
                'used_left_pv' => $basePv,
                'used_right_pv' => $basePv,
                'carry_left_pv' => $carryLeftPv,
                'carry_right_pv' => $carryRightPv,
                'money_base_amount' => $moneyBaseAmount,
                'binary_percent' => $percent,
                'bonus_amount' => $amount,
                'main_amount' => $mainAmount,
                'deposit_amount' => $bonusAmount,
                'metadata' => [
                    'pv_money_rate' => self::PV_MONEY_RATE,
                    'period_start' => $periodStart->toISOString(),
                    'period_end' => $periodEnd->toISOString(),
                    'diagnostics' => $diagnostics,
                ],
            ]);

            $user->notify(new BonusAccruedNotification($bonusTransaction->refresh()));

            return $bonusTransaction->refresh();
        });
    }

    /**
     * @return array{message: string, data: array<string, mixed>, bonus_transaction: ?BonusTransaction}
     */
    public function recalculateBinaryBonus(User $user, User $admin): array
    {
        return DB::transaction(function () use ($user, $admin): array {
            $user = User::query()
                ->with('currentPackage')
                ->activeMlm()
                ->lockForUpdate()
                ->findOrFail($user->id);

            $currentRun = $this->currentBinaryRun($user);
            $preview = $this->previewBinaryCalculation($user, $currentRun?->id);

            if (! $currentRun) {
                if (! $preview['eligible'] || bccomp($preview['binary_total'], '0', 2) <= 0) {
                    return [
                        'message' => 'Бинар не начислен',
                        'data' => $this->recalculationResponseData($user, null, $preview, '0.00', '0.00'),
                        'bonus_transaction' => null,
                    ];
                }

                $bonusTransaction = $this->calculateBinaryBonus($user);
                $currentRun = $this->currentBinaryRun($user->refresh());

                return [
                    'message' => 'Бинар пересчитан',
                    'data' => $this->recalculationResponseData(
                        $user,
                        $currentRun,
                        $preview,
                        $preview['main_wallet_amount'],
                        $preview['deposit_amount'],
                    ),
                    'bonus_transaction' => $bonusTransaction?->refresh(),
                ];
            }

            $currentRun->load('bonusTransaction');
            [$oldMainAmount, $oldDepositAmount] = $this->currentRunWalletAmounts($currentRun);
            $newMainAmount = $preview['eligible'] ? $preview['main_wallet_amount'] : '0.00';
            $newDepositAmount = $preview['eligible'] ? $preview['deposit_amount'] : '0.00';
            $adjustmentMain = $this->decimal(bcsub($newMainAmount, $oldMainAmount, 2));
            $adjustmentDeposit = $this->decimal(bcsub($newDepositAmount, $oldDepositAmount, 2));
            $bonusTransaction = $this->ensureBinaryBonusTransaction($currentRun, $user);
            $adjustmentMetadata = [
                'source' => 'binary_recalculation',
                'binary_bonus_run_id' => $currentRun->id,
                'old_main_amount' => $oldMainAmount,
                'old_deposit_amount' => $oldDepositAmount,
                'new_main_amount' => $newMainAmount,
                'new_deposit_amount' => $newDepositAmount,
                'adjustment_main' => $adjustmentMain,
                'adjustment_deposit' => $adjustmentDeposit,
                'weak_leg_pv' => $preview['weak_leg_pv'],
                'excluded_deleted_pv' => bcadd($preview['diagnostics']['left_excluded_deleted_pv'], $preview['diagnostics']['right_excluded_deleted_pv'], 2),
                'excluded_non_bonusable_pv' => bcadd($preview['diagnostics']['left_excluded_non_bonusable_pv'], $preview['diagnostics']['right_excluded_non_bonusable_pv'], 2),
                'recalculated_by_admin_id' => $admin->id,
                'recalculated_at' => now()->toISOString(),
            ];

            $this->walletService->createUserWallets($user);
            $mainWallet = $user->wallets()
                ->where('type', 'main')
                ->lockForUpdate()
                ->firstOrFail();
            $depositWallet = $user->wallets()
                ->where('type', 'deposit')
                ->lockForUpdate()
                ->firstOrFail();

            $mainAdjustmentTransaction = $this->applyBinaryWalletAdjustment(
                $mainWallet,
                $adjustmentMain,
                'binary_bonus_main_adjustment',
                $bonusTransaction,
                [...$adjustmentMetadata, 'wallet_part' => 'main'],
                'Перерасчёт бинарного бонуса: корректировка основного кошелька',
            );
            $depositAdjustmentTransaction = $this->applyBinaryWalletAdjustment(
                $depositWallet,
                $adjustmentDeposit,
                'binary_bonus_deposit_adjustment',
                $bonusTransaction,
                [...$adjustmentMetadata, 'wallet_part' => 'deposit'],
                'Перерасчёт бинарного бонуса: корректировка депозита',
            );

            $usedPv = $preview['eligible'] ? $preview['weak_leg_pv'] : '0.00';
            $carryLeftPv = $preview['eligible']
                ? $this->positiveOrZero(bcsub($preview['diagnostics']['left_available_pv'], $usedPv, 2))
                : $preview['diagnostics']['left_available_pv'];
            $carryRightPv = $preview['eligible']
                ? $this->positiveOrZero(bcsub($preview['diagnostics']['right_available_pv'], $usedPv, 2))
                : $preview['diagnostics']['right_available_pv'];
            $runMetadata = $currentRun->metadata ?? [];
            $runMetadata['pv_money_rate'] = self::PV_MONEY_RATE;
            $runMetadata['binary_percent'] = $preview['binary_percent'];
            $runMetadata['package_id'] = $user->current_package_id;
            $runMetadata['diagnostics'] = $preview['diagnostics'];
            $runMetadata['manual_recalculation'] = [
                ...$adjustmentMetadata,
                'current_main_amount' => $newMainAmount,
                'current_deposit_amount' => $newDepositAmount,
                'main_adjustment_transaction_id' => $mainAdjustmentTransaction?->id,
                'deposit_adjustment_transaction_id' => $depositAdjustmentTransaction?->id,
                'eligible' => $preview['eligible'],
                'reason' => $preview['reason'],
            ];

            $currentRun->forceFill([
                'status' => 'completed',
                'weak_leg_pv' => $usedPv,
                'used_left_pv' => $usedPv,
                'used_right_pv' => $usedPv,
                'carry_left_pv' => $carryLeftPv,
                'carry_right_pv' => $carryRightPv,
                'amount' => $preview['eligible'] ? $preview['binary_total'] : '0.00',
                'pending_amount' => '0.00',
                'metadata' => $runMetadata,
            ])->save();

            $user->forceFill([
                'remaining_left_pv' => $carryLeftPv,
                'remaining_right_pv' => $carryRightPv,
            ])->save();

            $bonusMetadata = $bonusTransaction->metadata ?? [];
            $bonusMetadata['recalculation'] = $runMetadata['manual_recalculation'];
            $bonusMetadata['diagnostics'] = $preview['diagnostics'];

            $bonusTransaction->forceFill([
                'amount' => $preview['eligible'] ? $preview['binary_total'] : '0.00',
                'left_pv' => $preview['diagnostics']['left_total_pv'],
                'right_pv' => $preview['diagnostics']['right_total_pv'],
                'matched_pv' => $usedPv,
                'status' => 'completed',
                'metadata' => $bonusMetadata,
                'calculated_at' => now(),
            ])->save();

            BinaryBonusCalculation::query()->create([
                'binary_bonus_run_id' => $currentRun->id,
                'user_id' => $user->id,
                'bonus_transaction_id' => $bonusTransaction->id,
                'left_pv' => $preview['diagnostics']['left_total_pv'],
                'right_pv' => $preview['diagnostics']['right_total_pv'],
                'weak_leg_pv' => $preview['weak_leg_pv'],
                'used_left_pv' => $usedPv,
                'used_right_pv' => $usedPv,
                'carry_left_pv' => $carryLeftPv,
                'carry_right_pv' => $carryRightPv,
                'money_base_amount' => $preview['money_base_amount'],
                'binary_percent' => $preview['binary_percent'],
                'bonus_amount' => $preview['eligible'] ? $preview['binary_total'] : '0.00',
                'main_amount' => $newMainAmount,
                'deposit_amount' => $newDepositAmount,
                'metadata' => [
                    'source' => 'manual_binary_recalculation',
                    'pv_money_rate' => self::PV_MONEY_RATE,
                    'diagnostics' => $preview['diagnostics'],
                    'recalculation' => $runMetadata['manual_recalculation'],
                ],
            ]);

            return [
                'message' => $preview['eligible'] ? 'Бинар пересчитан' : 'Бинар не начислен',
                'data' => $this->recalculationResponseData($user, $currentRun, $preview, $adjustmentMain, $adjustmentDeposit),
                'bonus_transaction' => $bonusTransaction->refresh(),
            ];
        });
    }

    private function hasActiveBinaryRun(User $user): bool
    {
        return BinaryBonusRun::query()
            ->where('user_id', $user->id)
            ->where('period_end', '>', now())
            ->exists();
    }

    private function currentBinaryRun(User $user): ?BinaryBonusRun
    {
        return BinaryBonusRun::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['completed', 'pending'])
            ->where('period_end', '>', now())
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function previewBinaryCalculation(User $user, ?int $ignoredRunId = null): array
    {
        $hasDirectReferrals = $this->hasDirectReferralInEachBinaryBranch($user);
        $pvSnapshot = $this->binaryPvSnapshot($user, $ignoredRunId);
        $weakLegPv = $this->minDecimal($pvSnapshot['left_available_pv'], $pvSnapshot['right_available_pv']);
        $percent = $this->decimal((string) ($user->currentPackage?->binary_percent ?? 0));
        $moneyBaseAmount = bcmul($weakLegPv, self::PV_MONEY_RATE, 2);
        $binaryTotal = bcdiv(bcmul($moneyBaseAmount, $percent, 2), '100', 2);
        $eligible = true;
        $reason = null;

        if (! $hasDirectReferrals) {
            $eligible = false;
            $reason = 'Нужен минимум 1 лично приглашённый партнёр в левой и правой ветке';
        } elseif (bccomp($weakLegPv, '0', 2) <= 0) {
            $eligible = false;
            $reason = 'Нет доступного PV для расчёта';
        } elseif (bccomp($percent, '0', 2) <= 0) {
            $eligible = false;
            $reason = 'У текущего пакета нет процента бинарного бонуса';
        } elseif (bccomp($binaryTotal, '0', 2) <= 0) {
            $eligible = false;
            $reason = 'Нет доступной суммы для начисления';
        }

        $binaryTotal = $eligible ? $this->decimal($binaryTotal) : '0.00';
        $mainAmount = $eligible ? bcdiv(bcmul($binaryTotal, '90', 2), '100', 2) : '0.00';
        $depositAmount = $eligible ? bcsub($binaryTotal, $mainAmount, 2) : '0.00';
        $diagnostics = [
            ...$pvSnapshot,
            'weak_leg_pv' => $weakLegPv,
            'binary_total' => $binaryTotal,
            'main_wallet_amount' => $mainAmount,
            'deposit_amount' => $depositAmount,
            'eligible' => $eligible,
            'reason' => $reason,
        ];

        Log::info('Binary calculation input', [
            'user_id' => $user->id,
            'left_total_pv' => $diagnostics['left_total_pv'],
            'right_total_pv' => $diagnostics['right_total_pv'],
            'left_excluded_deleted_pv' => $diagnostics['left_excluded_deleted_pv'],
            'right_excluded_deleted_pv' => $diagnostics['right_excluded_deleted_pv'],
            'left_excluded_elite_non_bonusable_pv' => $diagnostics['left_excluded_elite_non_bonusable_pv'],
            'right_excluded_elite_non_bonusable_pv' => $diagnostics['right_excluded_elite_non_bonusable_pv'],
            'left_bonusable_pv' => $diagnostics['left_bonusable_pv'],
            'right_bonusable_pv' => $diagnostics['right_bonusable_pv'],
            'weak_leg_pv' => $diagnostics['weak_leg_pv'],
            'binary_total' => $diagnostics['binary_total'],
            'main_wallet_amount' => $diagnostics['main_wallet_amount'],
            'deposit_amount' => $diagnostics['deposit_amount'],
        ]);

        return [
            'eligible' => $eligible,
            'reason' => $reason,
            'diagnostics' => $diagnostics,
            'weak_leg_pv' => $weakLegPv,
            'binary_percent' => $percent,
            'money_base_amount' => $moneyBaseAmount,
            'binary_total' => $binaryTotal,
            'main_wallet_amount' => $this->decimal($mainAmount),
            'deposit_amount' => $this->decimal($depositAmount),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recalculationResponseData(
        User $user,
        ?BinaryBonusRun $run,
        array $preview,
        string $adjustmentMain,
        string $adjustmentDeposit,
    ): array {
        $diagnostics = $preview['diagnostics'];

        return [
            'user_id' => $user->id,
            'period_id' => $run?->id,
            'left_pv_total' => $diagnostics['left_total_pv'],
            'right_pv_total' => $diagnostics['right_total_pv'],
            'left_pv_excluded_deleted' => $diagnostics['left_excluded_deleted_pv'],
            'right_pv_excluded_deleted' => $diagnostics['right_excluded_deleted_pv'],
            'left_pv_excluded_non_bonusable' => $diagnostics['left_excluded_non_bonusable_pv'],
            'right_pv_excluded_non_bonusable' => $diagnostics['right_excluded_non_bonusable_pv'],
            'left_pv_bonusable' => $diagnostics['left_bonusable_pv'],
            'right_pv_bonusable' => $diagnostics['right_bonusable_pv'],
            'left_used_prior_pv' => $diagnostics['left_used_prior_pv'],
            'right_used_prior_pv' => $diagnostics['right_used_prior_pv'],
            'left_pv_available' => $diagnostics['left_available_pv'],
            'right_pv_available' => $diagnostics['right_available_pv'],
            'weak_leg_pv' => $preview['weak_leg_pv'],
            'binary_percent' => $preview['binary_percent'],
            'binary_total' => $preview['binary_total'],
            'main_wallet_amount' => $preview['main_wallet_amount'],
            'deposit_amount' => $preview['deposit_amount'],
            'adjustment_main' => $this->decimal($adjustmentMain),
            'adjustment_deposit' => $this->decimal($adjustmentDeposit),
            'eligible' => $preview['eligible'],
            'reason' => $preview['reason'],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function currentRunWalletAmounts(BinaryBonusRun $run): array
    {
        $metadata = $run->metadata ?? [];
        $manualRecalculation = is_array($metadata['manual_recalculation'] ?? null)
            ? $metadata['manual_recalculation']
            : [];

        if (isset($manualRecalculation['current_main_amount'], $manualRecalculation['current_deposit_amount'])) {
            return [
                $this->decimal((string) $manualRecalculation['current_main_amount']),
                $this->decimal((string) $manualRecalculation['current_deposit_amount']),
            ];
        }

        if (! $run->bonus_transaction_id) {
            return ['0.00', '0.00'];
        }

        $mainAmount = '0.00';
        $depositAmount = '0.00';

        WalletTransaction::query()
            ->where('user_id', $run->user_id)
            ->where('source_type', BonusTransaction::class)
            ->where('source_id', $run->bonus_transaction_id)
            ->where('status', 'completed')
            ->where('affects_balance', true)
            ->whereIn('type', [
                'binary_bonus_main',
                'binary_bonus_deposit',
                'binary_bonus_main_adjustment',
                'binary_bonus_deposit_adjustment',
            ])
            ->each(function (WalletTransaction $transaction) use (&$mainAmount, &$depositAmount): void {
                $signedAmount = $transaction->direction === 'debit'
                    ? bcmul((string) $transaction->amount, '-1', 2)
                    : (string) $transaction->amount;

                if (in_array($transaction->type, ['binary_bonus_main', 'binary_bonus_main_adjustment'], true)) {
                    $mainAmount = bcadd($mainAmount, $signedAmount, 2);

                    return;
                }

                $depositAmount = bcadd($depositAmount, $signedAmount, 2);
            });

        return [
            $this->decimal($mainAmount),
            $this->decimal($depositAmount),
        ];
    }

    private function ensureBinaryBonusTransaction(BinaryBonusRun $run, User $user): BonusTransaction
    {
        if ($run->bonusTransaction) {
            return $run->bonusTransaction;
        }

        $bonusTransaction = BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => 'binary',
            'amount' => '0.00',
            'left_pv' => '0.00',
            'right_pv' => '0.00',
            'matched_pv' => '0.00',
            'status' => 'completed',
            'metadata' => [
                'source' => 'manual_binary_recalculation',
                'binary_bonus_run_id' => $run->id,
            ],
            'calculated_at' => now(),
        ]);

        $run->forceFill([
            'bonus_transaction_id' => $bonusTransaction->id,
        ])->save();

        return $bonusTransaction;
    }

    private function applyBinaryWalletAdjustment(
        mixed $wallet,
        string $adjustment,
        string $type,
        BonusTransaction $bonusTransaction,
        array $metadata,
        string $description,
    ): ?WalletTransaction {
        $adjustment = $this->decimal($adjustment);

        if (bccomp($adjustment, '0', 2) === 0) {
            return null;
        }

        if (bccomp($adjustment, '0', 2) > 0) {
            return $this->walletService->credit(
                $wallet,
                $adjustment,
                $type,
                $bonusTransaction,
                $metadata,
                $description,
            );
        }

        return $this->walletService->debit(
            $wallet,
            bcmul($adjustment, '-1', 2),
            $type,
            $bonusTransaction,
            $metadata,
            $description,
        );
    }

    private function hasDirectReferralInEachBinaryBranch(User $user): bool
    {
        $sponsorNode = $user->binaryNode()
            ->where('is_active', true)
            ->first();

        if (! $sponsorNode) {
            return false;
        }

        $sponsorPath = trim((string) $sponsorNode->path, '.');

        if ($sponsorPath === '') {
            return false;
        }

        $rootBranchPositions = BinaryNode::query()
            ->where('parent_id', $sponsorNode->id)
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->activeMlm())
            ->pluck('position', 'user_id');

        if ($rootBranchPositions->isEmpty()) {
            return false;
        }

        $branches = [];

        User::query()
            ->where('sponsor_id', $user->id)
            ->where('role', User::ROLE_USER)
            ->activeMlm()
            ->with(['binaryNode' => fn ($query) => $query->where('is_active', true)])
            ->each(function (User $referral) use ($sponsorPath, $rootBranchPositions, &$branches): void {
                $referralPath = trim((string) $referral->binaryNode?->path, '.');

                if ($referralPath === '' || $referralPath === $sponsorPath) {
                    return;
                }

                $prefix = $sponsorPath.'.';

                if (! str_starts_with($referralPath, $prefix)) {
                    return;
                }

                $relativePath = substr($referralPath, strlen($prefix));
                $branchRootUserId = (int) explode('.', $relativePath)[0];
                $position = $rootBranchPositions[$branchRootUserId] ?? null;

                if (in_array($position, ['L', 'R'], true)) {
                    $branches[$position] = true;
                }
            });

        return isset($branches['L'], $branches['R']);
    }

    /**
     * @return array<string, string|bool>
     */
    private function binaryPvSnapshot(User $user, ?int $ignoredRunId = null): array
    {
        $hasPvTransactions = PvTransaction::query()
            ->where('upline_id', $user->id)
            ->whereIn('branch', ['L', 'R'])
            ->exists();

        if (! $hasPvTransactions) {
            return [
                'uses_pv_transactions' => false,
                'left_total_pv' => $this->decimal((string) ($user->left_pv ?? '0')),
                'right_total_pv' => $this->decimal((string) ($user->right_pv ?? '0')),
                'left_excluded_deleted_pv' => '0.00',
                'right_excluded_deleted_pv' => '0.00',
                'left_excluded_inactive_or_deleted_pv' => '0.00',
                'right_excluded_inactive_or_deleted_pv' => '0.00',
                'left_excluded_voided_pv' => '0.00',
                'right_excluded_voided_pv' => '0.00',
                'left_excluded_elite_non_bonusable_pv' => '0.00',
                'right_excluded_elite_non_bonusable_pv' => '0.00',
                'left_excluded_non_bonusable_pv' => '0.00',
                'right_excluded_non_bonusable_pv' => '0.00',
                'left_bonusable_pv' => $this->decimal((string) ($user->remaining_left_pv ?? '0')),
                'right_bonusable_pv' => $this->decimal((string) ($user->remaining_right_pv ?? '0')),
                'left_used_prior_pv' => '0.00',
                'right_used_prior_pv' => '0.00',
                'left_available_pv' => $this->decimal((string) ($user->remaining_left_pv ?? '0')),
                'right_available_pv' => $this->decimal((string) ($user->remaining_right_pv ?? '0')),
            ];
        }

        $left = $this->branchPvComponents($user, 'L', $ignoredRunId);
        $right = $this->branchPvComponents($user, 'R', $ignoredRunId);

        return [
            'uses_pv_transactions' => true,
            'left_total_pv' => $left['total_pv'],
            'right_total_pv' => $right['total_pv'],
            'left_excluded_deleted_pv' => $left['excluded_inactive_or_deleted_pv'],
            'right_excluded_deleted_pv' => $right['excluded_inactive_or_deleted_pv'],
            'left_excluded_inactive_or_deleted_pv' => $left['excluded_inactive_or_deleted_pv'],
            'right_excluded_inactive_or_deleted_pv' => $right['excluded_inactive_or_deleted_pv'],
            'left_excluded_voided_pv' => $left['excluded_voided_pv'],
            'right_excluded_voided_pv' => $right['excluded_voided_pv'],
            'left_excluded_elite_non_bonusable_pv' => $left['excluded_elite_non_bonusable_pv'],
            'right_excluded_elite_non_bonusable_pv' => $right['excluded_elite_non_bonusable_pv'],
            'left_excluded_non_bonusable_pv' => $left['excluded_non_bonusable_pv'],
            'right_excluded_non_bonusable_pv' => $right['excluded_non_bonusable_pv'],
            'left_bonusable_pv' => $left['bonusable_pv'],
            'right_bonusable_pv' => $right['bonusable_pv'],
            'left_used_prior_pv' => $left['used_prior_pv'],
            'right_used_prior_pv' => $right['used_prior_pv'],
            'left_available_pv' => $left['available_pv'],
            'right_available_pv' => $right['available_pv'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function branchPvComponents(User $user, string $branch, ?int $ignoredRunId = null): array
    {
        $baseQuery = PvTransaction::query()
            ->where('upline_id', $user->id)
            ->where('branch', $branch);

        $nonVoidedQuery = (clone $baseQuery)->whereNull('voided_at');
        $activeBuyerQuery = (clone $nonVoidedQuery)
            ->whereHas('buyer', fn ($query) => $query->activeMlm()->where('role', User::ROLE_USER));
        $totalPv = $this->sumPv($nonVoidedQuery);
        $excludedVoidedPv = $this->sumPv((clone $baseQuery)->whereNotNull('voided_at'));
        $excludedInactiveOrDeletedPv = $this->sumPv(
            (clone $nonVoidedQuery)->whereDoesntHave('buyer', fn ($query) => $query->activeMlm()->where('role', User::ROLE_USER))
        );
        $excludedNonBonusablePv = $this->sumPv((clone $activeBuyerQuery)->where('is_bonusable', false));
        $excludedEliteNonBonusablePv = $this->sumPv(
            (clone $activeBuyerQuery)
                ->where('is_bonusable', false)
                ->where('source', 'package_elite_upgrade')
        );
        $bonusablePv = $this->sumPv((clone $activeBuyerQuery)->where('is_bonusable', true));
        $usedPriorPv = $this->usedPriorBinaryPv($user, $branch, $ignoredRunId);
        $availablePv = $this->positiveOrZero(bcsub($bonusablePv, $usedPriorPv, 2));

        return [
            'total_pv' => $totalPv,
            'excluded_voided_pv' => $excludedVoidedPv,
            'excluded_inactive_or_deleted_pv' => $excludedInactiveOrDeletedPv,
            'excluded_non_bonusable_pv' => $excludedNonBonusablePv,
            'excluded_elite_non_bonusable_pv' => $excludedEliteNonBonusablePv,
            'bonusable_pv' => $bonusablePv,
            'used_prior_pv' => $usedPriorPv,
            'available_pv' => $availablePv,
        ];
    }

    private function usedPriorBinaryPv(User $user, string $branch, ?int $ignoredRunId = null): string
    {
        $column = $branch === 'L' ? 'used_left_pv' : 'used_right_pv';

        return $this->decimal((string) BinaryBonusRun::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['completed', 'pending'])
            ->when($ignoredRunId, fn ($query) => $query->whereKeyNot($ignoredRunId))
            ->sum($column));
    }

    private function sumPv($query): string
    {
        return $this->decimal((string) $query->sum('pv'));
    }

    public function accrueDepositPurchaseCashback(
        User $user,
        float|string $purchaseAmount,
        ?WalletTransaction $sourceTransaction = null,
    ): ?BonusTransaction {
        return DB::transaction(function () use ($user, $purchaseAmount, $sourceTransaction): ?BonusTransaction {
            if ($user->trashed() || $user->account_status !== 'active') {
                return null;
            }

            $purchaseAmount = (string) $purchaseAmount;
            $amount = bcdiv(bcmul($purchaseAmount, self::DEPOSIT_CASHBACK_PERCENT, 2), '100', 2);

            if (bccomp($amount, '0', 2) <= 0) {
                return null;
            }

            $this->walletService->createUserWallets($user);

            $wallet = $user->wallets()
                ->where('type', 'main')
                ->lockForUpdate()
                ->firstOrFail();

            $bonusTransaction = BonusTransaction::query()->create([
                'user_id' => $user->id,
                'bonus_type' => 'cashback',
                'amount' => $amount,
                'status' => 'completed',
                'metadata' => [
                    'purchase_amount' => $purchaseAmount,
                    'cashback_percent' => self::DEPOSIT_CASHBACK_PERCENT,
                    'source' => 'deposit_purchase',
                    'deposit_wallet_transaction_id' => $sourceTransaction?->id,
                ],
                'calculated_at' => now(),
            ]);

            $walletTransaction = $this->walletService->credit(
                $wallet,
                $amount,
                'deposit_purchase_cashback',
                $bonusTransaction,
                [
                    'source' => 'deposit_purchase_cashback',
                    'purchase_amount' => $purchaseAmount,
                ],
                'Deposit purchase cashback',
            );

            $bonusTransaction->forceFill([
                'wallet_transaction_id' => $walletTransaction->id,
            ])->save();

            $user->notify(new BonusAccruedNotification($bonusTransaction->refresh()));

            return $bonusTransaction->refresh();
        });
    }

    private function minDecimal(string $left, string $right): string
    {
        return bccomp($left, $right, 2) <= 0 ? $left : $right;
    }

    private function positiveOrZero(string $value): string
    {
        return bccomp($value, '0', 2) > 0 ? $this->decimal($value) : '0.00';
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}

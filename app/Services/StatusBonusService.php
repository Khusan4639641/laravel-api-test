<?php

namespace App\Services;

use App\Models\AdminActionLog;
use App\Models\BonusTransaction;
use App\Models\StatusBonusDefinition;
use App\Models\User;
use App\Models\UserStatusBonus;
use App\Models\WalletTransaction;
use App\Notifications\BonusAccruedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatusBonusService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly DashboardBranchVolumeService $branchVolumeService,
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
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function syncForUser(User $user, array $context = []): array
    {
        return $this->repairForUser($user, [
            ...$context,
            'dry_run' => false,
            'repair_ledger' => true,
        ]);
    }

    /**
     * @return array{total: int, existing: int, missing: int, created: int, updated: int, missing_codes: array<int, string>}
     */
    public function seedDefaultDefinitions(bool $dryRun = false, ?string $statusCode = null): array
    {
        $defaults = $this->defaultDefinitionRows($statusCode);
        $codes = $defaults->pluck('status_code')->all();
        $existing = StatusBonusDefinition::query()
            ->whereIn('status_code', $codes ?: [''])
            ->get()
            ->keyBy('status_code');
        $missingCodes = $defaults
            ->reject(fn (array $row): bool => $existing->has($row['status_code']))
            ->pluck('status_code')
            ->values()
            ->all();
        $created = 0;
        $updated = 0;

        if (! $dryRun) {
            foreach ($defaults as $row) {
                $alreadyExists = $existing->has($row['status_code']);

                StatusBonusDefinition::query()->updateOrCreate(
                    ['status_code' => $row['status_code']],
                    $row,
                );

                $alreadyExists ? $updated++ : $created++;
            }
        }

        return [
            'total' => $defaults->count(),
            'existing' => $existing->count(),
            'missing' => count($missingCodes),
            'created' => $created,
            'updated' => $updated,
            'missing_codes' => $missingCodes,
        ];
    }

    /**
     * @return array{required: int, existing: int, missing: int, missing_codes: array<int, string>}
     */
    public function definitionsHealth(?string $statusCode = null): array
    {
        $defaults = $this->defaultDefinitionRows($statusCode);
        $codes = $defaults->pluck('status_code')->all();
        $existingCodes = StatusBonusDefinition::query()
            ->whereIn('status_code', $codes ?: [''])
            ->pluck('status_code')
            ->all();
        $missingCodes = $defaults
            ->pluck('status_code')
            ->diff($existingCodes)
            ->values()
            ->all();

        return [
            'required' => $defaults->count(),
            'existing' => count($existingCodes),
            'missing' => count($missingCodes),
            'missing_codes' => $missingCodes,
        ];
    }

    /**
     * @return Collection<int, UserStatusBonus>
     */
    public function checkMissedStatusBonuses(User $user): Collection
    {
        return DB::transaction(function () use ($user): Collection {
            $user = User::query()
                ->with('currentPackage')
                ->activeAccount()
                ->lockForUpdate()
                ->findOrFail($user->id);
            $created = collect();

            if (! $this->hasElitePackage($user)) {
                return $created;
            }

            $branchVolumes = $this->branchVolumes($user);
            $weakLegPv = $this->weakLegPvFromVolumes($branchVolumes);

            $definitions = $this->eligibleDefinitions($weakLegPv);

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
                        'left_pv' => $branchVolumes['left_pv'],
                        'right_pv' => $branchVolumes['right_pv'],
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

    public function awardManualStatusBonus(User $user, string $statusCode, ?User $actor = null): ?UserStatusBonus
    {
        $awarded = $this->awardManualStatusBonuses($user, $statusCode, $actor);

        $matchingAward = $awarded->first(fn (UserStatusBonus $bonus): bool => $bonus->status_code === $statusCode);

        if ($matchingAward) {
            return $matchingAward;
        }

        $definition = StatusBonusDefinition::query()
            ->where('status_code', $statusCode)
            ->where('is_active', true)
            ->first();

        if (! $definition) {
            return null;
        }

        return UserStatusBonus::query()
            ->where('user_id', $user->id)
            ->where('status_bonus_definition_id', $definition->id)
            ->first();
    }

    /**
     * @return Collection<int, UserStatusBonus>
     */
    public function awardManualStatusBonuses(User $user, string $statusCode, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($user, $statusCode, $actor): Collection {
            $user = User::query()
                ->with('currentPackage')
                ->activeAccount()
                ->lockForUpdate()
                ->findOrFail($user->id);
            $created = collect();

            if (! $this->hasElitePackage($user)) {
                return $created;
            }

            $definition = StatusBonusDefinition::query()
                ->where('status_code', $statusCode)
                ->where('is_active', true)
                ->first();

            if (! $definition) {
                return $created;
            }

            $definitions = StatusBonusDefinition::query()
                ->where('is_active', true)
                ->where('threshold_pv', '<=', $definition->threshold_pv)
                ->orderBy('threshold_pv')
                ->get();

            foreach ($definitions as $statusDefinition) {
                $exists = UserStatusBonus::query()
                    ->where('user_id', $user->id)
                    ->where('status_bonus_definition_id', $statusDefinition->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $cashAmount = $this->cashAmount($statusDefinition);
                $branchVolumes = $this->branchVolumes($user);
                $bonusTransaction = $this->createCashBonusTransaction($user, $statusDefinition, $cashAmount, true, $actor);

                $statusBonus = UserStatusBonus::query()->create([
                    'user_id' => $user->id,
                    'status_bonus_definition_id' => $statusDefinition->id,
                    'bonus_transaction_id' => $bonusTransaction?->id,
                    'status_code' => $statusDefinition->status_code,
                    'amount' => $cashAmount,
                    'currency' => $statusDefinition->currency,
                    'reward_text' => $statusDefinition->reward_text,
                    'awarded_at' => now(),
                    'metadata' => [
                        'manual_status_assignment' => true,
                        'assigned_status_code' => $statusCode,
                        'threshold_pv' => (string) $statusDefinition->threshold_pv,
                        'user_total_pv' => (string) $user->total_pv,
                        'weak_leg_pv' => $this->weakLegPvFromVolumes($branchVolumes),
                        'left_pv' => $branchVolumes['left_pv'],
                        'right_pv' => $branchVolumes['right_pv'],
                        'elite_required' => true,
                        'package_id' => $user->current_package_id,
                        'package_code' => $user->currentPackage?->code,
                        'reward_type' => $statusDefinition->reward_type,
                        'cash_amount' => $cashAmount,
                        'compensation_amount' => (string) ($statusDefinition->compensation_amount ?? '0.00'),
                        'compensation_available' => (bool) ($statusDefinition->compensation_available ?? false),
                        'compensation_paid' => false,
                        'admin_id' => $actor?->id,
                    ],
                ]);

                $this->recordStatusBonusPaidAudit($user, $statusBonus, $bonusTransaction, $actor);
                $created->push($statusBonus);
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function repairForUser(User $user, array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? true);
        $repairLedger = (bool) ($options['repair_ledger'] ?? false);
        $statusCode = $options['status_code'] ?? null;
        $onlyMissing = (bool) ($options['only_missing'] ?? false);
        $source = (string) ($options['source'] ?? ($dryRun ? 'status_bonus_repair_dry_run' : 'status_bonus_repair'));

        return DB::transaction(function () use ($user, $dryRun, $repairLedger, $statusCode, $onlyMissing, $source): array {
            $user = User::query()
                ->with('currentPackage')
                ->activeAccount()
                ->lockForUpdate()
                ->findOrFail($user->id);
            $branchVolumes = $this->branchVolumes($user);
            $weakLegPv = $this->weakLegPvFromVolumes($branchVolumes);
            $definitions = $this->eligibleDefinitions($weakLegPv, $statusCode);
            $rows = [];
            $summary = [
                'user_id' => $user->id,
                'package_code' => $user->currentPackage?->code,
                'left_pv' => $branchVolumes['left_pv'],
                'right_pv' => $branchVolumes['right_pv'],
                'weak_leg_pv' => $weakLegPv,
                'eligible' => $this->hasElitePackage($user),
                'skipped_reason' => null,
                'definitions_count' => $definitions->count(),
                'awarded_count' => 0,
                'repaired_count' => 0,
                'already_awarded_count' => 0,
                'skipped_count' => 0,
                'warnings_count' => 0,
                'errors_count' => 0,
            ];

            if (! $summary['eligible']) {
                $summary['skipped_reason'] = 'not_elite';
                $summary['skipped_count'] = $definitions->count();

                return [
                    ...$summary,
                    'rows' => [],
                ];
            }

            foreach ($definitions as $definition) {
                $row = $this->inspectStatusLedger($user, $definition, $branchVolumes, $weakLegPv);
                $hasMissing = ! empty($row['missing']);

                if ($onlyMissing && ! $hasMissing) {
                    continue;
                }

                if (! empty($row['warnings'])) {
                    $summary['warnings_count'] += count($row['warnings']);
                }

                if (! $dryRun && $hasMissing) {
                    $legacyTypeRepaired = false;

                    if ((bool) ($row['legacy_bonus_type'] ?? false) || (bool) ($row['bonus_pv_columns_need_repair'] ?? false)) {
                        $this->normalizeStatusBonusTransactions($user, $definition, $branchVolumes, $weakLegPv);
                        $legacyTypeRepaired = true;
                    }

                    if (! $row['marker_exists']) {
                        $repairResult = $this->repairMissingMarker(
                            $user,
                            $definition,
                            $branchVolumes,
                            $weakLegPv,
                            $row,
                            $source,
                        );
                        $row = [...$row, ...$repairResult];
                        $summary[$repairResult['action'] === 'awarded' ? 'awarded_count' : 'repaired_count']++;
                    } elseif ($repairLedger && $definition->is_cash_bonus && $this->hasLedgerMissing($row)) {
                        $repairResult = $this->repairMissingCashLedger(
                            $user,
                            $definition,
                            $branchVolumes,
                            $weakLegPv,
                            $row,
                            $source,
                        );
                        $row = [...$row, ...$repairResult];
                        $summary['repaired_count']++;
                    } elseif ($legacyTypeRepaired && ! $this->hasLedgerMissing($row)) {
                        $row['action'] = 'repaired';
                        $row['missing'] = [];
                        $summary['repaired_count']++;
                    } else {
                        $row['action'] = 'missing_ledger_not_repaired';
                        $summary['skipped_count']++;
                    }
                } elseif ($hasMissing) {
                    $row['action'] = 'would_repair';
                    $summary['skipped_count']++;
                } else {
                    $row['action'] = 'already_awarded';
                    $summary['already_awarded_count']++;
                }

                $rows[] = $row;
            }

            return [
                ...$summary,
                'rows' => $rows,
            ];
        });
    }

    private function createCashBonusTransaction(
        User $user,
        StatusBonusDefinition $definition,
        string $cashAmount,
        bool $manualStatusAssignment = false,
        ?User $actor = null,
        array $context = [],
    ): ?BonusTransaction
    {
        if (! $definition->is_cash_bonus || bccomp($cashAmount, '0', 2) <= 0) {
            return null;
        }

        $this->walletService->createUserWallets($user);
        $branchVolumes = $this->branchVolumes($user);
        $weakLegPv = $this->weakLegPvFromVolumes($branchVolumes);

        $wallet = $user->wallets()
            ->where('type', 'main')
            ->lockForUpdate()
            ->firstOrFail();

        $bonusTransaction = BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => 'status_bonus',
            'amount' => $cashAmount,
            'left_pv' => $branchVolumes['left_pv'],
            'right_pv' => $branchVolumes['right_pv'],
            'matched_pv' => $weakLegPv,
            'status' => 'completed',
            'metadata' => [
                'source' => $context['source'] ?? ($manualStatusAssignment ? 'manual_status_assignment' : 'status_bonus_listener'),
                'status_code' => $definition->status_code,
                'status_name' => $definition->status_name,
                'status_bonus_definition_id' => $definition->id,
                'threshold_pv' => (string) $definition->threshold_pv,
                'weak_leg_pv' => $weakLegPv,
                'left_pv' => $branchVolumes['left_pv'],
                'right_pv' => $branchVolumes['right_pv'],
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
                'admin_id' => $actor?->id,
            ],
            'calculated_at' => now(),
        ]);

        $walletTransaction = $this->walletService->credit(
            $wallet,
            $cashAmount,
            'status_bonus',
            $bonusTransaction,
            [
                'source' => $context['source'] ?? ($manualStatusAssignment ? 'manual_status_assignment' : 'status_bonus_listener'),
                'status_code' => $definition->status_code,
                'status_name' => $definition->status_name,
                'status_bonus_definition_id' => $definition->id,
                'threshold_pv' => (string) $definition->threshold_pv,
                'weak_leg_pv' => $weakLegPv,
                'left_pv' => $branchVolumes['left_pv'],
                'right_pv' => $branchVolumes['right_pv'],
                'cash_amount' => $cashAmount,
                'manual_status_assignment' => $manualStatusAssignment,
                'admin_id' => $actor?->id,
            ],
            "Статусный бонус: {$definition->status_name}",
        );

        $bonusTransaction->forceFill([
            'wallet_transaction_id' => $walletTransaction->id,
        ])->save();

        $bonusTransaction = $bonusTransaction->refresh();
        $user->notify(new BonusAccruedNotification($bonusTransaction));

        return $bonusTransaction;
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectStatusLedger(User $user, StatusBonusDefinition $definition, array $branchVolumes, string $weakLegPv): array
    {
        $marker = UserStatusBonus::query()
            ->where('user_id', $user->id)
            ->where('status_bonus_definition_id', $definition->id)
            ->first();
        $bonusTransactions = $this->statusBonusTransactions($user, $definition);
        $walletTransactions = $this->statusWalletTransactions($user, $definition, $bonusTransactions);
        $missing = [];
        $warnings = [];

        if (! $marker) {
            $missing[] = 'user_status_bonus';
        }

        if ($definition->is_cash_bonus && bccomp($this->cashAmount($definition), '0', 2) > 0) {
            if ($bonusTransactions->isEmpty()) {
                $missing[] = 'bonus_transaction';
            }

            if ($walletTransactions->isEmpty()) {
                $missing[] = 'wallet_transaction';
            }
        }

        if ($bonusTransactions->count() > 1) {
            $warnings[] = 'duplicate_bonus_transactions';
        }

        if ($walletTransactions->count() > 1) {
            $warnings[] = 'duplicate_wallet_transactions';
        }

        $legacyBonusIds = $bonusTransactions
            ->filter(fn (BonusTransaction $transaction): bool => $transaction->bonus_type === 'status')
            ->pluck('id')
            ->values()
            ->all();

        if ($legacyBonusIds !== []) {
            $missing[] = 'canonical_bonus_type';
            $warnings[] = 'legacy_bonus_type_status';
        }

        $pvColumnRepairIds = $bonusTransactions
            ->filter(fn (BonusTransaction $transaction): bool => $this->bonusPvColumnsNeedRepair($transaction))
            ->pluck('id')
            ->values()
            ->all();

        if ($pvColumnRepairIds !== []) {
            $missing[] = 'bonus_pv_columns';
            $warnings[] = 'bonus_pv_columns_zero';
        }

        return [
            'status_code' => $definition->status_code,
            'status_name' => $definition->status_name,
            'threshold_pv' => (string) $definition->threshold_pv,
            'amount' => $this->cashAmount($definition),
            'is_cash_bonus' => (bool) $definition->is_cash_bonus,
            'left_pv' => $branchVolumes['left_pv'],
            'right_pv' => $branchVolumes['right_pv'],
            'weak_leg_pv' => $weakLegPv,
            'marker_exists' => (bool) $marker,
            'bonus_transaction_exists' => $bonusTransactions->isNotEmpty(),
            'wallet_transaction_exists' => $walletTransactions->isNotEmpty(),
            'marker_id' => $marker?->id,
            'bonus_transaction_id' => $bonusTransactions->first()?->id,
            'wallet_transaction_id' => $walletTransactions->first()?->id,
            'legacy_bonus_type' => $legacyBonusIds !== [],
            'legacy_bonus_transaction_ids' => $legacyBonusIds,
            'bonus_pv_columns_need_repair' => $pvColumnRepairIds !== [],
            'bonus_pv_column_repair_ids' => $pvColumnRepairIds,
            'missing' => $missing,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function repairMissingMarker(
        User $user,
        StatusBonusDefinition $definition,
        array $branchVolumes,
        string $weakLegPv,
        array $row,
        string $source,
    ): array {
        $bonusTransaction = $row['bonus_transaction_id']
            ? BonusTransaction::query()->find($row['bonus_transaction_id'])
            : null;
        $walletTransaction = $row['wallet_transaction_id']
            ? WalletTransaction::query()->find($row['wallet_transaction_id'])
            : null;
        $cashAmount = $this->cashAmount($definition);
        $action = 'repaired';

        if ($definition->is_cash_bonus && bccomp($cashAmount, '0', 2) > 0) {
            if (! $bonusTransaction && $walletTransaction) {
                $bonusTransaction = $this->createBonusTransactionForExistingWalletTransaction(
                    $user,
                    $definition,
                    $walletTransaction,
                    $branchVolumes,
                    $weakLegPv,
                    $source,
                );
            }

            if (! $bonusTransaction) {
                $bonusTransaction = $this->createCashBonusTransaction($user, $definition, $cashAmount, false, null, [
                    'source' => $source,
                ]);
                $action = 'awarded';
            } elseif (! $walletTransaction) {
                $walletTransaction = $this->createWalletTransactionForBonus(
                    $user,
                    $definition,
                    $bonusTransaction,
                    $cashAmount,
                    $branchVolumes,
                    $weakLegPv,
                    $source,
                );
            }
        }

        $statusBonus = $this->createStatusBonusMarker(
            $user,
            $definition,
            $bonusTransaction,
            $cashAmount,
            $branchVolumes,
            $weakLegPv,
            [
                'source' => $source,
                'ledger_repair' => $action !== 'awarded',
            ],
        );

        return [
            'action' => $action,
            'marker_id' => $statusBonus->id,
            'bonus_transaction_id' => $bonusTransaction?->id,
            'wallet_transaction_id' => $bonusTransaction?->wallet_transaction_id ?? $walletTransaction?->id,
            'missing' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function repairMissingCashLedger(
        User $user,
        StatusBonusDefinition $definition,
        array $branchVolumes,
        string $weakLegPv,
        array $row,
        string $source,
    ): array {
        $marker = UserStatusBonus::query()->find($row['marker_id']);
        $bonusTransaction = $row['bonus_transaction_id']
            ? BonusTransaction::query()->find($row['bonus_transaction_id'])
            : null;
        $walletTransaction = $row['wallet_transaction_id']
            ? WalletTransaction::query()->find($row['wallet_transaction_id'])
            : null;
        $cashAmount = $this->cashAmount($definition);

        if (! $bonusTransaction && $walletTransaction) {
            $bonusTransaction = $this->createBonusTransactionForExistingWalletTransaction(
                $user,
                $definition,
                $walletTransaction,
                $branchVolumes,
                $weakLegPv,
                $source,
            );
        }

        if (! $bonusTransaction) {
            $bonusTransaction = $this->createCashBonusTransaction($user, $definition, $cashAmount, false, null, [
                'source' => $source,
            ]);
        } elseif (! $walletTransaction) {
            $walletTransaction = $this->createWalletTransactionForBonus(
                $user,
                $definition,
                $bonusTransaction,
                $cashAmount,
                $branchVolumes,
                $weakLegPv,
                $source,
            );
        }

        if ($marker && ! $marker->bonus_transaction_id && $bonusTransaction) {
            $marker->forceFill([
                'bonus_transaction_id' => $bonusTransaction->id,
            ])->save();
        }

        return [
            'action' => 'repaired',
            'marker_id' => $marker?->id,
            'bonus_transaction_id' => $bonusTransaction?->id,
            'wallet_transaction_id' => $bonusTransaction?->wallet_transaction_id ?? $walletTransaction?->id,
            'missing' => [],
        ];
    }

    private function createWalletTransactionForBonus(
        User $user,
        StatusBonusDefinition $definition,
        BonusTransaction $bonusTransaction,
        string $cashAmount,
        array $branchVolumes,
        string $weakLegPv,
        string $source,
    ): WalletTransaction {
        $this->walletService->createUserWallets($user);

        $wallet = $user->wallets()
            ->where('type', 'main')
            ->lockForUpdate()
            ->firstOrFail();

        $walletTransaction = $this->walletService->credit(
            $wallet,
            $cashAmount,
            'status_bonus',
            $bonusTransaction,
            [
                'source' => $source,
                'status_code' => $definition->status_code,
                'status_name' => $definition->status_name,
                'status_bonus_definition_id' => $definition->id,
                'threshold_pv' => (string) $definition->threshold_pv,
                'weak_leg_pv' => $weakLegPv,
                'left_pv' => $branchVolumes['left_pv'],
                'right_pv' => $branchVolumes['right_pv'],
                'cash_amount' => $cashAmount,
            ],
            "Статусный бонус: {$definition->status_name}",
        );

        $bonusTransaction->forceFill([
            'wallet_transaction_id' => $walletTransaction->id,
        ])->save();

        return $walletTransaction;
    }

    private function createBonusTransactionForExistingWalletTransaction(
        User $user,
        StatusBonusDefinition $definition,
        WalletTransaction $walletTransaction,
        array $branchVolumes,
        string $weakLegPv,
        string $source,
    ): BonusTransaction {
        $bonusTransaction = BonusTransaction::query()->create([
            'user_id' => $user->id,
            'wallet_transaction_id' => $walletTransaction->id,
            'bonus_type' => 'status_bonus',
            'amount' => $this->cashAmount($definition),
            'left_pv' => $branchVolumes['left_pv'],
            'right_pv' => $branchVolumes['right_pv'],
            'matched_pv' => $weakLegPv,
            'status' => 'completed',
            'metadata' => [
                'source' => $source,
                'status_code' => $definition->status_code,
                'status_name' => $definition->status_name,
                'status_bonus_definition_id' => $definition->id,
                'threshold_pv' => (string) $definition->threshold_pv,
                'weak_leg_pv' => $weakLegPv,
                'left_pv' => $branchVolumes['left_pv'],
                'right_pv' => $branchVolumes['right_pv'],
                'cash_amount' => $this->cashAmount($definition),
                'ledger_repair' => true,
            ],
            'calculated_at' => now(),
        ]);

        $walletTransaction->source()->associate($bonusTransaction);
        $walletTransaction->save();

        return $bonusTransaction;
    }

    private function createStatusBonusMarker(
        User $user,
        StatusBonusDefinition $definition,
        ?BonusTransaction $bonusTransaction,
        string $cashAmount,
        array $branchVolumes,
        string $weakLegPv,
        array $metadata = [],
    ): UserStatusBonus {
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
                ...$metadata,
                'threshold_pv' => (string) $definition->threshold_pv,
                'user_total_pv' => (string) $user->total_pv,
                'weak_leg_pv' => $weakLegPv,
                'left_pv' => $branchVolumes['left_pv'],
                'right_pv' => $branchVolumes['right_pv'],
                'elite_required' => true,
                'package_id' => $user->current_package_id,
                'package_code' => $user->currentPackage?->code,
                'reward_type' => $definition->reward_type,
                'cash_amount' => $cashAmount,
                'compensation_amount' => (string) ($definition->compensation_amount ?? '0.00'),
                'compensation_available' => (bool) ($definition->compensation_available ?? false),
                'compensation_paid' => false,
            ],
        ]);
    }

    private function normalizeStatusBonusTransactions(
        User $user,
        StatusBonusDefinition $definition,
        array $branchVolumes,
        string $weakLegPv,
    ): void
    {
        $this->statusBonusTransactions($user, $definition)
            ->each(function (BonusTransaction $transaction) use ($branchVolumes, $weakLegPv): void {
                $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
                $metadata['canonical_bonus_type_repaired_at'] ??= now()->toISOString();

                if ($transaction->bonus_type === 'status') {
                    $metadata['legacy_bonus_type'] = 'status';
                }

                $transaction->forceFill([
                    'bonus_type' => 'status_bonus',
                    'left_pv' => $metadata['left_pv'] ?? $branchVolumes['left_pv'],
                    'right_pv' => $metadata['right_pv'] ?? $branchVolumes['right_pv'],
                    'matched_pv' => $metadata['weak_leg_pv'] ?? $weakLegPv,
                    'metadata' => $metadata,
                ])->save();
            });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasLedgerMissing(array $row): bool
    {
        return collect($row['missing'] ?? [])
            ->intersect(['user_status_bonus', 'bonus_transaction', 'wallet_transaction'])
            ->isNotEmpty();
    }

    private function bonusPvColumnsNeedRepair(BonusTransaction $transaction): bool
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];

        if (($metadata['left_pv'] ?? null) === null || ($metadata['right_pv'] ?? null) === null || ($metadata['weak_leg_pv'] ?? null) === null) {
            return false;
        }

        return bccomp((string) $transaction->left_pv, '0', 2) === 0
            && bccomp((string) $transaction->right_pv, '0', 2) === 0
            && bccomp((string) $transaction->matched_pv, '0', 2) === 0;
    }

    /**
     * @return Collection<int, BonusTransaction>
     */
    private function statusBonusTransactions(User $user, StatusBonusDefinition $definition): Collection
    {
        return BonusTransaction::query()
            ->where('user_id', $user->id)
            ->whereIn('bonus_type', ['status_bonus', 'status'])
            ->whereNotIn('status', ['reversed', 'voided', 'cancelled'])
            ->get()
            ->filter(fn (BonusTransaction $transaction): bool => ($transaction->metadata['status_code'] ?? null) === $definition->status_code)
            ->values();
    }

    /**
     * @param  Collection<int, BonusTransaction>  $bonusTransactions
     * @return Collection<int, WalletTransaction>
     */
    private function statusWalletTransactions(User $user, StatusBonusDefinition $definition, Collection $bonusTransactions): Collection
    {
        $walletTransactionIds = $bonusTransactions
            ->pluck('wallet_transaction_id')
            ->filter()
            ->values()
            ->all();

        return WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'status_bonus')
            ->where('status', WalletTransaction::STATUS_COMPLETED)
            ->get()
            ->filter(function (WalletTransaction $transaction) use ($definition, $walletTransactionIds): bool {
                if (in_array($transaction->id, $walletTransactionIds, true)) {
                    return true;
                }

                return ($transaction->metadata['status_code'] ?? null) === $definition->status_code;
            })
            ->values();
    }

    private function recordStatusBonusPaidAudit(
        User $user,
        UserStatusBonus $statusBonus,
        ?BonusTransaction $bonusTransaction,
        ?User $actor,
    ): void {
        if (! $actor || ! $bonusTransaction) {
            return;
        }

        AdminActionLog::query()->create([
            'admin_id' => $actor->id,
            'target_user_id' => $user->id,
            'action' => 'status_bonus_paid',
            'reason' => null,
            'metadata' => [
                'status_code' => $statusBonus->status_code,
                'amount' => (string) $statusBonus->amount,
                'bonus_transaction_id' => $bonusTransaction->id,
                'wallet_transaction_id' => $bonusTransaction->wallet_transaction_id,
                'manual_status_assignment' => true,
            ],
        ]);
    }

    private function cashAmount(StatusBonusDefinition $definition): string
    {
        $cashAmount = (string) ($definition->cash_amount ?? '0.00');

        return bccomp($cashAmount, '0', 2) > 0 ? $cashAmount : (string) $definition->amount;
    }

    /**
     * @return Collection<int, StatusBonusDefinition>
     */
    private function eligibleDefinitions(string $weakLegPv, ?string $statusCode = null): Collection
    {
        return StatusBonusDefinition::query()
            ->where('is_active', true)
            ->where('threshold_pv', '<=', $weakLegPv)
            ->when($statusCode, fn ($query) => $query->where('status_code', $statusCode))
            ->orderBy('threshold_pv')
            ->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function defaultDefinitionRows(?string $statusCode = null): Collection
    {
        return collect(StatusService::STATUS_DEFINITIONS)
            ->when($statusCode, fn (Collection $definitions): Collection => $definitions
                ->filter(fn (array $definition): bool => $definition['id'] === $statusCode))
            ->values()
            ->map(fn (array $definition, int $index): array => [
                'status_code' => $definition['id'],
                'status_name' => $definition['name'],
                'threshold_pv' => $definition['pv'],
                'reward_type' => $definition['reward_type'],
                'amount' => $definition['amount'],
                'cash_amount' => $definition['cash_amount'] ?? $definition['amount'],
                'compensation_amount' => $definition['compensation_amount'] ?? '0.00',
                'compensation_available' => $definition['compensation_available'] ?? false,
                'currency' => 'KZT',
                'reward_text' => $definition['reward'],
                'is_cash_bonus' => $definition['is_cash_bonus'],
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
    }

    private function hasElitePackage(User $user): bool
    {
        return strtoupper((string) $user->currentPackage?->code) === 'ELITE';
    }

    /**
     * @return array{left_pv: string, right_pv: string}
     */
    private function branchVolumes(User $user): array
    {
        $volumes = $this->branchVolumeService->getBranchVolumes($user);

        return [
            'left_pv' => $volumes['left_pv'],
            'right_pv' => $volumes['right_pv'],
        ];
    }

    /**
     * @param  array{left_pv: string, right_pv: string}  $branchVolumes
     */
    private function weakLegPvFromVolumes(array $branchVolumes): string
    {
        $leftPv = $branchVolumes['left_pv'];
        $rightPv = $branchVolumes['right_pv'];

        return bccomp($leftPv, $rightPv, 2) <= 0 ? $leftPv : $rightPv;
    }
}

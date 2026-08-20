<?php

namespace App\Services;

use App\Models\AdminActionLog;
use App\Models\BinaryBonusCalculation;
use App\Models\BinaryBonusRun;
use App\Models\BonusTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BinaryRecalculationRollbackService
{
    public const SCOPE_ADJUSTMENTS = 'adjustments-only';

    public const SCOPE_ENTIRE_BATCH = 'entire-batch';

    public const MANIFEST_VERSION = 1;

    /**
     * Discover an incident from linked records and immutable snapshots. The
     * UTC metadata timestamps and application-timezone SQL timestamps are
     * deliberately checked independently.
     *
     * @param  array<int, int>|null  $explicitRunIds
     * @return array<string, mixed>
     */
    public function discover(
        CarbonImmutable $startedAtUtc,
        CarbonImmutable $endedAtUtc,
        string $scope,
        ?array $explicitRunIds = null,
        int $controlUserId = 69,
    ): array {
        $this->assertScope($scope);

        if (! $endedAtUtc->gt($startedAtUtc)) {
            throw new RuntimeException('ended-at must be later than started-at.');
        }

        $timezone = (string) config('app.timezone', BinaryBonusPeriodResolver::TIMEZONE);
        $startedAtLocal = $startedAtUtc->setTimezone($timezone);
        $endedAtLocal = $endedAtUtc->setTimezone($timezone);
        $runQuery = BinaryBonusRun::query()->with(['user', 'bonusTransaction', 'calculations']);

        if ($explicitRunIds !== null) {
            $runQuery->whereIn('id', $explicitRunIds);
        } else {
            $runQuery->where(function ($query) use ($startedAtLocal, $endedAtLocal): void {
                $query
                    ->where(function ($historical) use ($startedAtLocal, $endedAtLocal): void {
                        $historical->where('created_at', '<', $startedAtLocal)
                            ->where('updated_at', '>=', $startedAtLocal)
                            ->where('updated_at', '<', $endedAtLocal);
                    })
                    ->orWhere(function ($new) use ($startedAtLocal, $endedAtLocal): void {
                        $new->where('created_at', '>=', $startedAtLocal)
                            ->where('created_at', '<', $endedAtLocal);
                    });
            });
        }

        $candidateRuns = $runQuery->orderBy('id')->get();
        $historicalRuns = $candidateRuns->filter(
            fn (BinaryBonusRun $run): bool => $this->isHistoricalRun($run, $startedAtUtc, $endedAtUtc, $startedAtLocal, $endedAtLocal)
        )->values();
        $newRuns = $candidateRuns->filter(
            fn (BinaryBonusRun $run): bool => $this->isNewRun($run, $startedAtLocal, $endedAtLocal)
        )->values();

        $classifiedIds = $historicalRuns->pluck('id')->merge($newRuns->pluck('id'))->map(fn ($id) => (int) $id)->sort()->values();
        $inconsistencies = [];

        if ($explicitRunIds !== null) {
            $unclassified = collect($explicitRunIds)->diff($classifiedIds)->values()->all();

            if ($unclassified !== []) {
                $inconsistencies[] = 'Explicit run IDs failed batch classification: '.implode(', ', $unclassified);
            }
        }

        $operations = [];

        foreach ($historicalRuns as $run) {
            $operations[] = $this->historicalOperation($run, $startedAtLocal, $endedAtLocal);
        }

        if ($scope === self::SCOPE_ENTIRE_BATCH) {
            foreach ($newRuns as $run) {
                $operations[] = $this->newRunOperation($run, $startedAtLocal, $endedAtLocal);
            }
        }

        $operations = $this->attachWalletReplayPlans($operations);
        $blockers = collect($operations)
            ->flatMap(fn (array $operation): array => $operation['blockers'])
            ->merge($inconsistencies)
            ->unique()
            ->values()
            ->all();
        $errors = [];

        if ($classifiedIds->isEmpty()) {
            $errors[] = 'No binary recalculation batch records were found.';
        }

        $targetHistoricalIds = $historicalRuns->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $targetNewIds = $newRuns->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $targetOperations = collect($operations);
        $userIds = $targetOperations->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        $adjustmentIds = $targetOperations->where('operation_type', 'restore_existing_run')
            ->flatMap(fn (array $operation): array => $operation['wallet_transaction_ids'])
            ->map(fn ($id) => (int) $id)->sort()->values()->all();
        $newPayoutIds = $targetOperations->where('operation_type', 'delete_new_run')
            ->flatMap(fn (array $operation): array => $operation['wallet_transaction_ids'])
            ->map(fn ($id) => (int) $id)->sort()->values()->all();
        $historicalBonusIds = $targetOperations->where('operation_type', 'restore_existing_run')
            ->pluck('bonus_transaction_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $newBonusIds = $targetOperations->where('operation_type', 'delete_new_run')
            ->pluck('bonus_transaction_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $batchCalculationIds = $targetOperations->flatMap(
            fn (array $operation): array => $operation['batch_calculation_ids']
        )->map(fn ($id) => (int) $id)->sort()->values()->all();
        $sourceCalculationIds = $targetOperations->flatMap(
            fn (array $operation): array => $operation['source_calculation_ids']
        )->map(fn ($id) => (int) $id)->sort()->values()->all();
        $notificationIds = $targetOperations->flatMap(
            fn (array $operation): array => $operation['notification_ids']
        )->values()->all();

        $stateSnapshot = $this->stateSnapshot(
            array_values(array_unique([...$targetHistoricalIds, ...$targetNewIds])),
            array_values(array_unique([...$historicalBonusIds, ...$newBonusIds])),
            array_values(array_unique([...$adjustmentIds, ...$newPayoutIds])),
            array_values(array_unique([...$batchCalculationIds, ...$sourceCalculationIds])),
            $userIds,
            $notificationIds,
        );
        $totals = $this->totals($operations, $historicalRuns, $newRuns, $blockers, $inconsistencies, $errors);
        $manifest = [
            'manifest_version' => self::MANIFEST_VERSION,
            'generated_at' => now('UTC')->toISOString(),
            'environment' => app()->environment(),
            'database' => (string) DB::connection()->getDatabaseName(),
            'started_at' => $startedAtUtc->utc()->toISOString(),
            'ended_at' => $endedAtUtc->utc()->toISOString(),
            'storage_started_at' => $startedAtLocal->format('Y-m-d H:i:s'),
            'storage_ended_at' => $endedAtLocal->format('Y-m-d H:i:s'),
            'timezone' => $timezone,
            'scope' => $scope,
            'control_user_id' => $controlUserId,
            'run_ids' => [
                'historical' => $targetHistoricalIds,
                'new' => $targetNewIds,
                'targeted' => $targetOperations->pluck('run_id')->map(fn ($id) => (int) $id)->values()->all(),
                'all_classified' => $classifiedIds->all(),
            ],
            'bonus_transaction_ids' => [
                'restore' => $historicalBonusIds,
                'delete' => $newBonusIds,
            ],
            'wallet_transaction_ids' => [
                'adjustments' => $adjustmentIds,
                'new_payouts' => $newPayoutIds,
            ],
            'calculation_ids' => [
                'immutable_restore_sources' => $sourceCalculationIds,
                'delete' => $batchCalculationIds,
            ],
            // The incident code only read pv_transactions. Consumption was
            // persisted in runs/calculations and users.remaining_*_pv.
            'pv_transaction_ids' => [],
            'notification_ids' => $notificationIds,
            'user_ids' => $userIds,
            'operations' => $operations,
            'blockers' => $blockers,
            'inconsistencies' => $inconsistencies,
            'errors' => $errors,
            'totals' => $totals,
            'state_snapshot' => $stateSnapshot,
        ];
        $manifest['fingerprint'] = $this->fingerprint($manifest);
        $manifest['control_user'] = $this->controlUserResult($manifest, $controlUserId);

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function fingerprint(array $manifest): string
    {
        $payload = [
            'manifest_version' => $manifest['manifest_version'] ?? null,
            'environment' => $manifest['environment'] ?? null,
            'database' => $manifest['database'] ?? null,
            'started_at' => $manifest['started_at'] ?? null,
            'ended_at' => $manifest['ended_at'] ?? null,
            'scope' => $manifest['scope'] ?? null,
            'run_ids' => $manifest['run_ids'] ?? null,
            'bonus_transaction_ids' => $manifest['bonus_transaction_ids'] ?? null,
            'wallet_transaction_ids' => $manifest['wallet_transaction_ids'] ?? null,
            'calculation_ids' => $manifest['calculation_ids'] ?? null,
            'pv_transaction_ids' => $manifest['pv_transaction_ids'] ?? null,
            'notification_ids' => $manifest['notification_ids'] ?? null,
            'user_ids' => $manifest['user_ids'] ?? null,
            'state_snapshot' => $manifest['state_snapshot'] ?? null,
        ];

        return hash('sha256', json_encode($this->canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $approvedManifest
     * @return array<string, mixed>
     */
    public function execute(array $approvedManifest, string $confirmedFingerprint): array
    {
        $manifestFingerprint = (string) ($approvedManifest['fingerprint'] ?? '');

        if ($manifestFingerprint === '' || ! hash_equals($manifestFingerprint, $confirmedFingerprint)) {
            throw new RuntimeException('The confirmed fingerprint does not match the approved manifest.');
        }

        if ($this->hasCompletedRollback($manifestFingerprint)) {
            return [
                'already_rolled_back' => true,
                'message' => 'Binary recalculation batch has already been rolled back.',
                'fingerprint' => $manifestFingerprint,
            ];
        }

        $startedAt = CarbonImmutable::parse((string) ($approvedManifest['started_at'] ?? ''), 'UTC')->utc();
        $endedAt = CarbonImmutable::parse((string) ($approvedManifest['ended_at'] ?? ''), 'UTC')->utc();
        $scope = (string) ($approvedManifest['scope'] ?? '');
        $reportPath = $approvedManifest['report_path'] ?? null;
        $runIds = array_map('intval', (array) data_get($approvedManifest, 'run_ids.all_classified', []));
        $controlUserId = (int) ($approvedManifest['control_user_id'] ?? 69);
        $liveManifest = $this->discover($startedAt, $endedAt, $scope, $runIds, $controlUserId);

        if (! hash_equals($manifestFingerprint, (string) $liveManifest['fingerprint'])) {
            throw new RuntimeException('Fingerprint mismatch: database state changed after dry-run. Generate and approve a new manifest.');
        }

        if ($liveManifest['errors'] !== []) {
            throw new RuntimeException('Rollback plan contains errors: '.implode('; ', $liveManifest['errors']));
        }

        if ($liveManifest['blockers'] !== []) {
            throw new RuntimeException('Strict rollback blocked: '.implode('; ', $liveManifest['blockers']));
        }

        return DB::transaction(function () use ($liveManifest, $manifestFingerprint, $startedAt, $endedAt, $scope, $runIds, $controlUserId, $reportPath): array {
            $walletIds = collect($liveManifest['operations'])->pluck('wallet_replays')->flatten(1)->pluck('wallet_id')->unique()->values()->all();
            Wallet::query()->whereIn('id', $walletIds)->orderBy('id')->lockForUpdate()->get();
            User::query()->whereIn('id', $liveManifest['user_ids'])->orderBy('id')->lockForUpdate()->get();
            BinaryBonusRun::query()->whereIn('id', data_get($liveManifest, 'run_ids.targeted', []))->orderBy('id')->lockForUpdate()->get();
            BonusTransaction::query()->whereIn('id', [
                ...data_get($liveManifest, 'bonus_transaction_ids.restore', []),
                ...data_get($liveManifest, 'bonus_transaction_ids.delete', []),
            ])->orderBy('id')->lockForUpdate()->get();

            $lockedManifest = $this->discover($startedAt, $endedAt, $scope, $runIds, $controlUserId);

            if (! hash_equals($manifestFingerprint, (string) $lockedManifest['fingerprint'])) {
                throw new RuntimeException('Fingerprint mismatch while acquiring rollback locks. No changes were made.');
            }

            if ($lockedManifest['blockers'] !== [] || $lockedManifest['errors'] !== []) {
                throw new RuntimeException('Strict rollback became unsafe while acquiring locks. No changes were made.');
            }

            $this->applyWalletReplays($lockedManifest['operations']);
            $this->applyPvDeltas($lockedManifest['operations']);

            foreach ($lockedManifest['operations'] as $operation) {
                if ($operation['operation_type'] === 'restore_existing_run') {
                    $this->restoreHistoricalOperation($operation);
                } else {
                    $this->deleteNewRunOperation($operation);
                }
            }

            AdminActionLog::query()->create([
                'admin_id' => null,
                'target_user_id' => null,
                'action' => 'binary_recalculation_batch_rollback',
                'reason' => 'Approved strict binary recalculation batch rollback completed',
                'metadata' => [
                    'batch_fingerprint' => $manifestFingerprint,
                    'executed_at' => now('UTC')->toISOString(),
                    'executed_by' => 'artisan:'.get_current_user(),
                    'scope' => $scope,
                    'run_ids' => data_get($lockedManifest, 'run_ids.targeted', []),
                    'bonus_transaction_ids' => $lockedManifest['bonus_transaction_ids'],
                    'wallet_transaction_ids' => $lockedManifest['wallet_transaction_ids'],
                    'calculation_ids' => $lockedManifest['calculation_ids'],
                    'report_path' => $reportPath,
                    'result' => 'completed',
                ],
            ]);

            return [
                'already_rolled_back' => false,
                'message' => 'Binary recalculation batch rollback completed.',
                'fingerprint' => $manifestFingerprint,
                'scope' => $scope,
                'affected_ids' => [
                    'runs' => data_get($lockedManifest, 'run_ids.targeted', []),
                    'bonus_transactions' => $lockedManifest['bonus_transaction_ids'],
                    'wallet_transactions' => $lockedManifest['wallet_transaction_ids'],
                    'calculations' => $lockedManifest['calculation_ids'],
                    'users' => $lockedManifest['user_ids'],
                ],
                'after' => $this->afterSnapshot($lockedManifest),
            ];
        }, 1);
    }

    private function isHistoricalRun(
        BinaryBonusRun $run,
        CarbonImmutable $startedAtUtc,
        CarbonImmutable $endedAtUtc,
        CarbonImmutable $startedAtLocal,
        CarbonImmutable $endedAtLocal,
    ): bool {
        if (! $run->created_at?->lt($startedAtLocal)
            || ! $run->updated_at?->gte($startedAtLocal)
            || ! $run->updated_at?->lt($endedAtLocal)) {
            return false;
        }

        $manual = data_get($run->metadata, 'manual_recalculation');

        if (! is_array($manual) || ($manual['source'] ?? null) !== 'binary_recalculation') {
            return false;
        }

        return $this->metadataTimestampInWindow($manual['recalculated_at'] ?? null, $startedAtUtc, $endedAtUtc);
    }

    private function isNewRun(BinaryBonusRun $run, CarbonImmutable $startedAtLocal, CarbonImmutable $endedAtLocal): bool
    {
        if (! $run->created_at?->gte($startedAtLocal) || ! $run->created_at?->lt($endedAtLocal)) {
            return false;
        }

        $bonus = $run->bonusTransaction;

        if (! $bonus || $bonus->bonus_type !== 'binary' || ! $this->modelCreatedInWindow($bonus, $startedAtLocal, $endedAtLocal)) {
            return false;
        }

        $walletTransactions = $this->linkedWalletTransactions($bonus)->filter(
            fn (WalletTransaction $transaction): bool => in_array($transaction->type, ['binary_bonus_main', 'binary_bonus_deposit'], true)
                && $this->modelCreatedInWindow($transaction, $startedAtLocal, $endedAtLocal)
        );
        $bonusSourceMatches = data_get($bonus->metadata, 'bulk_recalculation.source') === 'scheduler'
            || data_get($bonus->metadata, 'source') === 'scheduled_binary_calculation';

        return $bonusSourceMatches
            && $walletTransactions->where('type', 'binary_bonus_main')->count() === 1
            && $walletTransactions->where('type', 'binary_bonus_deposit')->count() === 1
            && $walletTransactions->every(fn (WalletTransaction $transaction): bool => $transaction->user_id === $run->user_id)
            && $walletTransactions->every(fn (WalletTransaction $transaction): bool => in_array(data_get($transaction->metadata, 'source'), ['binary_bonus', 'scheduled_binary_calculation'], true))
            && $run->calculations->contains(fn (BinaryBonusCalculation $calculation): bool => $this->modelCreatedInWindow($calculation, $startedAtLocal, $endedAtLocal));
    }

    /** @return array<string, mixed> */
    private function historicalOperation(BinaryBonusRun $run, CarbonImmutable $startedAtLocal, CarbonImmutable $endedAtLocal): array
    {
        $blockers = [];
        $bonus = $run->bonusTransaction;
        $sourceCalculations = $run->calculations->filter(fn (BinaryBonusCalculation $calculation): bool => $calculation->created_at?->lt($startedAtLocal))->sortByDesc('created_at')->values();
        $batchCalculations = $run->calculations->filter(function (BinaryBonusCalculation $calculation) use ($startedAtLocal, $endedAtLocal): bool {
            return $this->modelCreatedInWindow($calculation, $startedAtLocal, $endedAtLocal)
                && data_get($calculation->metadata, 'source') === 'manual_binary_recalculation'
                && data_get($calculation->metadata, 'recalculation.source') === 'binary_recalculation';
        })->values();
        $sourceCalculation = $sourceCalculations->first();

        if (! $bonus) {
            $blockers[] = "Run {$run->id}: linked bonus transaction is missing.";
        }

        if (! $sourceCalculation) {
            $blockers[] = "Run {$run->id}: immutable pre-batch calculation is missing.";
        }

        if ($batchCalculations->count() !== 1) {
            $blockers[] = "Run {$run->id}: expected exactly one batch calculation, found {$batchCalculations->count()}.";
        }

        $adjustments = $bonus ? $this->linkedWalletTransactions($bonus)->filter(function (WalletTransaction $transaction) use ($run, $startedAtLocal, $endedAtLocal): bool {
            return in_array($transaction->type, ['binary_bonus_main_adjustment', 'binary_bonus_deposit_adjustment'], true)
                && data_get($transaction->metadata, 'source') === 'binary_recalculation'
                && (int) data_get($transaction->metadata, 'binary_bonus_run_id') === $run->id
                && $this->modelCreatedInWindow($transaction, $startedAtLocal, $endedAtLocal);
        })->sortBy('id')->values() : collect();
        $manual = (array) data_get($run->metadata, 'manual_recalculation', []);

        foreach ([
            'main' => ['amount' => 'adjustment_main', 'transaction' => 'main_adjustment_transaction_id', 'type' => 'binary_bonus_main_adjustment'],
            'deposit' => ['amount' => 'adjustment_deposit', 'transaction' => 'deposit_adjustment_transaction_id', 'type' => 'binary_bonus_deposit_adjustment'],
        ] as $part => $keys) {
            $expectedAdjustment = (string) ($manual[$keys['amount']] ?? '0.00');
            $partTransactions = $adjustments->where('type', $keys['type']);

            if (bccomp($expectedAdjustment, '0', 2) === 0 && $partTransactions->isNotEmpty()) {
                $blockers[] = "Run {$run->id}: unexpected {$part} adjustment transaction exists.";
            }

            if (bccomp($expectedAdjustment, '0', 2) !== 0) {
                $expectedId = (int) ($manual[$keys['transaction']] ?? 0);

                if ($partTransactions->count() !== 1 || (int) $partTransactions->first()?->id !== $expectedId) {
                    $blockers[] = "Run {$run->id}: {$part} adjustment link does not match recalculation metadata.";
                }
            }
        }
        $originalWalletTransactions = $bonus ? $this->linkedWalletTransactions($bonus)->filter(function (WalletTransaction $transaction) use ($startedAtLocal): bool {
            return in_array($transaction->type, ['binary_bonus_main', 'binary_bonus_deposit'], true)
                && $transaction->created_at?->lt($startedAtLocal);
        })->sortBy('id')->values() : collect();

        if ($originalWalletTransactions->where('type', 'binary_bonus_main')->count() !== 1
            || $originalWalletTransactions->where('type', 'binary_bonus_deposit')->count() !== 1) {
            $blockers[] = "Run {$run->id}: original binary wallet payout links are inconsistent.";
        }

        $restoreRun = $sourceCalculation ? $this->restoredRunValues($run, $sourceCalculation) : [];
        $restoreBonus = ($sourceCalculation && $bonus)
            ? $this->restoredBonusValues($run, $bonus, $sourceCalculation, $originalWalletTransactions)
            : [];
        $pvDeltaLeft = $sourceCalculation ? bcsub((string) $run->used_left_pv, (string) $sourceCalculation->used_left_pv, 2) : '0.00';
        $pvDeltaRight = $sourceCalculation ? bcsub((string) $run->used_right_pv, (string) $sourceCalculation->used_right_pv, 2) : '0.00';

        return [
            'operation_type' => 'restore_existing_run',
            'user_id' => (int) $run->user_id,
            'email' => (string) ($run->user?->email ?? ''),
            'run_id' => (int) $run->id,
            'bonus_transaction_id' => $bonus?->id,
            'wallet_transaction_ids' => $adjustments->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'source_calculation_ids' => $sourceCalculations->take(1)->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'batch_calculation_ids' => $batchCalculations->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'related_pv_transaction_ids' => [],
            'notification_ids' => [],
            'before' => [
                'run' => $this->modelSnapshot($run),
                'bonus_transaction' => $bonus ? $this->modelSnapshot($bonus) : null,
                'user_remaining_pv' => $this->userPvSnapshot($run->user),
            ],
            'expected_after' => [
                'run' => $restoreRun,
                'bonus_transaction' => $restoreBonus,
                'remaining_left_pv_delta' => $pvDeltaLeft,
                'remaining_right_pv_delta' => $pvDeltaRight,
            ],
            'financial' => $this->financialSummary($adjustments),
            'wallet_replays' => [],
            'later_transaction_ids' => [],
            'later_financial_transactions_count' => 0,
            'can_rollback' => $blockers === [],
            'blocker_reason' => $blockers === [] ? null : implode('; ', $blockers),
            'blockers' => $blockers,
        ];
    }

    /** @return array<string, mixed> */
    private function newRunOperation(BinaryBonusRun $run, CarbonImmutable $startedAtLocal, CarbonImmutable $endedAtLocal): array
    {
        $blockers = [];
        $bonus = $run->bonusTransaction;
        $payouts = $bonus ? $this->linkedWalletTransactions($bonus)->filter(function (WalletTransaction $transaction) use ($startedAtLocal, $endedAtLocal): bool {
            return in_array($transaction->type, ['binary_bonus_main', 'binary_bonus_deposit'], true)
                && $this->modelCreatedInWindow($transaction, $startedAtLocal, $endedAtLocal);
        })->sortBy('id')->values() : collect();
        $calculations = $run->calculations->filter(
            fn (BinaryBonusCalculation $calculation): bool => $this->modelCreatedInWindow($calculation, $startedAtLocal, $endedAtLocal)
        )->values();

        if (! $bonus || $bonus->bonus_type !== 'binary') {
            $blockers[] = "Run {$run->id}: linked binary bonus transaction is missing.";
        }

        if ($payouts->where('type', 'binary_bonus_main')->count() !== 1
            || $payouts->where('type', 'binary_bonus_deposit')->count() !== 1) {
            $blockers[] = "Run {$run->id}: expected exactly one linked main and deposit payout.";
        }

        if ($calculations->count() !== 1) {
            $blockers[] = "Run {$run->id}: expected exactly one linked batch calculation, found {$calculations->count()}.";
        }

        $notificationIds = $bonus ? $this->notificationIdsForBonus($bonus) : [];

        return [
            'operation_type' => 'delete_new_run',
            'user_id' => (int) $run->user_id,
            'email' => (string) ($run->user?->email ?? ''),
            'run_id' => (int) $run->id,
            'bonus_transaction_id' => $bonus?->id,
            'wallet_transaction_ids' => $payouts->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'source_calculation_ids' => [],
            'batch_calculation_ids' => $calculations->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'related_pv_transaction_ids' => [],
            'notification_ids' => $notificationIds,
            'before' => [
                'run' => $this->modelSnapshot($run),
                'bonus_transaction' => $bonus ? $this->modelSnapshot($bonus) : null,
                'user_remaining_pv' => $this->userPvSnapshot($run->user),
            ],
            'expected_after' => [
                'run' => null,
                'bonus_transaction' => null,
                'remaining_left_pv_delta' => (string) $run->used_left_pv,
                'remaining_right_pv_delta' => (string) $run->used_right_pv,
            ],
            'financial' => $this->financialSummary($payouts),
            'wallet_replays' => [],
            'later_transaction_ids' => [],
            'later_financial_transactions_count' => 0,
            'can_rollback' => $blockers === [],
            'blocker_reason' => $blockers === [] ? null : implode('; ', $blockers),
            'blockers' => $blockers,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $operations
     * @return array<int, array<string, mixed>>
     */
    private function attachWalletReplayPlans(array $operations): array
    {
        $allTargetIds = collect($operations)->flatMap(fn (array $operation): array => $operation['wallet_transaction_ids'])
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        $targets = WalletTransaction::query()->whereIn('id', $allTargetIds)->get()->keyBy('id');
        $walletPlans = [];

        foreach ($targets->groupBy('wallet_id') as $walletId => $walletTargets) {
            $walletPlans[(int) $walletId] = $this->walletReplayPlan((int) $walletId, $walletTargets->pluck('id')->map(fn ($id) => (int) $id)->all());
        }

        foreach ($operations as &$operation) {
            $operationTargetIds = collect($operation['wallet_transaction_ids'])->map(fn ($id) => (int) $id);
            $operationWalletIds = $operationTargetIds->map(fn (int $id) => (int) ($targets[$id]?->wallet_id ?? 0))->filter()->unique();
            $operation['wallet_replays'] = $operationWalletIds->map(fn (int $walletId): array => $walletPlans[$walletId])->values()->all();

            if ($operation['wallet_replays'] === []) {
                $operation['wallet_replays'] = Wallet::query()
                    ->where('user_id', $operation['user_id'])
                    ->whereIn('type', ['main', 'deposit'])
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Wallet $wallet): array => [
                        'wallet_id' => (int) $wallet->id,
                        'wallet_type' => (string) $wallet->type,
                        'target_transaction_ids' => [],
                        'later_transaction_ids' => [],
                        'current_balance' => (string) $wallet->balance,
                        'expected_balance' => (string) $wallet->balance,
                        'updates' => [],
                        'blockers' => [],
                    ])->all();
            }
            $laterIds = collect($operation['wallet_replays'])->flatMap(fn (array $plan): array => $plan['later_transaction_ids'])->unique()->sort()->values()->all();
            $operation['later_transaction_ids'] = $laterIds;
            $operation['later_financial_transactions_count'] = count($laterIds);

            foreach ($operation['wallet_replays'] as $plan) {
                foreach ($plan['blockers'] as $blocker) {
                    $operation['blockers'][] = $blocker;
                }
            }

            $operation['blockers'] = array_values(array_unique($operation['blockers']));
            $operation['can_rollback'] = $operation['blockers'] === [];
            $operation['blocker_reason'] = $operation['can_rollback'] ? null : implode('; ', $operation['blockers']);
            $operation['current_main_balance'] = $this->walletBalanceFromPlans($operation['wallet_replays'], 'main', 'current_balance');
            $operation['current_deposit_balance'] = $this->walletBalanceFromPlans($operation['wallet_replays'], 'deposit', 'current_balance');
            $operation['expected_main_after_rollback'] = $this->walletBalanceFromPlans($operation['wallet_replays'], 'main', 'expected_balance');
            $operation['expected_deposit_after_rollback'] = $this->walletBalanceFromPlans($operation['wallet_replays'], 'deposit', 'expected_balance');
        }
        unset($operation);

        $pvPlans = collect($operations)->groupBy('user_id')->map(function (Collection $userOperations, int|string $userId): array {
            $user = User::query()->find((int) $userId);
            $leftDelta = $userOperations->reduce(
                fn (string $sum, array $operation): string => bcadd($sum, (string) data_get($operation, 'expected_after.remaining_left_pv_delta', '0.00'), 2),
                '0.00',
            );
            $rightDelta = $userOperations->reduce(
                fn (string $sum, array $operation): string => bcadd($sum, (string) data_get($operation, 'expected_after.remaining_right_pv_delta', '0.00'), 2),
                '0.00',
            );

            return [
                'left' => $user ? bcadd((string) $user->remaining_left_pv, $leftDelta, 2) : null,
                'right' => $user ? bcadd((string) $user->remaining_right_pv, $rightDelta, 2) : null,
            ];
        });

        foreach ($operations as &$operation) {
            $pvPlan = $pvPlans[$operation['user_id']];
            $operation['expected_after']['remaining_left_pv'] = $pvPlan['left'];
            $operation['expected_after']['remaining_right_pv'] = $pvPlan['right'];

            if ($pvPlan['left'] === null || $pvPlan['right'] === null) {
                $operation['blockers'][] = "User {$operation['user_id']}: PV state cannot be resolved because the user is missing.";
            } elseif (bccomp($pvPlan['left'], '0', 2) < 0 || bccomp($pvPlan['right'], '0', 2) < 0) {
                $operation['blockers'][] = "User {$operation['user_id']}: PV rollback would produce a negative remaining PV balance.";
            }

            $operation['blockers'] = array_values(array_unique($operation['blockers']));
            $operation['can_rollback'] = $operation['blockers'] === [];
            $operation['blocker_reason'] = $operation['can_rollback'] ? null : implode('; ', $operation['blockers']);
        }
        unset($operation);

        return $operations;
    }

    /** @return array<string, mixed> */
    private function walletReplayPlan(int $walletId, array $targetIds): array
    {
        $wallet = Wallet::query()->find($walletId);
        $transactions = WalletTransaction::query()
            ->where('wallet_id', $walletId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $targetLookup = array_fill_keys($targetIds, true);
        $firstIndex = $transactions->search(fn (WalletTransaction $transaction): bool => isset($targetLookup[$transaction->id]));
        $blockers = [];

        if (! $wallet || $firstIndex === false) {
            $blockers[] = "Wallet {$walletId}: target ledger transaction is missing.";

            return [
                'wallet_id' => $walletId,
                'wallet_type' => $wallet?->type,
                'target_transaction_ids' => $targetIds,
                'later_transaction_ids' => [],
                'current_balance' => $wallet?->balance,
                'expected_balance' => null,
                'updates' => [],
                'blockers' => $blockers,
            ];
        }

        $first = $transactions[$firstIndex];
        $recordedBalance = (string) $first->balance_before;

        for ($index = $firstIndex; $index < $transactions->count(); $index++) {
            /** @var WalletTransaction $transaction */
            $transaction = $transactions[$index];
            $recordedAfter = (bool) $transaction->affects_balance
                ? match ($transaction->direction) {
                    'credit' => bcadd($recordedBalance, (string) $transaction->amount, 2),
                    'debit' => bcsub($recordedBalance, (string) $transaction->amount, 2),
                    default => $recordedBalance,
                }
            : $recordedBalance;

            if (bccomp((string) $transaction->balance_before, $recordedBalance, 2) !== 0
                || bccomp((string) $transaction->balance_after, $recordedAfter, 2) !== 0) {
                $blockers[] = "Wallet {$walletId}: ledger is inconsistent at transaction {$transaction->id}.";
            }

            $recordedBalance = $recordedAfter;
        }

        $balance = (string) $first->balance_before;
        $updates = [];
        $laterIds = [];

        for ($index = $firstIndex; $index < $transactions->count(); $index++) {
            /** @var WalletTransaction $transaction */
            $transaction = $transactions[$index];

            if (isset($targetLookup[$transaction->id])) {
                continue;
            }

            if ((bool) $transaction->affects_balance) {
                $before = $balance;
                $balance = match ($transaction->direction) {
                    'credit' => bcadd($balance, (string) $transaction->amount, 2),
                    'debit' => bcsub($balance, (string) $transaction->amount, 2),
                    default => $balance,
                };

                if (bccomp($balance, '0', 2) < 0) {
                    $blockers[] = "User {$transaction->user_id}, wallet {$wallet->type}: removing batch transactions causes a negative balance {$balance} at transaction {$transaction->id}.";
                }

                $updates[] = [
                    'id' => (int) $transaction->id,
                    'balance_before' => $before,
                    'balance_after' => $balance,
                ];
                $laterIds[] = (int) $transaction->id;
            }
        }

        if (bccomp((string) $wallet->balance, (string) optional($transactions->last())->balance_after, 2) !== 0) {
            $blockers[] = "Wallet {$walletId}: wallet.balance does not match the final ledger balance.";
        }

        if (bccomp($balance, '0', 2) < 0 && ! collect($blockers)->contains(fn (string $blocker): bool => str_contains($blocker, 'negative balance'))) {
            $blockers[] = "User {$wallet->user_id}, wallet {$wallet->type}: final balance would be {$balance}.";
        }

        return [
            'wallet_id' => (int) $wallet->id,
            'wallet_type' => (string) $wallet->type,
            'target_transaction_ids' => array_values($targetIds),
            'later_transaction_ids' => array_values(array_unique($laterIds)),
            'current_balance' => (string) $wallet->balance,
            'expected_balance' => $balance,
            'updates' => $updates,
            'blockers' => array_values(array_unique($blockers)),
        ];
    }

    /** @param array<int, array<string, mixed>> $operations */
    private function applyWalletReplays(array $operations): void
    {
        $plans = collect($operations)->flatMap(fn (array $operation): array => $operation['wallet_replays'])->keyBy('wallet_id');

        foreach ($plans as $plan) {
            foreach ($plan['updates'] as $update) {
                WalletTransaction::query()->whereKey($update['id'])->update([
                    'balance_before' => $update['balance_before'],
                    'balance_after' => $update['balance_after'],
                ]);
            }

            Wallet::query()->whereKey($plan['wallet_id'])->update(['balance' => $plan['expected_balance']]);
            WalletTransaction::query()->whereIn('id', $plan['target_transaction_ids'])->delete();
        }
    }

    /** @param array<int, array<string, mixed>> $operations */
    private function applyPvDeltas(array $operations): void
    {
        $deltas = collect($operations)->groupBy('user_id')->map(function (Collection $userOperations): array {
            return [
                'left' => $userOperations->reduce(
                    fn (string $sum, array $operation): string => bcadd($sum, (string) data_get($operation, 'expected_after.remaining_left_pv_delta', '0.00'), 2),
                    '0.00',
                ),
                'right' => $userOperations->reduce(
                    fn (string $sum, array $operation): string => bcadd($sum, (string) data_get($operation, 'expected_after.remaining_right_pv_delta', '0.00'), 2),
                    '0.00',
                ),
            ];
        });

        foreach ($deltas as $userId => $delta) {
            $user = User::query()->lockForUpdate()->findOrFail((int) $userId);
            $left = bcadd((string) $user->remaining_left_pv, $delta['left'], 2);
            $right = bcadd((string) $user->remaining_right_pv, $delta['right'], 2);

            if (bccomp($left, '0', 2) < 0 || bccomp($right, '0', 2) < 0) {
                throw new RuntimeException("User {$userId}: PV rollback would produce a negative remaining PV balance.");
            }

            $user->forceFill(['remaining_left_pv' => $left, 'remaining_right_pv' => $right])->save();
        }
    }

    /** @param array<string, mixed> $operation */
    private function restoreHistoricalOperation(array $operation): void
    {
        $run = BinaryBonusRun::query()->findOrFail($operation['run_id']);
        $bonus = BonusTransaction::query()->findOrFail($operation['bonus_transaction_id']);
        $run->timestamps = false;
        $bonus->timestamps = false;
        $run->forceFill($operation['expected_after']['run'])->save();
        $bonus->forceFill($operation['expected_after']['bonus_transaction'])->save();
        BinaryBonusCalculation::query()->whereIn('id', $operation['batch_calculation_ids'])->delete();
    }

    /** @param array<string, mixed> $operation */
    private function deleteNewRunOperation(array $operation): void
    {
        DB::table('notifications')->whereIn('id', $operation['notification_ids'])->delete();
        BinaryBonusCalculation::query()->whereIn('id', $operation['batch_calculation_ids'])->delete();
        BinaryBonusRun::query()->whereKey($operation['run_id'])->delete();
        BonusTransaction::query()->whereKey($operation['bonus_transaction_id'])->delete();
    }

    /** @return array<string, mixed> */
    private function restoredRunValues(BinaryBonusRun $run, BinaryBonusCalculation $calculation): array
    {
        $metadata = is_array($run->metadata) ? $run->metadata : [];
        unset($metadata['manual_recalculation']);
        $metadata['pv_money_rate'] = (string) data_get($calculation->metadata, 'pv_money_rate', data_get($metadata, 'pv_money_rate', '500'));
        $metadata['binary_percent'] = (string) $calculation->binary_percent;
        $metadata['diagnostics'] = data_get($calculation->metadata, 'diagnostics', []);

        return [
            'status' => 'completed',
            'period_start' => $run->getRawOriginal('period_start'),
            'period_end' => $run->getRawOriginal('period_end'),
            'weak_leg_pv' => (string) $calculation->weak_leg_pv,
            'used_left_pv' => (string) $calculation->used_left_pv,
            'used_right_pv' => (string) $calculation->used_right_pv,
            'carry_left_pv' => (string) $calculation->carry_left_pv,
            'carry_right_pv' => (string) $calculation->carry_right_pv,
            'amount' => (string) $calculation->bonus_amount,
            'pending_amount' => '0.00',
            'metadata' => $metadata,
            'updated_at' => $calculation->getRawOriginal('created_at'),
        ];
    }

    /** @return array<string, mixed> */
    private function restoredBonusValues(
        BinaryBonusRun $run,
        BonusTransaction $bonus,
        BinaryBonusCalculation $calculation,
        Collection $originalWalletTransactions,
    ): array {
        $metadata = is_array($bonus->metadata) ? $bonus->metadata : [];
        unset($metadata['recalculation'], $metadata['bulk_recalculation']);
        $mainTransaction = $originalWalletTransactions->firstWhere('type', 'binary_bonus_main');
        $depositTransaction = $originalWalletTransactions->firstWhere('type', 'binary_bonus_deposit');
        $metadata = array_merge($metadata, [
            'base_pv' => (string) $calculation->weak_leg_pv,
            'money_base_amount' => (string) $calculation->money_base_amount,
            'pv_money_rate' => (string) data_get($calculation->metadata, 'pv_money_rate', '500'),
            'binary_percent' => (string) $calculation->binary_percent,
            'main_percent' => '90.00',
            'deposit_percent' => '10.00',
            'main_amount' => (string) $calculation->main_amount,
            'deposit_amount' => (string) $calculation->deposit_amount,
            'period_start' => data_get($calculation->metadata, 'period_start'),
            'period_end' => data_get($calculation->metadata, 'period_end'),
            'used_left_pv' => (string) $calculation->used_left_pv,
            'used_right_pv' => (string) $calculation->used_right_pv,
            'carry_left_pv' => (string) $calculation->carry_left_pv,
            'carry_right_pv' => (string) $calculation->carry_right_pv,
            'remaining_left_pv_after' => (string) $calculation->carry_left_pv,
            'remaining_right_pv_after' => (string) $calculation->carry_right_pv,
            'diagnostics' => data_get($calculation->metadata, 'diagnostics', []),
            'main_wallet_transaction_id' => $mainTransaction?->id,
            'deposit_wallet_transaction_id' => $depositTransaction?->id,
        ]);

        return [
            'wallet_transaction_id' => $mainTransaction?->id,
            'amount' => (string) $calculation->bonus_amount,
            'left_pv' => (string) $calculation->left_pv,
            'right_pv' => (string) $calculation->right_pv,
            'matched_pv' => (string) $calculation->weak_leg_pv,
            'status' => 'completed',
            'metadata' => $metadata,
            'calculated_at' => $calculation->getRawOriginal('created_at'),
            'updated_at' => $calculation->getRawOriginal('created_at'),
        ];
    }

    /** @return Collection<int, WalletTransaction> */
    private function linkedWalletTransactions(BonusTransaction $bonus): Collection
    {
        return WalletTransaction::query()
            ->where('source_type', BonusTransaction::class)
            ->where('source_id', $bonus->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /** @return array{main: string, deposit: string, total: string} */
    private function financialSummary(Collection $transactions): array
    {
        $main = '0.00';
        $deposit = '0.00';

        foreach ($transactions as $transaction) {
            $signed = $transaction->direction === 'debit'
                ? bcmul((string) $transaction->amount, '-1', 2)
                : (string) $transaction->amount;

            if (str_contains($transaction->type, 'deposit') || $transaction->wallet?->type === 'deposit') {
                $deposit = bcadd($deposit, $signed, 2);
            } else {
                $main = bcadd($main, $signed, 2);
            }
        }

        return ['main' => $main, 'deposit' => $deposit, 'total' => bcadd($main, $deposit, 2)];
    }

    /** @return array<string, mixed> */
    private function totals(array $operations, Collection $historicalRuns, Collection $newRuns, array $blockers, array $inconsistencies, array $errors): array
    {
        $collection = collect($operations);
        $historical = $collection->where('operation_type', 'restore_existing_run');
        $new = $collection->where('operation_type', 'delete_new_run');
        $adjustmentMain = $historical->reduce(fn (string $sum, array $operation): string => bcadd($sum, $operation['financial']['main'], 2), '0.00');
        $adjustmentDeposit = $historical->reduce(fn (string $sum, array $operation): string => bcadd($sum, $operation['financial']['deposit'], 2), '0.00');
        $newMain = $new->reduce(fn (string $sum, array $operation): string => bcadd($sum, $operation['financial']['main'], 2), '0.00');
        $newDeposit = $new->reduce(fn (string $sum, array $operation): string => bcadd($sum, $operation['financial']['deposit'], 2), '0.00');

        return [
            'existing_runs_to_restore' => $historical->count(),
            'historical_runs_with_money_adjustments' => $historical->filter(fn (array $operation): bool => $operation['wallet_transaction_ids'] !== [])->count(),
            'historical_zero_adjustment_runs' => $historical->filter(fn (array $operation): bool => $operation['wallet_transaction_ids'] === [])->count(),
            'new_runs_discovered' => $newRuns->count(),
            'new_runs_to_delete' => $new->count(),
            'bonus_transactions_to_restore' => $historical->pluck('bonus_transaction_id')->filter()->count(),
            'bonus_transactions_to_delete' => $new->pluck('bonus_transaction_id')->filter()->count(),
            'wallet_adjustments_to_delete' => $historical->sum(fn (array $operation): int => count($operation['wallet_transaction_ids'])),
            'new_wallet_payouts_to_delete' => $new->sum(fn (array $operation): int => count($operation['wallet_transaction_ids'])),
            'adjustment_main_amount_to_reverse' => $adjustmentMain,
            'adjustment_deposit_amount_to_reverse' => $adjustmentDeposit,
            'new_main_amount_to_reverse' => $newMain,
            'new_deposit_amount_to_reverse' => $newDeposit,
            'main_amount_to_reverse' => bcadd($adjustmentMain, $newMain, 2),
            'deposit_amount_to_reverse' => bcadd($adjustmentDeposit, $newDeposit, 2),
            'total_amount_to_reverse' => bcadd(bcadd($adjustmentMain, $newMain, 2), bcadd($adjustmentDeposit, $newDeposit, 2), 2),
            'affected_users' => $collection->pluck('user_id')->unique()->count(),
            'users_with_later_transactions' => $collection->filter(fn (array $operation): bool => $operation['later_financial_transactions_count'] > 0)->pluck('user_id')->unique()->count(),
            'users_with_insufficient_balance' => $collection->filter(fn (array $operation): bool => str_contains((string) $operation['blocker_reason'], 'negative balance'))->pluck('user_id')->unique()->count(),
            'unresolved_pv_state_count' => $collection->filter(fn (array $operation): bool => str_contains((string) $operation['blocker_reason'], 'PV'))->count(),
            'inconsistencies' => count($inconsistencies),
            'blockers' => count($blockers),
            'errors' => count($errors),
        ];
    }

    /** @return array<string, mixed> */
    private function stateSnapshot(array $runIds, array $bonusIds, array $walletTransactionIds, array $calculationIds, array $userIds, array $notificationIds): array
    {
        $walletIds = WalletTransaction::query()->whereIn('id', $walletTransactionIds)->pluck('wallet_id')->unique()->values()->all();
        $ledgerRows = WalletTransaction::query()
            ->whereIn('wallet_id', $walletIds)
            ->where(function ($query) use ($walletTransactionIds): void {
                foreach (WalletTransaction::query()->whereIn('id', $walletTransactionIds)->get() as $target) {
                    $query->orWhere(function ($walletQuery) use ($target): void {
                        $walletQuery->where('wallet_id', $target->wallet_id)
                            ->where(function ($position) use ($target): void {
                                $position->where('created_at', '>', $target->created_at)
                                    ->orWhere(function ($sameTime) use ($target): void {
                                        $sameTime->where('created_at', $target->created_at)->where('id', '>=', $target->id);
                                    });
                            });
                    });
                }
            })
            ->orderBy('wallet_id')->orderBy('created_at')->orderBy('id')->get();
        $linkedRows = WalletTransaction::query()
            ->where('source_type', BonusTransaction::class)
            ->whereIn('source_id', $bonusIds)
            ->get();
        $walletTransactionRows = $linkedRows
            ->merge($ledgerRows)
            ->unique('id')
            ->sortBy(fn (WalletTransaction $row): string => sprintf('%020d-%s-%020d', $row->wallet_id, $row->created_at?->format('YmdHis') ?? '', $row->id))
            ->values();

        return [
            'runs' => $this->tableSnapshots('binary_bonus_runs', $runIds),
            'bonus_transactions' => $this->tableSnapshots('bonus_transactions', $bonusIds),
            'wallet_transactions_and_later_ledger' => $walletTransactionRows->map(fn (WalletTransaction $row): array => $this->modelSnapshot($row))->all(),
            'wallets' => $this->tableSnapshots('wallets', $walletIds),
            'calculations' => $this->tableSnapshots('binary_bonus_calculations', $calculationIds),
            'pv_transactions' => [],
            'users' => $this->tableSnapshots('users', $userIds, ['id', 'email', 'remaining_left_pv', 'remaining_right_pv', 'updated_at']),
            'notifications' => $this->tableSnapshots('notifications', $notificationIds),
        ];
    }

    /** @return array<string, mixed> */
    private function afterSnapshot(array $manifest): array
    {
        return [
            'runs' => $this->tableSnapshots('binary_bonus_runs', data_get($manifest, 'run_ids.targeted', [])),
            'bonus_transactions' => $this->tableSnapshots('bonus_transactions', [
                ...data_get($manifest, 'bonus_transaction_ids.restore', []),
                ...data_get($manifest, 'bonus_transaction_ids.delete', []),
            ]),
            'wallet_transactions' => $this->tableSnapshots('wallet_transactions', [
                ...data_get($manifest, 'wallet_transaction_ids.adjustments', []),
                ...data_get($manifest, 'wallet_transaction_ids.new_payouts', []),
            ]),
            'calculations' => $this->tableSnapshots('binary_bonus_calculations', data_get($manifest, 'calculation_ids.delete', [])),
            'users' => $this->tableSnapshots('users', $manifest['user_ids'], ['id', 'email', 'remaining_left_pv', 'remaining_right_pv', 'updated_at']),
            'wallets' => $this->tableSnapshots('wallets', collect($manifest['operations'])->pluck('wallet_replays')->flatten(1)->pluck('wallet_id')->unique()->values()->all()),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function tableSnapshots(string $table, array $ids, array $columns = ['*']): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table($table)->whereIn('id', $ids)->orderBy('id')->get($columns)->map(function ($row): array {
            $values = (array) $row;

            foreach (['metadata', 'data'] as $jsonColumn) {
                if (isset($values[$jsonColumn]) && is_string($values[$jsonColumn])) {
                    $decoded = json_decode($values[$jsonColumn], true);
                    $values[$jsonColumn] = is_array($decoded) ? $decoded : $values[$jsonColumn];
                }
            }

            return $values;
        })->all();
    }

    /** @return array<string, mixed> */
    private function modelSnapshot(Model $model): array
    {
        $values = $model->getAttributes();

        foreach (['metadata', 'data'] as $jsonColumn) {
            if (isset($values[$jsonColumn]) && is_string($values[$jsonColumn])) {
                $decoded = json_decode($values[$jsonColumn], true);
                $values[$jsonColumn] = is_array($decoded) ? $decoded : $values[$jsonColumn];
            }
        }

        return $values;
    }

    /** @return array{remaining_left_pv: ?string, remaining_right_pv: ?string} */
    private function userPvSnapshot(?User $user): array
    {
        return [
            'remaining_left_pv' => $user ? (string) $user->remaining_left_pv : null,
            'remaining_right_pv' => $user ? (string) $user->remaining_right_pv : null,
        ];
    }

    private function notificationIdsForBonus(BonusTransaction $bonus): array
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $bonus->user_id)
            ->get(['id', 'data'])
            ->filter(function ($notification) use ($bonus): bool {
                $data = json_decode((string) $notification->data, true);

                return is_array($data) && (int) ($data['bonus_transaction_id'] ?? 0) === $bonus->id;
            })
            ->pluck('id')->values()->all();
    }

    private function controlUserResult(array $manifest, int $controlUserId): ?array
    {
        $operation = collect($manifest['operations'])->firstWhere('user_id', $controlUserId);

        if (! $operation) {
            return null;
        }

        return [
            'user_id' => $controlUserId,
            'run_id' => $operation['run_id'],
            'bonus_transaction_id' => $operation['bonus_transaction_id'],
            'wallet_transaction_ids_to_delete' => $operation['wallet_transaction_ids'],
            'later_transaction_ids_to_preserve' => $operation['later_transaction_ids'],
            'current_main_balance' => $operation['current_main_balance'],
            'current_deposit_balance' => $operation['current_deposit_balance'],
            'expected_main_after_rollback' => $operation['expected_main_after_rollback'],
            'expected_deposit_after_rollback' => $operation['expected_deposit_after_rollback'],
            'expected_bonus_amount' => data_get($operation, 'expected_after.bonus_transaction.amount'),
            'can_rollback' => $operation['can_rollback'],
            'blocker_reason' => $operation['blocker_reason'],
        ];
    }

    public function hasCompletedRollback(string $fingerprint): bool
    {
        return AdminActionLog::query()
            ->where('action', 'binary_recalculation_batch_rollback')
            ->get()
            ->contains(fn (AdminActionLog $log): bool => data_get($log->metadata, 'batch_fingerprint') === $fingerprint
                && data_get($log->metadata, 'result') === 'completed');
    }

    private function metadataTimestampInWindow(mixed $value, CarbonImmutable $startedAtUtc, CarbonImmutable $endedAtUtc): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        try {
            $timestamp = CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            return false;
        }

        return $timestamp->gte($startedAtUtc) && $timestamp->lt($endedAtUtc);
    }

    private function modelCreatedInWindow(Model $model, CarbonImmutable $startedAtLocal, CarbonImmutable $endedAtLocal): bool
    {
        return (bool) $model->created_at?->gte($startedAtLocal) && (bool) $model->created_at?->lt($endedAtLocal);
    }

    private function walletBalanceFromPlans(array $plans, string $walletType, string $key): ?string
    {
        $plan = collect($plans)->firstWhere('wallet_type', $walletType);

        return $plan ? (string) $plan[$key] : null;
    }

    private function assertScope(string $scope): void
    {
        if (! in_array($scope, [self::SCOPE_ADJUSTMENTS, self::SCOPE_ENTIRE_BATCH], true)) {
            throw new RuntimeException('scope must be adjustments-only or entire-batch.');
        }
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonicalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}

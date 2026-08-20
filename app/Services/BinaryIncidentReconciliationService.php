<?php

namespace App\Services;

use App\Models\AdminActionLog;
use App\Models\PartnerTransfer;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;

class BinaryIncidentReconciliationService
{
    /** @return array<string, mixed> */
    public function plan(array $approved, string $checksum): array
    {
        $blockers = [];
        $incident = trim((string) ($approved['incident'] ?? ''));

        if ($incident === '') {
            $blockers[] = 'Reconciliation incident identifier is required.';
        }

        $manualConfigs = $this->list($approved, 'manual_adjustments', $blockers);
        $transferConfigs = $this->list($approved, 'partner_transfers', $blockers);
        $preserveWalletIds = $this->integerList($approved, 'preserve_wallet_transaction_ids', $blockers);
        $preserveNeutralIds = $this->integerList($approved, 'preserve_non_balance_transaction_ids', $blockers);
        $manualRecords = [];
        $transferRecords = [];

        foreach ($manualConfigs as $index => $config) {
            $manualRecords[] = $this->manualAdjustmentPlan((array) $config, $index, $blockers);
        }

        foreach ($transferConfigs as $index => $config) {
            $transferRecords[] = $this->partnerTransferPlan((array) $config, $index, $blockers);
        }

        $this->rejectDuplicateIds($manualRecords, 'wallet_transaction_id', 'manual adjustment wallet transaction', $blockers);
        $this->rejectDuplicateIds($manualRecords, 'admin_action_log_id', 'manual adjustment admin action log', $blockers);
        $this->rejectDuplicateIds($transferRecords, 'partner_transfer_id', 'partner transfer', $blockers);
        $this->rejectDuplicateIds([
            ...collect($transferRecords)->map(fn (array $record): array => ['transaction_id' => $record['sender_transaction_id']])->all(),
            ...collect($transferRecords)->map(fn (array $record): array => ['transaction_id' => $record['recipient_transaction_id']])->all(),
        ], 'transaction_id', 'partner transfer wallet transaction', $blockers);

        $reversalTransactionIds = collect($manualRecords)
            ->pluck('wallet_transaction_id')
            ->merge(collect($transferRecords)->flatMap(fn (array $record): array => [
                $record['sender_transaction_id'],
                $record['recipient_transaction_id'],
            ]))
            ->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $preservedIds = collect($preserveWalletIds)->merge($preserveNeutralIds)->unique()->values();
        $overlap = $preservedIds->intersect($reversalTransactionIds)->values()->all();

        if ($overlap !== []) {
            $blockers[] = 'Preserved transactions cannot be reconciliation targets: '.implode(', ', $overlap).'.';
        }

        $preservedTransactions = [];

        foreach ($preservedIds as $id) {
            $transaction = WalletTransaction::query()->find($id);

            if (! $transaction) {
                $blockers[] = "Preserved wallet transaction {$id} does not exist.";

                continue;
            }

            if (in_array($id, $preserveNeutralIds, true) && (bool) $transaction->affects_balance) {
                $blockers[] = "Preserved non-balance transaction {$id} unexpectedly affects balance.";
            }

            if (! in_array($transaction->status, [WalletTransaction::STATUS_COMPLETED], true)) {
                $blockers[] = "Preserved wallet transaction {$id} is not completed.";
            }

            $preservedTransactions[] = $this->snapshot($transaction);
        }

        $executionOrder = $this->executionOrder($manualRecords, $transferRecords, $blockers);

        return [
            'enabled' => true,
            'incident' => $incident,
            'json_checksum' => $checksum,
            'manual_adjustments' => $manualRecords,
            'partner_transfers' => $transferRecords,
            'preserved_transactions' => $preservedTransactions,
            'preserve_wallet_transaction_ids' => $preserveWalletIds,
            'preserve_non_balance_transaction_ids' => $preserveNeutralIds,
            'reversal_wallet_transaction_ids' => $reversalTransactionIds,
            'execution_order' => $executionOrder,
            'expected_final_balances' => [],
            'user_ids' => collect($manualRecords)->pluck('user_id')
                ->merge(collect($transferRecords)->flatMap(fn (array $record): array => [
                    $record['sender_user_id'],
                    $record['recipient_user_id'],
                ]))->filter()->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all(),
            'blockers' => array_values(array_unique($blockers)),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $walletPlans
     * @return array<string, mixed>
     */
    public function withWalletPlans(array $plan, array $walletPlans): array
    {
        $plans = collect($walletPlans)->keyBy('wallet_id');
        $finalBalances = [];

        foreach ($plan['manual_adjustments'] as &$record) {
            $walletPlan = $plans->get($record['wallet_id']);
            $record['wallet_before'] = $walletPlan['current_balance'] ?? null;
            $record['wallet_after'] = $walletPlan['expected_balance'] ?? null;
            $record['can_reconcile'] = $record['blockers'] === [] && ($walletPlan['blockers'] ?? []) === [];
            $record['blocker_reason'] = $record['can_reconcile'] ? null : implode('; ', [
                ...$record['blockers'],
                ...($walletPlan['blockers'] ?? []),
            ]);
        }
        unset($record);

        foreach ($plan['partner_transfers'] as &$record) {
            $senderPlan = $plans->get($record['sender_wallet_id']);
            $recipientPlan = $plans->get($record['recipient_wallet_id']);
            $record['sender_wallet_before'] = $senderPlan['current_balance'] ?? null;
            $record['sender_wallet_after'] = $senderPlan['expected_balance'] ?? null;
            $record['recipient_wallet_before'] = $recipientPlan['current_balance'] ?? null;
            $record['recipient_wallet_after'] = $recipientPlan['expected_balance'] ?? null;
            $walletBlockers = [
                ...($senderPlan['blockers'] ?? []),
                ...($recipientPlan['blockers'] ?? []),
            ];
            $record['can_reconcile'] = $record['blockers'] === [] && $walletBlockers === [];
            $record['blocker_reason'] = $record['can_reconcile'] ? null : implode('; ', [
                ...$record['blockers'],
                ...$walletBlockers,
            ]);
        }
        unset($record);

        foreach ($walletPlans as $walletPlan) {
            $wallet = Wallet::query()->find($walletPlan['wallet_id']);

            if (! $wallet) {
                continue;
            }

            $finalBalances[] = [
                'user_id' => (int) $wallet->user_id,
                'wallet_id' => (int) $wallet->id,
                'wallet_type' => (string) $wallet->type,
                'current_balance' => (string) $walletPlan['current_balance'],
                'expected_balance' => (string) $walletPlan['expected_balance'],
            ];
        }

        $plan['expected_final_balances'] = collect($finalBalances)
            ->sortBy(fn (array $row): string => sprintf('%020d-%s', $row['user_id'], $row['wallet_type']))
            ->values()->all();

        return $plan;
    }

    public function apply(array $plan, string $fingerprint): void
    {
        foreach ($plan['execution_order'] as $step) {
            if ($step['type'] === 'manual_adjustment') {
                $record = collect($plan['manual_adjustments'])->firstWhere('wallet_transaction_id', $step['id']);
                $this->voidManualAdjustment($record, $plan['incident'], $fingerprint);

                continue;
            }

            $record = collect($plan['partner_transfers'])->firstWhere('partner_transfer_id', $step['id']);
            $this->reversePartnerTransfer($record, $plan['incident'], $fingerprint);
        }
    }

    /** @return array<string, mixed> */
    private function manualAdjustmentPlan(array $config, int $index, array &$globalBlockers): array
    {
        $blockers = [];
        $prefix = 'Manual adjustment #'.($index + 1);
        $transactionId = $this->positiveInt($config, 'wallet_transaction_id', $prefix, $blockers);
        $adminLogId = $this->positiveInt($config, 'admin_action_log_id', $prefix, $blockers);
        $userId = $this->positiveInt($config, 'user_id', $prefix, $blockers);
        $walletId = $this->positiveInt($config, 'expected_wallet_id', $prefix, $blockers);
        $amount = $this->decimal($config['expected_amount'] ?? null, 'expected_amount', $prefix, $blockers);
        $oldBalance = $this->decimal($config['expected_old_balance'] ?? null, 'expected_old_balance', $prefix, $blockers);
        $newBalance = $this->decimal($config['expected_new_balance'] ?? null, 'expected_new_balance', $prefix, $blockers);
        $transaction = $transactionId ? WalletTransaction::query()->find($transactionId) : null;
        $adminLog = $adminLogId ? AdminActionLog::query()->find($adminLogId) : null;
        $wallet = $walletId ? Wallet::query()->find($walletId) : null;

        if (! $transaction) {
            $blockers[] = "{$prefix}: wallet transaction {$transactionId} does not exist.";
        } else {
            $this->expect($transaction->user_id, $userId, "{$prefix}: transaction user mismatch.", $blockers);
            $this->expect($transaction->wallet_id, $walletId, "{$prefix}: transaction wallet mismatch.", $blockers);
            $this->expect($transaction->type, 'manual_adjustment', "{$prefix}: transaction type mismatch.", $blockers);
            $this->expect($transaction->direction, 'debit', "{$prefix}: transaction direction mismatch.", $blockers);
            $this->expectDecimal($transaction->amount, $amount, "{$prefix}: transaction amount mismatch.", $blockers);
            $this->expectDecimal($transaction->balance_before, $oldBalance, "{$prefix}: old balance mismatch.", $blockers);
            $this->expectDecimal($transaction->balance_after, $newBalance, "{$prefix}: new balance mismatch.", $blockers);
            $this->expect($transaction->status, WalletTransaction::STATUS_COMPLETED, "{$prefix}: transaction is not completed.", $blockers);
            $this->expect((bool) $transaction->affects_balance, true, "{$prefix}: transaction does not affect balance.", $blockers);
            $this->expect(data_get($transaction->metadata, 'mode'), 'set', "{$prefix}: transaction mode mismatch.", $blockers);
        }

        if (! $wallet) {
            $blockers[] = "{$prefix}: wallet {$walletId} does not exist.";
        } else {
            $this->expect($wallet->user_id, $userId, "{$prefix}: wallet owner mismatch.", $blockers);
            $this->expect($wallet->type, 'main', "{$prefix}: expected a main wallet.", $blockers);
        }

        if (! $adminLog) {
            $blockers[] = "{$prefix}: admin action log {$adminLogId} does not exist.";
        } else {
            $this->expect($adminLog->action, 'balance_update', "{$prefix}: admin action mismatch.", $blockers);
            $this->expect($adminLog->target_user_id, $userId, "{$prefix}: admin log target user mismatch.", $blockers);
            $this->expect(data_get($adminLog->metadata, 'wallet_id'), $walletId, "{$prefix}: admin log wallet mismatch.", $blockers);
            $this->expect(data_get($adminLog->metadata, 'wallet_transaction_id'), $transactionId, "{$prefix}: admin log transaction mismatch.", $blockers);
            $this->expect(data_get($adminLog->metadata, 'mode'), 'set', "{$prefix}: admin log mode mismatch.", $blockers);
            $this->expectDecimal(data_get($adminLog->metadata, 'old_balance'), $oldBalance, "{$prefix}: admin log old balance mismatch.", $blockers);
            $this->expectDecimal(data_get($adminLog->metadata, 'new_balance'), $newBalance, "{$prefix}: admin log new balance mismatch.", $blockers);

            if ($transaction) {
                $this->expect($adminLog->admin_id, data_get($transaction->metadata, 'admin_id'), "{$prefix}: admin identity mismatch.", $blockers);
            }
        }

        $globalBlockers = [...$globalBlockers, ...$blockers];

        return [
            'wallet_transaction_id' => $transactionId,
            'admin_action_log_id' => $adminLogId,
            'user_id' => $userId,
            'wallet_id' => $walletId,
            'amount' => $amount,
            'expected_old_balance' => $oldBalance,
            'expected_new_balance' => $newBalance,
            'current_status' => $transaction?->status,
            'expected_status' => WalletTransaction::STATUS_VOIDED,
            'wallet_transaction' => $transaction ? $this->snapshot($transaction) : null,
            'admin_action_log' => $adminLog ? $this->snapshot($adminLog) : null,
            'wallet' => $wallet ? $this->snapshot($wallet) : null,
            'wallet_before' => $wallet ? (string) $wallet->balance : null,
            'wallet_after' => null,
            'can_reconcile' => $blockers === [],
            'blocker_reason' => $blockers === [] ? null : implode('; ', $blockers),
            'blockers' => $blockers,
        ];
    }

    /** @return array<string, mixed> */
    private function partnerTransferPlan(array $config, int $index, array &$globalBlockers): array
    {
        $blockers = [];
        $prefix = 'Partner transfer #'.($index + 1);
        $transferId = $this->positiveInt($config, 'partner_transfer_id', $prefix, $blockers);
        $senderId = $this->positiveInt($config, 'sender_user_id', $prefix, $blockers);
        $recipientId = $this->positiveInt($config, 'recipient_user_id', $prefix, $blockers);
        $senderTransactionId = $this->positiveInt($config, 'sender_transaction_id', $prefix, $blockers);
        $recipientTransactionId = $this->positiveInt($config, 'recipient_transaction_id', $prefix, $blockers);
        $amount = $this->decimal($config['amount'] ?? null, 'amount', $prefix, $blockers);
        $transfer = $transferId ? PartnerTransfer::query()->find($transferId) : null;
        $senderTransaction = $senderTransactionId ? WalletTransaction::query()->find($senderTransactionId) : null;
        $recipientTransaction = $recipientTransactionId ? WalletTransaction::query()->find($recipientTransactionId) : null;
        $senderWallet = $senderTransaction ? Wallet::query()->find($senderTransaction->wallet_id) : null;
        $recipientWallet = $recipientTransaction ? Wallet::query()->find($recipientTransaction->wallet_id) : null;

        if (! $transfer) {
            $blockers[] = "{$prefix}: partner transfer {$transferId} does not exist.";
        } else {
            $this->expect($transfer->sender_user_id, $senderId, "{$prefix}: sender mismatch.", $blockers);
            $this->expect($transfer->recipient_user_id, $recipientId, "{$prefix}: recipient mismatch.", $blockers);
            $this->expectDecimal($transfer->amount, $amount, "{$prefix}: amount mismatch.", $blockers);
            $this->expect($transfer->sender_transaction_id, $senderTransactionId, "{$prefix}: sender transaction link mismatch.", $blockers);
            $this->expect($transfer->recipient_transaction_id, $recipientTransactionId, "{$prefix}: recipient transaction link mismatch.", $blockers);
            $this->expect($transfer->status, 'completed', "{$prefix}: transfer is not completed.", $blockers);
        }

        $this->validateTransferTransaction($senderTransaction, $transferId, $senderId, $amount, 'partner_transfer_out', 'debit', $prefix.' sender', $blockers);
        $this->validateTransferTransaction($recipientTransaction, $transferId, $recipientId, $amount, 'partner_transfer_in', 'credit', $prefix.' recipient', $blockers);

        if ($senderWallet) {
            $this->expect($senderWallet->user_id, $senderId, "{$prefix}: sender wallet owner mismatch.", $blockers);
            $this->expect($senderWallet->type, 'main', "{$prefix}: sender wallet is not main.", $blockers);
        }

        if ($recipientWallet) {
            $this->expect($recipientWallet->user_id, $recipientId, "{$prefix}: recipient wallet owner mismatch.", $blockers);
            $this->expect($recipientWallet->type, 'main', "{$prefix}: recipient wallet is not main.", $blockers);
        }

        $globalBlockers = [...$globalBlockers, ...$blockers];

        return [
            'partner_transfer_id' => $transferId,
            'sender_user_id' => $senderId,
            'recipient_user_id' => $recipientId,
            'amount' => $amount,
            'sender_transaction_id' => $senderTransactionId,
            'recipient_transaction_id' => $recipientTransactionId,
            'sender_wallet_id' => $senderTransaction?->wallet_id,
            'recipient_wallet_id' => $recipientTransaction?->wallet_id,
            'current_status' => $transfer?->status,
            'expected_status' => 'reversed',
            'partner_transfer' => $transfer ? $this->snapshot($transfer) : null,
            'sender_transaction' => $senderTransaction ? $this->snapshot($senderTransaction) : null,
            'recipient_transaction' => $recipientTransaction ? $this->snapshot($recipientTransaction) : null,
            'sender_wallet' => $senderWallet ? $this->snapshot($senderWallet) : null,
            'recipient_wallet' => $recipientWallet ? $this->snapshot($recipientWallet) : null,
            'can_reconcile' => $blockers === [],
            'blocker_reason' => $blockers === [] ? null : implode('; ', $blockers),
            'blockers' => $blockers,
        ];
    }

    private function validateTransferTransaction(?WalletTransaction $transaction, int $transferId, int $userId, string $amount, string $type, string $direction, string $prefix, array &$blockers): void
    {
        if (! $transaction) {
            $blockers[] = "{$prefix}: linked wallet transaction is missing.";

            return;
        }

        $this->expect($transaction->user_id, $userId, "{$prefix}: transaction user mismatch.", $blockers);
        $this->expect($transaction->type, $type, "{$prefix}: transaction type mismatch.", $blockers);
        $this->expect($transaction->direction, $direction, "{$prefix}: transaction direction mismatch.", $blockers);
        $this->expectDecimal($transaction->amount, $amount, "{$prefix}: transaction amount mismatch.", $blockers);
        $this->expect($transaction->source_type, PartnerTransfer::class, "{$prefix}: source type mismatch.", $blockers);
        $this->expect($transaction->source_id, $transferId, "{$prefix}: source ID mismatch.", $blockers);
        $this->expect($transaction->status, WalletTransaction::STATUS_COMPLETED, "{$prefix}: transaction is not completed.", $blockers);
        $this->expect((bool) $transaction->affects_balance, true, "{$prefix}: transaction does not affect balance.", $blockers);
    }

    /** @return array<int, array{type: string, id: int}> */
    private function executionOrder(array $manualRecords, array $transferRecords, array &$blockers): array
    {
        $order = [];
        $seenTransfers = [];
        $incoming = collect($transferRecords)->groupBy('recipient_user_id');

        foreach ($manualRecords as $manual) {
            if (! $manual['wallet_transaction_id']) {
                continue;
            }

            $order[] = ['type' => 'manual_adjustment', 'id' => (int) $manual['wallet_transaction_id']];
            $this->appendIncomingTransfers((int) $manual['user_id'], $incoming, $seenTransfers, $order);
        }

        $unreachable = collect($transferRecords)->pluck('partner_transfer_id')->filter()
            ->diff(array_keys($seenTransfers))->values()->all();

        if ($unreachable !== []) {
            $blockers[] = 'Partner transfers are not connected to an approved manual-adjustment endpoint: '.implode(', ', $unreachable).'.';
        }

        return $order;
    }

    private function appendIncomingTransfers(int $recipientUserId, Collection $incoming, array &$seen, array &$order): void
    {
        $records = collect($incoming->get($recipientUserId, []))->sortByDesc('partner_transfer_id');

        foreach ($records as $record) {
            $transferId = (int) $record['partner_transfer_id'];

            if (isset($seen[$transferId])) {
                continue;
            }

            $seen[$transferId] = true;
            $order[] = ['type' => 'partner_transfer', 'id' => $transferId];
            $this->appendIncomingTransfers((int) $record['sender_user_id'], $incoming, $seen, $order);
        }
    }

    private function voidManualAdjustment(array $record, string $incident, string $fingerprint): void
    {
        $transaction = WalletTransaction::query()->lockForUpdate()->findOrFail($record['wallet_transaction_id']);
        $metadata = $transaction->metadata ?: [];
        $metadata['voided_by'] = data_get($record, 'admin_action_log.admin_id');
        $metadata['voided_at'] = now('UTC')->toISOString();
        $metadata['void_reason'] = 'Reconciled as part of '.$incident;
        $metadata['void_reverse_delta'] = (string) $transaction->amount;
        $metadata['previous_status'] = $transaction->status;
        $metadata['incident_reconciliation'] = [
            'incident' => $incident,
            'rollback_manifest_fingerprint' => $fingerprint,
        ];
        $transaction->forceFill([
            'status' => WalletTransaction::STATUS_VOIDED,
            'affects_balance' => false,
            'metadata' => $metadata,
        ])->save();

        AdminActionLog::query()->create([
            'admin_id' => data_get($record, 'admin_action_log.admin_id'),
            'target_user_id' => $record['user_id'],
            'action' => 'binary_incident_reconciliation',
            'reason' => 'Void manual balance adjustment after confirmed incident funds were recovered',
            'metadata' => [
                'incident' => $incident,
                'original_wallet_transaction_id' => $record['wallet_transaction_id'],
                'original_admin_action_log_id' => $record['admin_action_log_id'],
                'reason' => 'Manual adjustment recovered incident funds and must be neutralized before transfer-chain reversal',
                'rollback_manifest_fingerprint' => $fingerprint,
            ],
        ]);
    }

    private function reversePartnerTransfer(array $record, string $incident, string $fingerprint): void
    {
        $transfer = PartnerTransfer::query()->lockForUpdate()->findOrFail($record['partner_transfer_id']);
        $meta = $transfer->meta ?: [];
        $meta['incident_reconciliation'] = [
            'incident' => $incident,
            'reversed_at' => now('UTC')->toISOString(),
            'reversed_by' => 'artisan:'.get_current_user(),
            'rollback_manifest_fingerprint' => $fingerprint,
            'previous_status' => $transfer->status,
        ];
        $transfer->forceFill(['status' => 'reversed', 'meta' => $meta])->save();

        foreach ([$record['sender_transaction_id'], $record['recipient_transaction_id']] as $transactionId) {
            $transaction = WalletTransaction::query()->lockForUpdate()->findOrFail($transactionId);
            $metadata = $transaction->metadata ?: [];
            $metadata['incident_reconciliation'] = [
                'incident' => $incident,
                'partner_transfer_id' => $record['partner_transfer_id'],
                'reversed_at' => now('UTC')->toISOString(),
                'rollback_manifest_fingerprint' => $fingerprint,
                'previous_status' => $transaction->status,
            ];
            $transaction->forceFill([
                'status' => 'reversed',
                'affects_balance' => false,
                'metadata' => $metadata,
            ])->save();
        }
    }

    private function expect(mixed $actual, mixed $expected, string $message, array &$blockers): void
    {
        if ((string) $actual !== (string) $expected) {
            $blockers[] = $message;
        }
    }

    private function expectDecimal(mixed $actual, string $expected, string $message, array &$blockers): void
    {
        if (bccomp((string) $actual, $expected, 2) !== 0) {
            $blockers[] = $message;
        }
    }

    private function positiveInt(array $config, string $key, string $prefix, array &$blockers): int
    {
        $value = filter_var($config[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($value === false) {
            $blockers[] = "{$prefix}: {$key} must be a positive integer.";

            return 0;
        }

        return (int) $value;
    }

    private function decimal(mixed $value, string $key, string $prefix, array &$blockers): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            $blockers[] = "{$prefix}: {$key} must be a decimal value.";

            return '0.00';
        }

        $formatted = number_format((float) $value, 2, '.', '');

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', (string) $value)) {
            $blockers[] = "{$prefix}: {$key} is not a valid decimal value.";
        }

        return $formatted;
    }

    private function list(array $approved, string $key, array &$blockers): array
    {
        $value = $approved[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            $blockers[] = "Reconciliation {$key} must be a non-empty JSON array.";

            return [];
        }

        return $value;
    }

    private function integerList(array $approved, string $key, array &$blockers): array
    {
        $value = $approved[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            $blockers[] = "Reconciliation {$key} must be a JSON array.";

            return [];
        }

        $ids = [];

        foreach ($value as $id) {
            $validated = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($validated === false) {
                $blockers[] = "Reconciliation {$key} contains an invalid ID.";

                continue;
            }

            $ids[] = (int) $validated;
        }

        if (count($ids) !== count(array_unique($ids))) {
            $blockers[] = "Reconciliation {$key} contains duplicate IDs.";
        }

        return array_values(array_unique($ids));
    }

    private function rejectDuplicateIds(array $records, string $key, string $label, array &$blockers): void
    {
        $ids = collect($records)->pluck($key)->filter()->map(fn ($id): int => (int) $id);
        $duplicates = $ids->duplicates()->unique()->values()->all();

        if ($duplicates !== []) {
            $blockers[] = 'Reconciliation contains duplicate '.$label.' IDs: '.implode(', ', $duplicates).'.';
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(object $model): array
    {
        $attributes = method_exists($model, 'getAttributes') ? $model->getAttributes() : (array) $model;

        foreach (['metadata', 'meta', 'data'] as $column) {
            if (isset($attributes[$column]) && is_string($attributes[$column])) {
                $decoded = json_decode($attributes[$column], true);
                $attributes[$column] = is_array($decoded) ? $decoded : $attributes[$column];
            }
        }

        return $attributes;
    }
}

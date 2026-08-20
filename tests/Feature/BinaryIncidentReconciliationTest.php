<?php

namespace Tests\Feature;

use App\Models\AdminActionLog;
use App\Models\BinaryBonusCalculation;
use App\Models\BinaryBonusRun;
use App\Models\BonusTransaction;
use App\Models\PartnerTransfer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\BinaryRecalculationRollbackService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class BinaryIncidentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_01_reconciliation_dry_run_does_not_write(): void
    {
        $fixture = $this->incidentFixture();
        $before = $this->state();
        $path = storage_path('framework/testing/reconciliation.json');
        file_put_contents($path, $fixture['raw']);

        $this->artisan('safi:binary-recalculation:rollback-batch', [
            '--started-at' => '2026-08-14 22:00:00',
            '--ended-at' => '2026-08-14 22:01:00',
            '--scope' => 'adjustments-only',
            '--reconciliation' => $path,
            '--dry-run' => true,
            '--manifest' => storage_path('framework/testing/reconciled-manifest.json'),
        ])->assertExitCode(Command::SUCCESS);

        $this->assertSame($before, $this->state());
    }

    public function test_02_missing_reconciliation_file_fails(): void
    {
        $this->artisan('safi:binary-recalculation:rollback-batch', [
            '--started-at' => '2026-08-14 22:00:00',
            '--ended-at' => '2026-08-14 22:01:00',
            '--reconciliation' => storage_path('framework/testing/missing-reconciliation.json'),
            '--dry-run' => true,
        ])->assertExitCode(Command::FAILURE);
    }

    public function test_03_modified_reconciliation_checksum_fails_force(): void
    {
        [$fixture, $manifest] = $this->plannedFixture();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum');
        app(BinaryRecalculationRollbackService::class)->execute($manifest, $manifest['fingerprint'], $fixture['config'], str_repeat('0', 64));
    }

    public function test_04_wrong_partner_transfer_amount_is_a_blocker(): void
    {
        $fixture = $this->incidentFixture();
        $fixture['config']['partner_transfers'][0]['amount'] = '13501.00';
        $manifest = $this->discover($fixture['config']);
        $this->assertStringContainsString('amount mismatch', implode(' ', $manifest['blockers']));
    }

    public function test_05_wrong_partner_transaction_id_is_a_blocker(): void
    {
        $fixture = $this->incidentFixture();
        $fixture['config']['partner_transfers'][0]['sender_transaction_id'] = 999999;
        $manifest = $this->discover($fixture['config']);
        $this->assertNotEmpty($manifest['blockers']);
    }

    public function test_06_wrong_manual_adjustment_amount_is_a_blocker(): void
    {
        $fixture = $this->incidentFixture();
        $fixture['config']['manual_adjustments'][0]['expected_amount'] = '29999.00';
        $manifest = $this->discover($fixture['config']);
        $this->assertStringContainsString('transaction amount mismatch', implode(' ', $manifest['blockers']));
    }

    public function test_07_admin_action_log_mismatch_is_a_blocker(): void
    {
        $fixture = $this->incidentFixture();
        AdminActionLog::query()->whereKey($fixture['manual_162']['log']->id)->update(['target_user_id' => 163]);
        $manifest = $this->discover($fixture['config']);
        $this->assertStringContainsString('admin log target user mismatch', implode(' ', $manifest['blockers']));
    }

    public function test_08_preserved_transaction_cannot_be_reversed(): void
    {
        $fixture = $this->incidentFixture();
        $fixture['config']['preserve_wallet_transaction_ids'][] = $fixture['transfers'][37]->sender_transaction_id;
        $manifest = $this->discover($fixture['config']);
        $this->assertStringContainsString('cannot be reconciliation targets', implode(' ', $manifest['blockers']));
    }

    public function test_09_transfer_chain_a_is_reversed_safely(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame('reversed', $fixture['transfers'][40]->refresh()->status);
        $this->assertSame('reversed', $fixture['transfers'][37]->refresh()->status);
    }

    public function test_10_transfer_chain_b_is_reversed_safely(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame('reversed', $fixture['transfers'][42]->refresh()->status);
    }

    public function test_11_user_162_ends_with_zero(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame('0.00', $fixture['wallets'][162]->refresh()->balance);
    }

    public function test_12_user_163_keeps_own_five_thousand(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame('5000.00', $fixture['wallets'][163]->refresh()->balance);
    }

    public function test_13_user_25_ends_with_zero(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame('0.00', $fixture['wallets'][25]->refresh()->balance);
    }

    public function test_14_user_51_ends_with_4599(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame('4599.00', $fixture['wallets'][51]->refresh()->balance);
    }

    public function test_15_user_53_ends_with_14500(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame('14500.00', $fixture['wallets'][53]->refresh()->balance);
    }

    public function test_16_user_31_ends_with_5001(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame('5001.00', $fixture['wallets'][31]->refresh()->balance);
    }

    public function test_17_user_28_ends_with_5500(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame('5500.00', $fixture['wallets'][28]->refresh()->balance);
    }

    public function test_18_control_user_69_ends_with_expected_wallets(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame('32600.00', $fixture['wallets'][69]->refresh()->balance);
        $this->assertSame('1400.00', $fixture['deposit_69']->refresh()->balance);
    }

    public function test_19_referral_preserved_transaction_is_unchanged(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame($fixture['preserved_snapshots']['referral_28'], $fixture['preserved']['referral_28']->refresh()->only(['type', 'direction', 'amount', 'status', 'affects_balance']));
    }

    public function test_20_later_partner_transfer_in_is_unchanged(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame($fixture['preserved_snapshots']['transfer_31'], $fixture['preserved']['transfer_31']->refresh()->only(['type', 'direction', 'amount', 'status', 'affects_balance']));
    }

    public function test_21_control_user_referral_is_unchanged(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertSame($fixture['preserved_snapshots']['referral_69'], $fixture['preserved']['referral_69']->refresh()->only(['type', 'direction', 'amount', 'status', 'affects_balance']));
    }

    public function test_22_package_assignments_are_unchanged(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertFalse($fixture['preserved']['package_162']->refresh()->affects_balance);
        $this->assertFalse($fixture['preserved']['package_163']->refresh()->affects_balance);
    }

    public function test_23_partner_transfer_history_remains_auditable(): void
    {
        [$fixture] = $this->executedFixture();
        $transfer = $fixture['transfers'][39]->refresh();
        $this->assertDatabaseHas('partner_transfers', ['id' => $transfer->id, 'status' => 'reversed']);
        $this->assertSame('binary-test-incident', data_get($transfer->meta, 'incident_reconciliation.incident'));
    }

    public function test_24_manual_adjustment_history_remains_auditable(): void
    {
        [$fixture] = $this->executedFixture();
        $transaction = $fixture['manual_162']['transaction']->refresh();
        $this->assertSame(WalletTransaction::STATUS_VOIDED, $transaction->status);
        $this->assertFalse($transaction->affects_balance);
    }

    public function test_25_original_admin_logs_remain_and_new_reconciliation_audits_are_created(): void
    {
        [$fixture] = $this->executedFixture();
        $this->assertDatabaseHas('admin_action_logs', ['id' => $fixture['manual_162']['log']->id, 'action' => 'balance_update']);
        $this->assertDatabaseHas('admin_action_logs', ['id' => $fixture['manual_163']['log']->id, 'action' => 'balance_update']);
        $this->assertSame(2, AdminActionLog::query()->where('action', 'binary_incident_reconciliation')->count());
    }

    public function test_26_valid_reconciliation_has_zero_blockers(): void
    {
        [, $manifest] = $this->plannedFixture();
        $this->assertSame([], $manifest['blockers']);
        $this->assertTrue($manifest['executable']);
    }

    public function test_27_one_database_mismatch_aborts_entire_transaction(): void
    {
        [$fixture, $manifest] = $this->plannedFixture();
        $before = $this->state();
        $fixture['transfers'][40]->forceFill(['amount' => '1.00'])->save();

        try {
            app(BinaryRecalculationRollbackService::class)->execute($manifest, $manifest['fingerprint'], $fixture['config'], $fixture['checksum']);
            $this->fail('Execution should fail after a database change.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Fingerprint mismatch', $exception->getMessage());
        }

        $this->assertDatabaseHas('wallet_transactions', ['id' => $fixture['binary'][25]['adjustment']->id, 'status' => 'completed']);
        $this->assertDatabaseHas('partner_transfers', ['id' => $fixture['transfers'][37]->id, 'status' => 'completed']);
        $this->assertNotSame($before, $this->state()); // Only the deliberate pre-execution mutation exists.
    }

    public function test_28_repeated_rollback_is_idempotent(): void
    {
        [$fixture, $manifest] = $this->executedFixture();
        $before = $this->state();
        $result = app(BinaryRecalculationRollbackService::class)->execute($manifest, $manifest['fingerprint'], $fixture['config'], $fixture['checksum']);
        $this->assertTrue($result['already_rolled_back']);
        $this->assertSame($before, $this->state());
    }

    public function test_29_replayed_ledgers_are_consistent(): void
    {
        [$fixture] = $this->executedFixture();

        foreach ($fixture['wallets'] as $wallet) {
            $this->assertLedgerConsistent($wallet->refresh());
        }
        $this->assertLedgerConsistent($fixture['deposit_69']->refresh());
    }

    public function test_30_wallet_balance_matches_final_ledger_balance(): void
    {
        [$fixture] = $this->executedFixture();

        foreach ($fixture['wallets'] as $wallet) {
            $last = $wallet->transactions()->orderBy('created_at')->orderBy('id')->get()->last();
            $this->assertSame((string) $last?->balance_after, (string) $wallet->refresh()->balance);
        }
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function plannedFixture(): array
    {
        $fixture = $this->incidentFixture();

        return [$fixture, $this->discover($fixture['config'], $fixture['checksum'])];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>} */
    private function executedFixture(): array
    {
        [$fixture, $manifest] = $this->plannedFixture();
        $result = app(BinaryRecalculationRollbackService::class)->execute(
            $manifest,
            $manifest['fingerprint'],
            $fixture['config'],
            $fixture['checksum'],
        );

        return [$fixture, $manifest, $result];
    }

    /** @return array<string, mixed> */
    private function incidentFixture(): array
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-31 10:00:00', 'Asia/Tashkent'));
        User::factory()->create(['id' => 1, 'role' => User::ROLE_SUPER_ADMIN, 'email' => 'admin@example.test']);
        $users = [];
        $wallets = [];

        foreach ([25 => '0.00', 28 => '500.00', 31 => '1.00', 51 => '4599.00', 53 => '14500.00', 69 => '15000.00', 162 => '0.00', 163 => '5000.00'] as $id => $balance) {
            $users[$id] = User::factory()->create([
                'id' => $id,
                'role' => User::ROLE_USER,
                'account_status' => 'active',
                'email' => "user{$id}@example.test",
                'remaining_left_pv' => '0.00',
                'remaining_right_pv' => '0.00',
            ]);
            app(WalletService::class)->createUserWallets($users[$id]);
            $wallets[$id] = $users[$id]->wallets()->where('type', 'main')->firstOrFail();
            $wallets[$id]->forceFill(['balance' => $balance])->save();
        }

        $binary = [
            25 => $this->binaryIncident($users[25], $wallets[25], '18000.00'),
            51 => $this->binaryIncident($users[51], $wallets[51], '9000.00'),
            53 => $this->binaryIncident($users[53], $wallets[53], '4500.00'),
            69 => $this->binaryIncident($users[69], $wallets[69], '3780.00', '12600.00', '1400.00', '420.00'),
        ];
        $deposit69 = $binary[69]['deposit_wallet'];

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 12:45:26', 'Asia/Tashkent'));
        $transfers[37] = $this->transfer(37, $users[51], $users[31], $wallets[51], $wallets[31], '13500.00');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 12:52:17', 'Asia/Tashkent'));
        $transfers[38] = $this->transfer(38, $users[53], $users[31], $wallets[53], $wallets[31], '19000.00');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 13:04:19', 'Asia/Tashkent'));
        $transfers[39] = $this->transfer(39, $users[31], $users[28], $wallets[31], $wallets[28], '30000.00');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 13:20:46', 'Asia/Tashkent'));
        $transfers[40] = $this->transfer(40, $users[28], $users[162], $wallets[28], $wallets[162], '30000.00');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 13:27:27', 'Asia/Tashkent'));
        $package162 = app(WalletService::class)->recordNonBalanceOperation($wallets[162], '60000.00', 'package_assignment');
        $referral28 = app(WalletService::class)->credit($wallets[28], '5000.00', 'referral_bonus');
        $manual162 = $this->manualAdjustment($users[162], $wallets[162], '30000.00');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 13:38:35', 'Asia/Tashkent'));
        $transfers[42] = $this->transfer(42, $users[25], $users[163], $wallets[25], $wallets[163], '18000.00');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 14:00:27', 'Asia/Tashkent'));
        $package163 = app(WalletService::class)->recordNonBalanceOperation($wallets[163], '60000.00', 'package_assignment');
        $manual163 = $this->manualAdjustment($users[163], $wallets[163], '23000.00');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-16 18:46:08', 'Asia/Tashkent'));
        $transfer31 = app(WalletService::class)->credit($wallets[31], '5000.00', 'partner_transfer_in');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-16 22:02:25', 'Asia/Tashkent'));
        $referral69 = app(WalletService::class)->credit($wallets[69], '5000.00', 'referral_bonus');

        $config = [
            'incident' => 'binary-test-incident',
            'manual_adjustments' => [
                $this->manualConfig($manual162),
                $this->manualConfig($manual163),
            ],
            'partner_transfers' => collect($transfers)->map(fn (PartnerTransfer $transfer): array => [
                'partner_transfer_id' => $transfer->id,
                'sender_user_id' => $transfer->sender_user_id,
                'recipient_user_id' => $transfer->recipient_user_id,
                'amount' => (string) $transfer->amount,
                'sender_transaction_id' => $transfer->sender_transaction_id,
                'recipient_transaction_id' => $transfer->recipient_transaction_id,
            ])->values()->all(),
            'preserve_wallet_transaction_ids' => [$referral28->id, $transfer31->id, $referral69->id],
            'preserve_non_balance_transaction_ids' => [$package162->id, $package163->id],
        ];
        $raw = json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $preserved = [
            'referral_28' => $referral28,
            'transfer_31' => $transfer31,
            'referral_69' => $referral69,
            'package_162' => $package162,
            'package_163' => $package163,
        ];

        return compact('users', 'wallets', 'binary', 'transfers', 'manual162', 'manual163', 'config', 'raw', 'preserved') + [
            'checksum' => hash('sha256', $raw),
            'deposit_69' => $deposit69,
            'manual_162' => $manual162,
            'manual_163' => $manual163,
            'preserved_snapshots' => collect($preserved)->map(fn (WalletTransaction $transaction): array => $transaction->only(['type', 'direction', 'amount', 'status', 'affects_balance']))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function binaryIncident(User $user, Wallet $mainWallet, string $mainAdjustment, string $originalMain = '0.00', string $originalDeposit = '0.00', string $depositAdjustment = '0.00'): array
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-31 10:00:00', 'Asia/Tashkent'));
        $depositWallet = $user->wallets()->where('type', 'deposit')->firstOrFail();
        $bonus = BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => 'binary',
            'amount' => bcadd($originalMain, $originalDeposit, 2),
            'left_pv' => '0.00',
            'right_pv' => '0.00',
            'matched_pv' => '0.00',
            'status' => 'completed',
            'metadata' => ['main_amount' => $originalMain, 'deposit_amount' => $originalDeposit],
            'calculated_at' => now(),
        ]);
        $run = BinaryBonusRun::query()->create([
            'user_id' => $user->id,
            'bonus_transaction_id' => $bonus->id,
            'status' => 'completed',
            'period_start' => now(),
            'period_end' => now()->addDays(15),
            'weak_leg_pv' => '0.00',
            'used_left_pv' => '0.00',
            'used_right_pv' => '0.00',
            'carry_left_pv' => '0.00',
            'carry_right_pv' => '0.00',
            'amount' => bcadd($originalMain, $originalDeposit, 2),
            'pending_amount' => '0.00',
            'metadata' => ['diagnostics' => ['snapshot' => 'original']],
        ]);
        $sourceCalculation = $this->calculation($run, $bonus, $originalMain, $originalDeposit, false);
        $mainPayout = $this->originalPayout($mainWallet, $bonus, 'binary_bonus_main', $originalMain);
        $depositPayout = $this->originalPayout($depositWallet, $bonus, 'binary_bonus_deposit', $originalDeposit);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 03:00:04', 'Asia/Tashkent'));
        $manual = [
            'source' => 'binary_recalculation',
            'binary_bonus_run_id' => $run->id,
            'old_main_amount' => $originalMain,
            'old_deposit_amount' => $originalDeposit,
            'new_main_amount' => bcadd($originalMain, $mainAdjustment, 2),
            'new_deposit_amount' => bcadd($originalDeposit, $depositAdjustment, 2),
            'adjustment_main' => $mainAdjustment,
            'adjustment_deposit' => $depositAdjustment,
            'recalculated_at' => now('UTC')->toISOString(),
            'eligible' => true,
        ];
        $adjustment = app(WalletService::class)->credit($mainWallet, $mainAdjustment, 'binary_bonus_main_adjustment', $bonus, [
            ...$manual,
            'source' => 'binary_recalculation',
            'binary_bonus_run_id' => $run->id,
        ]);
        $manual['main_adjustment_transaction_id'] = $adjustment->id;
        $depositAdjustmentTransaction = null;

        if (bccomp($depositAdjustment, '0', 2) > 0) {
            $depositAdjustmentTransaction = app(WalletService::class)->credit($depositWallet, $depositAdjustment, 'binary_bonus_deposit_adjustment', $bonus, [
                ...$manual,
                'source' => 'binary_recalculation',
                'binary_bonus_run_id' => $run->id,
            ]);
            $manual['deposit_adjustment_transaction_id'] = $depositAdjustmentTransaction->id;
        }

        $badTotal = bcadd(bcadd($originalMain, $originalDeposit, 2), bcadd($mainAdjustment, $depositAdjustment, 2), 2);
        $run->forceFill(['amount' => $badTotal, 'metadata' => ['manual_recalculation' => $manual]])->save();
        $bonus->forceFill([
            'amount' => $badTotal,
            'metadata' => [
                'main_amount' => bcadd($originalMain, $mainAdjustment, 2),
                'deposit_amount' => bcadd($originalDeposit, $depositAdjustment, 2),
                'main_wallet_transaction_id' => $mainPayout->id,
                'deposit_wallet_transaction_id' => $depositPayout->id,
                'recalculation' => $manual,
            ],
            'calculated_at' => now(),
        ])->save();
        $badCalculation = $this->calculation($run, $bonus, bcadd($originalMain, $mainAdjustment, 2), bcadd($originalDeposit, $depositAdjustment, 2), true, $manual);

        return compact('run', 'bonus', 'sourceCalculation', 'badCalculation', 'mainPayout', 'depositPayout', 'adjustment', 'depositAdjustmentTransaction') + [
            'deposit_wallet' => $depositWallet,
        ];
    }

    private function calculation(BinaryBonusRun $run, BonusTransaction $bonus, string $main, string $deposit, bool $batch, array $manual = []): BinaryBonusCalculation
    {
        return BinaryBonusCalculation::query()->create([
            'binary_bonus_run_id' => $run->id,
            'user_id' => $run->user_id,
            'bonus_transaction_id' => $bonus->id,
            'left_pv' => '0.00',
            'right_pv' => '0.00',
            'weak_leg_pv' => '0.00',
            'used_left_pv' => '0.00',
            'used_right_pv' => '0.00',
            'carry_left_pv' => '0.00',
            'carry_right_pv' => '0.00',
            'money_base_amount' => '0.00',
            'binary_percent' => '7.00',
            'bonus_amount' => bcadd($main, $deposit, 2),
            'main_amount' => $main,
            'deposit_amount' => $deposit,
            'metadata' => $batch
                ? ['source' => 'manual_binary_recalculation', 'recalculation' => $manual]
                : ['diagnostics' => ['snapshot' => 'original']],
        ]);
    }

    private function originalPayout(Wallet $wallet, BonusTransaction $bonus, string $type, string $amount): WalletTransaction
    {
        if (bccomp($amount, '0', 2) > 0) {
            return app(WalletService::class)->credit($wallet, $amount, $type, $bonus, ['source' => 'binary_bonus']);
        }

        $transaction = new WalletTransaction([
            'user_id' => $wallet->user_id,
            'type' => $type,
            'direction' => 'credit',
            'amount' => '0.00',
            'balance_before' => $wallet->balance,
            'balance_after' => $wallet->balance,
            'status' => 'completed',
            'affects_balance' => true,
            'metadata' => ['source' => 'binary_bonus'],
        ]);
        $transaction->source()->associate($bonus);
        $wallet->transactions()->save($transaction);

        return $transaction;
    }

    private function transfer(int $id, User $sender, User $recipient, Wallet $senderWallet, Wallet $recipientWallet, string $amount): PartnerTransfer
    {
        $transfer = PartnerTransfer::query()->create([
            'id' => $id,
            'uuid' => (string) Str::uuid(),
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipient->id,
            'amount' => $amount,
            'currency' => 'KZT',
            'status' => 'completed',
            'meta' => ['fixture' => true],
        ]);
        $senderTransaction = app(WalletService::class)->debit($senderWallet, $amount, 'partner_transfer_out', $transfer, ['transfer_id' => $transfer->id]);
        $recipientTransaction = app(WalletService::class)->credit($recipientWallet, $amount, 'partner_transfer_in', $transfer, ['transfer_id' => $transfer->id]);
        $transfer->forceFill([
            'sender_transaction_id' => $senderTransaction->id,
            'recipient_transaction_id' => $recipientTransaction->id,
        ])->save();

        return $transfer->refresh();
    }

    /** @return array{transaction: WalletTransaction, log: AdminActionLog} */
    private function manualAdjustment(User $user, Wallet $wallet, string $amount): array
    {
        $oldBalance = (string) $wallet->balance;
        $transaction = app(WalletService::class)->debit($wallet, $amount, 'manual_adjustment', null, [
            'mode' => 'set',
            'requested_amount' => '0.00',
            'delta' => bcmul($amount, '-1', 2),
            'admin_id' => 1,
        ]);
        $log = AdminActionLog::query()->create([
            'admin_id' => 1,
            'target_user_id' => $user->id,
            'action' => 'balance_update',
            'metadata' => [
                'mode' => 'set',
                'amount' => '0.00',
                'old_balance' => $oldBalance,
                'new_balance' => (string) $wallet->balance,
                'delta' => bcmul($amount, '-1', 2),
                'wallet_id' => $wallet->id,
                'wallet_transaction_id' => $transaction->id,
            ],
        ]);

        return compact('transaction', 'log');
    }

    private function manualConfig(array $manual): array
    {
        return [
            'wallet_transaction_id' => $manual['transaction']->id,
            'admin_action_log_id' => $manual['log']->id,
            'user_id' => $manual['transaction']->user_id,
            'expected_wallet_id' => $manual['transaction']->wallet_id,
            'expected_amount' => (string) $manual['transaction']->amount,
            'expected_old_balance' => (string) $manual['transaction']->balance_before,
            'expected_new_balance' => (string) $manual['transaction']->balance_after,
        ];
    }

    private function discover(array $config, ?string $checksum = null): array
    {
        $raw = json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        return app(BinaryRecalculationRollbackService::class)->discover(
            CarbonImmutable::parse('2026-08-14 22:00:00', 'UTC'),
            CarbonImmutable::parse('2026-08-14 22:01:00', 'UTC'),
            BinaryRecalculationRollbackService::SCOPE_ADJUSTMENTS,
            null,
            69,
            $config,
            $checksum ?? hash('sha256', $raw),
        );
    }

    private function assertLedgerConsistent(Wallet $wallet): void
    {
        $transactions = $wallet->transactions()->orderBy('created_at')->orderBy('id')->get();
        $balance = (string) $transactions->first()?->balance_before;

        foreach ($transactions as $transaction) {
            $this->assertSame(0, bccomp((string) $transaction->balance_before, $balance, 2), "Ledger before mismatch at transaction {$transaction->id}");
            $balance = ! $transaction->affects_balance ? $balance : match ($transaction->direction) {
                'credit' => bcadd($balance, (string) $transaction->amount, 2),
                'debit' => bcsub($balance, (string) $transaction->amount, 2),
                default => $balance,
            };
            $this->assertSame(0, bccomp((string) $transaction->balance_after, $balance, 2), "Ledger after mismatch at transaction {$transaction->id}");
            $this->assertGreaterThanOrEqual(0, bccomp($balance, '0.00', 2));
        }

        $this->assertSame(0, bccomp((string) $wallet->balance, $balance, 2));
    }

    private function state(): array
    {
        return [
            'wallets' => Wallet::query()->orderBy('id')->get()->toArray(),
            'wallet_transactions' => WalletTransaction::query()->orderBy('id')->get()->toArray(),
            'transfers' => PartnerTransfer::query()->orderBy('id')->get()->toArray(),
            'runs' => BinaryBonusRun::query()->orderBy('id')->get()->toArray(),
            'bonuses' => BonusTransaction::query()->orderBy('id')->get()->toArray(),
            'calculations' => BinaryBonusCalculation::query()->orderBy('id')->get()->toArray(),
            'admin_logs' => AdminActionLog::query()->orderBy('id')->get()->toArray(),
        ];
    }
}

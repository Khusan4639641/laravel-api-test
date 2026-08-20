<?php

namespace Tests\Feature;

use App\Models\BinaryBonusCalculation;
use App\Models\BinaryBonusRun;
use App\Models\BonusTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\BinaryRecalculationRollbackService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BinaryRecalculationRollbackBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_dry_run_does_not_write_database_and_manifest_contains_exact_linked_records(): void
    {
        $historical = $this->historicalIncidentUser();
        $new = $this->newIncidentRun();
        $before = $this->databaseState();
        $manifestPath = storage_path('framework/testing/binary-rollback-manifest.json');
        $reportPath = storage_path('framework/testing/binary-rollback-report.json');

        $this->artisan('safi:binary-recalculation:rollback-batch', [
            '--started-at' => '2026-08-14 22:00:00',
            '--ended-at' => '2026-08-14 22:01:00',
            '--scope' => 'entire-batch',
            '--dry-run' => true,
            '--manifest' => $manifestPath,
            '--report' => $reportPath,
            '--control-user-id' => $historical['user']->id,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertSame($before, $this->databaseState());
        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$historical['run']->id], $manifest['run_ids']['historical']);
        $this->assertSame([$new['run']->id], $manifest['run_ids']['new']);
        $this->assertSame([$historical['bonus']->id], $manifest['bonus_transaction_ids']['restore']);
        $this->assertSame([$new['bonus']->id], $manifest['bonus_transaction_ids']['delete']);
        $this->assertSame([$historical['main_adjustment']->id, $historical['deposit_adjustment']->id], $manifest['wallet_transaction_ids']['adjustments']);
        $this->assertSame([$new['main_payout']->id, $new['deposit_payout']->id], $manifest['wallet_transaction_ids']['new_payouts']);
        $this->assertSame([$historical['bad_calculation']->id, $new['calculation']->id], $manifest['calculation_ids']['delete']);
        $this->assertSame([], $manifest['pv_transaction_ids']);
        $this->assertNotEmpty($manifest['fingerprint']);
    }

    public function test_fingerprint_changes_when_relevant_database_state_changes(): void
    {
        $fixture = $this->historicalIncidentUser();
        $first = $this->discover('adjustments-only');
        $fixture['main_wallet']->forceFill(['balance' => '99999.00'])->save();
        $second = $this->discover('adjustments-only');

        $this->assertNotSame($first['fingerprint'], $second['fingerprint']);
    }

    public function test_force_requires_manifest_and_matching_fingerprint(): void
    {
        $this->artisan('safi:binary-recalculation:rollback-batch', [
            '--scope' => 'entire-batch',
            '--force' => true,
            '--confirm-fingerprint' => 'abc',
        ])->assertExitCode(Command::FAILURE);

        $fixture = $this->historicalIncidentUser();
        $manifest = $this->discover('adjustments-only');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('confirmed fingerprint');
        app(BinaryRecalculationRollbackService::class)->execute($manifest, str_repeat('0', 64));
        $this->assertDatabaseHas('wallet_transactions', ['id' => $fixture['main_adjustment']->id]);
    }

    public function test_force_rejects_approved_manifest_after_database_state_changes(): void
    {
        $fixture = $this->historicalIncidentUser();
        $manifest = $this->discover('adjustments-only');
        $fixture['user']->forceFill(['remaining_left_pv' => '1.00'])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fingerprint mismatch');
        app(BinaryRecalculationRollbackService::class)->execute($manifest, $manifest['fingerprint']);
    }

    public function test_unknown_batch_fails_without_writes(): void
    {
        $before = $this->databaseState();

        $this->artisan('safi:binary-recalculation:rollback-batch', [
            '--started-at' => '2026-08-14 22:00:00',
            '--ended-at' => '2026-08-14 22:01:00',
            '--scope' => 'entire-batch',
            '--dry-run' => true,
            '--manifest' => storage_path('framework/testing/unknown-manifest.json'),
        ])->assertExitCode(Command::FAILURE);

        $this->assertSame($before, $this->databaseState());
    }

    public function test_historical_run_is_restored_not_deleted_and_later_referral_is_replayed(): void
    {
        $fixture = $this->historicalIncidentUser();
        $manifest = $this->discover('adjustments-only', $fixture['user']->id);
        $result = app(BinaryRecalculationRollbackService::class)->execute($manifest, $manifest['fingerprint']);

        $this->assertFalse($result['already_rolled_back']);
        $this->assertDatabaseHas('binary_bonus_runs', [
            'id' => $fixture['run']->id,
            'amount' => '14000.00',
            'weak_leg_pv' => '400.00',
            'used_left_pv' => '400.00',
            'used_right_pv' => '400.00',
        ]);
        $this->assertDatabaseHas('bonus_transactions', [
            'id' => $fixture['bonus']->id,
            'amount' => '14000.00',
            'matched_pv' => '400.00',
        ]);
        $this->assertDatabaseMissing('wallet_transactions', ['id' => $fixture['main_adjustment']->id]);
        $this->assertDatabaseMissing('wallet_transactions', ['id' => $fixture['deposit_adjustment']->id]);
        $this->assertDatabaseHas('wallet_transactions', ['id' => $fixture['main_payout']->id, 'type' => 'binary_bonus_main']);
        $this->assertDatabaseHas('wallet_transactions', ['id' => $fixture['deposit_payout']->id, 'type' => 'binary_bonus_deposit']);
        $this->assertDatabaseHas('wallet_transactions', [
            'id' => $fixture['later_referral']->id,
            'type' => 'referral_bonus',
            'balance_before' => '27600.00',
            'balance_after' => '32600.00',
        ]);
        $this->assertSame('32600.00', $fixture['main_wallet']->refresh()->balance);
        $this->assertSame('1400.00', $fixture['deposit_wallet']->refresh()->balance);
        $this->assertSame('120.00', $fixture['user']->refresh()->remaining_left_pv);
        $this->assertSame('200.00', $fixture['user']->remaining_right_pv);
        $this->assertDatabaseMissing('binary_bonus_calculations', ['id' => $fixture['bad_calculation']->id]);
        $this->assertDatabaseHas('binary_bonus_calculations', ['id' => $fixture['source_calculation']->id]);
        $this->assertArrayNotHasKey('manual_recalculation', $fixture['run']->refresh()->metadata);
        $this->assertArrayNotHasKey('recalculation', $fixture['bonus']->refresh()->metadata);
    }

    public function test_zero_adjustment_historical_run_restores_metadata_without_wallet_changes(): void
    {
        $fixture = $this->historicalIncidentUser(false, false);
        $mainBalance = $fixture['main_wallet']->balance;
        $depositBalance = $fixture['deposit_wallet']->balance;
        $manifest = $this->discover('adjustments-only');

        $this->assertSame(1, $manifest['totals']['historical_zero_adjustment_runs']);
        app(BinaryRecalculationRollbackService::class)->execute($manifest, $manifest['fingerprint']);

        $this->assertSame($mainBalance, $fixture['main_wallet']->refresh()->balance);
        $this->assertSame($depositBalance, $fixture['deposit_wallet']->refresh()->balance);
        $this->assertDatabaseHas('binary_bonus_runs', ['id' => $fixture['run']->id]);
        $this->assertArrayNotHasKey('manual_recalculation', $fixture['run']->refresh()->metadata);
        $this->assertDatabaseMissing('binary_bonus_calculations', ['id' => $fixture['bad_calculation']->id]);
    }

    public function test_entire_batch_deletes_new_linked_records_restores_consumed_pv_and_preserves_later_transaction(): void
    {
        $fixture = $this->newIncidentRun(true);
        $manifest = $this->discover('entire-batch');
        app(BinaryRecalculationRollbackService::class)->execute($manifest, $manifest['fingerprint']);

        $this->assertDatabaseMissing('binary_bonus_runs', ['id' => $fixture['run']->id]);
        $this->assertDatabaseMissing('bonus_transactions', ['id' => $fixture['bonus']->id]);
        $this->assertDatabaseMissing('binary_bonus_calculations', ['id' => $fixture['calculation']->id]);
        $this->assertDatabaseMissing('wallet_transactions', ['id' => $fixture['main_payout']->id]);
        $this->assertDatabaseMissing('wallet_transactions', ['id' => $fixture['deposit_payout']->id]);
        $this->assertDatabaseHas('wallet_transactions', ['id' => $fixture['later_referral']->id, 'type' => 'referral_bonus']);
        $this->assertSame('15000.00', $fixture['main_wallet']->refresh()->balance);
        $this->assertSame('0.00', $fixture['deposit_wallet']->refresh()->balance);
        $this->assertSame('500.00', $fixture['user']->refresh()->remaining_left_pv);
        $this->assertSame('200.00', $fixture['user']->remaining_right_pv);
    }

    public function test_insufficient_balance_blocker_aborts_whole_strict_batch_and_preserves_partner_transfer(): void
    {
        $safe = $this->historicalIncidentUser(true, true, 'safe@example.test');
        $blocked = $this->historicalIncidentUser(true, false, 'blocked@example.test', true);
        $manifest = $this->discover('adjustments-only');

        $this->assertFalse(collect($manifest['operations'])->firstWhere('user_id', $blocked['user']->id)['can_rollback']);
        $this->assertSame(1, $manifest['totals']['users_with_insufficient_balance']);

        try {
            app(BinaryRecalculationRollbackService::class)->execute($manifest, $manifest['fingerprint']);
            $this->fail('Strict rollback should have been blocked.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Strict rollback blocked', $exception->getMessage());
        }

        $this->assertDatabaseHas('wallet_transactions', ['id' => $safe['main_adjustment']->id]);
        $this->assertDatabaseHas('wallet_transactions', ['id' => $blocked['main_adjustment']->id]);
        $this->assertDatabaseHas('wallet_transactions', ['id' => $blocked['partner_transfer']->id, 'type' => 'partner_transfer_out']);
        $this->assertSame('18200.00', $safe['bonus']->refresh()->amount);
    }

    public function test_incident_blocker_users_25_51_and_53_are_all_reported(): void
    {
        $this->historicalIncidentUser(true, false, 'user25@example.test', true, 25);
        $this->historicalIncidentUser(true, false, 'user51@example.test', true, 51);
        $this->historicalIncidentUser(true, false, 'user53@example.test', true, 53);
        $manifest = $this->discover('adjustments-only');
        $blockedUserIds = collect($manifest['operations'])
            ->where('can_rollback', false)
            ->pluck('user_id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([25, 51, 53], $blockedUserIds);
        $this->assertSame(3, $manifest['totals']['users_with_insufficient_balance']);
    }

    public function test_second_force_is_idempotent(): void
    {
        $this->historicalIncidentUser();
        $manifest = $this->discover('adjustments-only');
        $service = app(BinaryRecalculationRollbackService::class);
        $service->execute($manifest, $manifest['fingerprint']);
        $state = $this->databaseState();
        $second = $service->execute($manifest, $manifest['fingerprint']);

        $this->assertTrue($second['already_rolled_back']);
        $this->assertSame('Binary recalculation batch has already been rolled back.', $second['message']);
        $this->assertSame($state, $this->databaseState());
    }

    public function test_control_user_plan_matches_incident_ledger_expectations(): void
    {
        $fixture = $this->historicalIncidentUser();
        $manifest = $this->discover('adjustments-only', $fixture['user']->id);
        $control = $manifest['control_user'];

        $this->assertSame('36380.00', $control['current_main_balance']);
        $this->assertSame('1820.00', $control['current_deposit_balance']);
        $this->assertSame('32600.00', $control['expected_main_after_rollback']);
        $this->assertSame('1400.00', $control['expected_deposit_after_rollback']);
        $this->assertSame('14000.00', $control['expected_bonus_amount']);
        $this->assertSame([$fixture['later_referral']->id], $control['later_transaction_ids_to_preserve']);
        $this->assertTrue($control['can_rollback']);
    }

    /** @return array<string, mixed> */
    private function historicalIncidentUser(
        bool $withAdjustment = true,
        bool $withLaterReferral = true,
        string $email = 'Naz82safi@internet.ru',
        bool $spendAdjustment = false,
        ?int $userId = null,
    ): array {
        $originalTime = CarbonImmutable::parse('2026-08-01 03:00:03', 'Asia/Tashkent');
        CarbonImmutable::setTestNow($originalTime);
        $user = User::factory()->create([
            ...($userId === null ? [] : ['id' => $userId]),
            'email' => $email,
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'remaining_left_pv' => '0.00',
            'remaining_right_pv' => '200.00',
        ]);
        app(WalletService::class)->createUserWallets($user);
        $mainWallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $depositWallet = $user->wallets()->where('type', 'deposit')->firstOrFail();
        $mainWallet->forceFill(['balance' => '15000.00'])->save();
        $bonus = BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => 'binary',
            'amount' => '14000.00',
            'left_pv' => '400.00',
            'right_pv' => '600.00',
            'matched_pv' => '400.00',
            'status' => 'completed',
            'metadata' => $this->bonusMetadata('400.00', '14000.00', '12600.00', '1400.00', '0.00', '200.00'),
            'calculated_at' => $originalTime,
        ]);
        $run = BinaryBonusRun::query()->create([
            'user_id' => $user->id,
            'bonus_transaction_id' => $bonus->id,
            'status' => 'completed',
            'period_start' => $originalTime,
            'period_end' => CarbonImmutable::parse('2026-08-16 03:00:03', 'Asia/Tashkent'),
            'weak_leg_pv' => '400.00',
            'used_left_pv' => '400.00',
            'used_right_pv' => '400.00',
            'carry_left_pv' => '0.00',
            'carry_right_pv' => '200.00',
            'amount' => '14000.00',
            'pending_amount' => '0.00',
            'metadata' => $this->runMetadata('400.00', '14000.00'),
        ]);
        $sourceCalculation = $this->calculation($run, $bonus, '400.00', '600.00', '400.00', '0.00', '200.00', '14000.00', '12600.00', '1400.00', false);
        $mainPayout = app(WalletService::class)->credit($mainWallet, '12600.00', 'binary_bonus_main', $bonus, ['source' => 'binary_bonus']);
        $depositPayout = app(WalletService::class)->credit($depositWallet, '1400.00', 'binary_bonus_deposit', $bonus, ['source' => 'binary_bonus']);
        $bonus->forceFill([
            'wallet_transaction_id' => $mainPayout->id,
            'metadata' => [
                ...$bonus->metadata,
                'main_wallet_transaction_id' => $mainPayout->id,
                'deposit_wallet_transaction_id' => $depositPayout->id,
            ],
        ])->save();

        $batchTime = CarbonImmutable::parse('2026-08-15 03:00:04', 'Asia/Tashkent');
        CarbonImmutable::setTestNow($batchTime);
        $manual = [
            'source' => 'binary_recalculation',
            'binary_bonus_run_id' => $run->id,
            'old_main_amount' => '12600.00',
            'old_deposit_amount' => '1400.00',
            'new_main_amount' => $withAdjustment ? '16380.00' : '12600.00',
            'new_deposit_amount' => $withAdjustment ? '1820.00' : '1400.00',
            'adjustment_main' => $withAdjustment ? '3780.00' : '0.00',
            'adjustment_deposit' => $withAdjustment ? '420.00' : '0.00',
            'recalculated_at' => $batchTime->utc()->toISOString(),
            'eligible' => true,
        ];
        $badMatchedPv = $withAdjustment ? '520.00' : '400.00';
        $badTotal = $withAdjustment ? '18200.00' : '14000.00';
        $badMain = $withAdjustment ? '16380.00' : '12600.00';
        $badDeposit = $withAdjustment ? '1820.00' : '1400.00';
        $badCarryRight = $withAdjustment ? '80.00' : '200.00';
        $mainAdjustment = null;
        $depositAdjustment = null;

        if ($withAdjustment) {
            $mainAdjustment = app(WalletService::class)->credit($mainWallet, '3780.00', 'binary_bonus_main_adjustment', $bonus, [...$manual, 'wallet_part' => 'main']);
            $depositAdjustment = app(WalletService::class)->credit($depositWallet, '420.00', 'binary_bonus_deposit_adjustment', $bonus, [...$manual, 'wallet_part' => 'deposit']);
            $manual['main_adjustment_transaction_id'] = $mainAdjustment->id;
            $manual['deposit_adjustment_transaction_id'] = $depositAdjustment->id;
        }

        $run->forceFill([
            'weak_leg_pv' => $badMatchedPv,
            'used_left_pv' => $badMatchedPv,
            'used_right_pv' => $badMatchedPv,
            'carry_left_pv' => '0.00',
            'carry_right_pv' => $badCarryRight,
            'amount' => $badTotal,
            'metadata' => [...$this->runMetadata($badMatchedPv, $badTotal), 'manual_recalculation' => $manual],
        ])->save();
        $bonus->forceFill([
            'amount' => $badTotal,
            'left_pv' => $badMatchedPv,
            'right_pv' => '600.00',
            'matched_pv' => $badMatchedPv,
            'metadata' => [
                ...$this->bonusMetadata($badMatchedPv, $badTotal, $badMain, $badDeposit, '0.00', $badCarryRight),
                'main_wallet_transaction_id' => $mainPayout->id,
                'deposit_wallet_transaction_id' => $depositPayout->id,
                'recalculation' => $manual,
                'bulk_recalculation' => ['source' => 'scheduler'],
            ],
            'calculated_at' => $batchTime,
        ])->save();
        $user->forceFill(['remaining_left_pv' => '0.00', 'remaining_right_pv' => $badCarryRight])->save();
        $badCalculation = $this->calculation($run, $bonus, $badMatchedPv, '600.00', $badMatchedPv, '0.00', $badCarryRight, $badTotal, $badMain, $badDeposit, true, $manual);
        $laterReferral = null;
        $partnerTransfer = null;

        if ($spendAdjustment) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 12:00:00', 'Asia/Tashkent'));
            $partnerTransfer = app(WalletService::class)->debit(
                $mainWallet,
                (string) $mainWallet->balance,
                'partner_transfer_out',
                null,
                ['source' => 'partner_transfer'],
            );
        } elseif ($withLaterReferral) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-16 22:02:25', 'Asia/Tashkent'));
            $laterBonus = BonusTransaction::query()->create([
                'user_id' => $user->id,
                'bonus_type' => 'referral',
                'amount' => '5000.00',
                'status' => 'completed',
                'calculated_at' => now(),
            ]);
            $laterReferral = app(WalletService::class)->credit($mainWallet, '5000.00', 'referral_bonus', $laterBonus, ['source' => 'referral_bonus']);
        }

        return [
            'user' => $user,
            'main_wallet' => $mainWallet,
            'deposit_wallet' => $depositWallet,
            'bonus' => $bonus,
            'run' => $run,
            'source_calculation' => $sourceCalculation,
            'bad_calculation' => $badCalculation,
            'main_payout' => $mainPayout,
            'deposit_payout' => $depositPayout,
            'main_adjustment' => $mainAdjustment,
            'deposit_adjustment' => $depositAdjustment,
            'later_referral' => $laterReferral,
            'partner_transfer' => $partnerTransfer,
        ];
    }

    /** @return array<string, mixed> */
    private function newIncidentRun(bool $withLaterReferral = false): array
    {
        $batchTime = CarbonImmutable::parse('2026-08-15 03:00:05', 'Asia/Tashkent');
        CarbonImmutable::setTestNow($batchTime);
        $user = User::factory()->create([
            'email' => 'new-run-'.uniqid().'@example.test',
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'remaining_left_pv' => '300.00',
            'remaining_right_pv' => '0.00',
        ]);
        app(WalletService::class)->createUserWallets($user);
        $mainWallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $depositWallet = $user->wallets()->where('type', 'deposit')->firstOrFail();
        $mainWallet->forceFill(['balance' => '10000.00'])->save();
        $bonus = BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => 'binary',
            'amount' => '10000.00',
            'left_pv' => '500.00',
            'right_pv' => '200.00',
            'matched_pv' => '200.00',
            'status' => 'completed',
            'metadata' => $this->bonusMetadata('200.00', '10000.00', '9000.00', '1000.00', '300.00', '0.00'),
            'calculated_at' => $batchTime,
        ]);
        $run = BinaryBonusRun::query()->create([
            'user_id' => $user->id,
            'bonus_transaction_id' => $bonus->id,
            'status' => 'completed',
            'period_start' => $batchTime,
            'period_end' => $batchTime->addDays(15),
            'weak_leg_pv' => '200.00',
            'used_left_pv' => '200.00',
            'used_right_pv' => '200.00',
            'carry_left_pv' => '300.00',
            'carry_right_pv' => '0.00',
            'amount' => '10000.00',
            'pending_amount' => '0.00',
            'metadata' => $this->runMetadata('200.00', '10000.00'),
        ]);
        $mainPayout = app(WalletService::class)->credit($mainWallet, '9000.00', 'binary_bonus_main', $bonus, ['source' => 'binary_bonus']);
        $depositPayout = app(WalletService::class)->credit($depositWallet, '1000.00', 'binary_bonus_deposit', $bonus, ['source' => 'binary_bonus']);
        $bonus->forceFill([
            'wallet_transaction_id' => $mainPayout->id,
            'metadata' => [
                ...$bonus->metadata,
                'main_wallet_transaction_id' => $mainPayout->id,
                'deposit_wallet_transaction_id' => $depositPayout->id,
                'bulk_recalculation' => ['source' => 'scheduler'],
            ],
        ])->save();
        $calculation = $this->calculation($run, $bonus, '500.00', '200.00', '200.00', '300.00', '0.00', '10000.00', '9000.00', '1000.00', false);
        $laterReferral = null;

        if ($withLaterReferral) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-16 10:00:00', 'Asia/Tashkent'));
            $laterBonus = BonusTransaction::query()->create([
                'user_id' => $user->id,
                'bonus_type' => 'referral',
                'amount' => '5000.00',
                'status' => 'completed',
                'calculated_at' => now(),
            ]);
            $laterReferral = app(WalletService::class)->credit($mainWallet, '5000.00', 'referral_bonus', $laterBonus, ['source' => 'referral_bonus']);
        }

        return [
            'user' => $user,
            'main_wallet' => $mainWallet,
            'deposit_wallet' => $depositWallet,
            'bonus' => $bonus,
            'run' => $run,
            'calculation' => $calculation,
            'main_payout' => $mainPayout,
            'deposit_payout' => $depositPayout,
            'later_referral' => $laterReferral,
        ];
    }

    private function calculation(
        BinaryBonusRun $run,
        BonusTransaction $bonus,
        string $leftPv,
        string $rightPv,
        string $matchedPv,
        string $carryLeft,
        string $carryRight,
        string $total,
        string $main,
        string $deposit,
        bool $batch,
        array $manual = [],
    ): BinaryBonusCalculation {
        return BinaryBonusCalculation::query()->create([
            'binary_bonus_run_id' => $run->id,
            'user_id' => $run->user_id,
            'bonus_transaction_id' => $bonus->id,
            'left_pv' => $leftPv,
            'right_pv' => $rightPv,
            'weak_leg_pv' => $matchedPv,
            'used_left_pv' => $matchedPv,
            'used_right_pv' => $matchedPv,
            'carry_left_pv' => $carryLeft,
            'carry_right_pv' => $carryRight,
            'money_base_amount' => bcmul($matchedPv, '500', 2),
            'binary_percent' => '7.00',
            'bonus_amount' => $total,
            'main_amount' => $main,
            'deposit_amount' => $deposit,
            'metadata' => $batch
                ? ['source' => 'manual_binary_recalculation', 'pv_money_rate' => '500', 'diagnostics' => ['snapshot' => 'bad'], 'recalculation' => $manual]
                : [
                    'pv_money_rate' => '500',
                    'period_start' => $run->period_start->utc()->toISOString(),
                    'period_end' => $run->period_end->utc()->toISOString(),
                    'diagnostics' => ['snapshot' => 'original'],
                ],
        ]);
    }

    private function runMetadata(string $matchedPv, string $total): array
    {
        return [
            'package_id' => 1,
            'pv_money_rate' => '500',
            'binary_percent' => '7.00',
            'diagnostics' => ['weak_leg_pv' => $matchedPv, 'binary_total' => $total],
        ];
    }

    private function bonusMetadata(string $matchedPv, string $total, string $main, string $deposit, string $carryLeft, string $carryRight): array
    {
        return [
            'base_pv' => $matchedPv,
            'money_base_amount' => bcmul($matchedPv, '500', 2),
            'pv_money_rate' => '500',
            'binary_percent' => '7.00',
            'main_percent' => '90.00',
            'deposit_percent' => '10.00',
            'main_amount' => $main,
            'deposit_amount' => $deposit,
            'used_left_pv' => $matchedPv,
            'used_right_pv' => $matchedPv,
            'carry_left_pv' => $carryLeft,
            'carry_right_pv' => $carryRight,
            'remaining_left_pv_after' => $carryLeft,
            'remaining_right_pv_after' => $carryRight,
            'diagnostics' => ['weak_leg_pv' => $matchedPv, 'binary_total' => $total],
        ];
    }

    private function discover(string $scope, int $controlUserId = 69): array
    {
        return app(BinaryRecalculationRollbackService::class)->discover(
            CarbonImmutable::parse('2026-08-14 22:00:00', 'UTC'),
            CarbonImmutable::parse('2026-08-14 22:01:00', 'UTC'),
            $scope,
            null,
            $controlUserId,
        );
    }

    private function databaseState(): array
    {
        return [
            'runs' => BinaryBonusRun::query()->orderBy('id')->get()->toArray(),
            'bonuses' => BonusTransaction::query()->orderBy('id')->get()->toArray(),
            'calculations' => BinaryBonusCalculation::query()->orderBy('id')->get()->toArray(),
            'wallets' => Wallet::query()->orderBy('id')->get()->toArray(),
            'wallet_transactions' => WalletTransaction::query()->orderBy('id')->get()->toArray(),
            'users' => User::query()->orderBy('id')->get(['id', 'remaining_left_pv', 'remaining_right_pv', 'updated_at'])->toArray(),
        ];
    }
}

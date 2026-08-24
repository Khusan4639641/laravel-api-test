<?php

namespace Tests\Feature;

use App\Models\BinaryBonusCalculation;
use App\Models\BinaryBonusRun;
use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\PvTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BinaryBonusPeriodResolver;
use App\Services\BonusService;
use App\Services\ScheduledBinaryBonusService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\Support\CreatesBinaryBonusEligibility;
use Tests\TestCase;

class ScheduledBinaryCalculationSafetyTest extends TestCase
{
    use CreatesBinaryBonusEligibility;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_period_resolver_uses_tashkent_boundaries_and_explicit_utc_conversion(): void
    {
        $period = app(BinaryBonusPeriodResolver::class)->resolve(
            CarbonImmutable::parse('2026-08-15 03:00:00', 'Asia/Tashkent')
        );

        $this->assertSame('2026-08-14 22:00:00', $period['start']->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-31 21:59:59', $period['end']->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Tashkent', $period['timezone']);
    }

    public function test_scheduled_use_case_never_updates_completed_run_and_creates_one_new_period_with_shared_batch_uuid(): void
    {
        $package = $this->package('START', 7);
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => $package->id,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ]);
        $eligible = $this->makeBinaryBonusEligible($user);
        $oldPeriodStart = CarbonImmutable::parse('2026-08-01 03:00:00', 'Asia/Tashkent');
        $oldPeriodEnd = CarbonImmutable::parse('2026-08-15 02:59:59', 'Asia/Tashkent');
        CarbonImmutable::setTestNow($oldPeriodStart);
        $oldBonus = app(BonusService::class)->calculateBinaryBonus($user, $oldPeriodStart, $oldPeriodEnd);
        $oldRun = BinaryBonusRun::query()->where('bonus_transaction_id', $oldBonus->id)->firstOrFail();
        $oldRunSnapshot = $oldRun->getAttributes();
        $oldBonusSnapshot = $oldBonus->getAttributes();

        foreach ([['L', $eligible['left']], ['R', $eligible['right']]] as [$branch, $buyer]) {
            PvTransaction::query()->create([
                'buyer_id' => $buyer->id,
                'upline_id' => $user->id,
                'source' => 'test_new_period_pv',
                'branch' => $branch,
                'pv' => '200.00',
                'is_bonusable' => true,
            ]);
        }

        $user->refresh()->forceFill(['remaining_left_pv' => '200.00', 'remaining_right_pv' => '200.00'])->save();
        $scheduledFor = CarbonImmutable::parse('2026-08-15 03:00:00', 'Asia/Tashkent');
        CarbonImmutable::setTestNow($scheduledFor);
        $first = app(ScheduledBinaryBonusService::class)->calculateForAllPartners($scheduledFor);

        $this->assertSame(1, $first['created_count']);
        $this->assertSame(0, $first['updated_count']);
        $this->assertSame($oldRunSnapshot, $oldRun->refresh()->getAttributes());
        $this->assertSame($oldBonusSnapshot, $oldBonus->refresh()->getAttributes());
        $this->assertDatabaseCount('binary_bonus_runs', 2);
        $this->assertDatabaseCount('bonus_transactions', 2);
        $this->assertDatabaseMissing('wallet_transactions', ['type' => 'binary_bonus_main_adjustment']);
        $this->assertDatabaseMissing('wallet_transactions', ['type' => 'binary_bonus_deposit_adjustment']);

        $newRun = BinaryBonusRun::query()->whereKeyNot($oldRun->id)->firstOrFail();
        $newBonus = $newRun->bonusTransaction()->firstOrFail();
        $newCalculation = $newRun->calculations()->firstOrFail();
        $newWalletTransactions = WalletTransaction::query()
            ->where('source_type', BonusTransaction::class)
            ->where('source_id', $newBonus->id)
            ->get();
        $batchUuid = $newRun->metadata['batch_uuid'];

        $this->assertSame('200.00', $newRun->used_left_pv);
        $this->assertSame('200.00', $newRun->used_right_pv);
        $this->assertSame('7000.00', $newRun->amount);
        $this->assertSame('6300.00', $newWalletTransactions->firstWhere('type', 'binary_bonus_main')->amount);
        $this->assertSame('700.00', $newWalletTransactions->firstWhere('type', 'binary_bonus_deposit')->amount);
        $this->assertSame($batchUuid, $newBonus->metadata['batch_uuid']);
        $this->assertSame($batchUuid, $newCalculation->metadata['batch_uuid']);
        $this->assertTrue($newWalletTransactions->every(fn (WalletTransaction $transaction): bool => $transaction->metadata['batch_uuid'] === $batchUuid));
        $this->assertSame('scheduled_binary_calculation', $newRun->metadata['source']);
        $this->assertSame('scheduled_binary_calculation', $newBonus->metadata['source']);
        $this->assertTrue($newWalletTransactions->every(fn (WalletTransaction $transaction): bool => $transaction->metadata['source'] === 'scheduled_binary_calculation'));

        $runCount = BinaryBonusRun::query()->count();
        $bonusCount = BonusTransaction::query()->count();
        $walletCount = WalletTransaction::query()->count();
        $calculationCount = BinaryBonusCalculation::query()->count();
        $second = app(ScheduledBinaryBonusService::class)->calculateForAllPartners($scheduledFor);

        $this->assertSame(0, $second['created_count']);
        $this->assertSame($runCount, BinaryBonusRun::query()->count());
        $this->assertSame($bonusCount, BonusTransaction::query()->count());
        $this->assertSame($walletCount, WalletTransaction::query()->count());
        $this->assertSame($calculationCount, BinaryBonusCalculation::query()->count());
    }

    public function test_concurrent_scheduled_invocation_is_rejected_by_period_lock(): void
    {
        $scheduledFor = CarbonImmutable::parse('2026-08-15 03:00:00', 'Asia/Tashkent');
        $lock = Cache::lock('safi:scheduled-binary:20260814220000', 3600);
        $this->assertTrue($lock->get());

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('already running');
            app(ScheduledBinaryBonusService::class)->calculateForAllPartners($scheduledFor);
        } finally {
            $lock->release();
        }
    }

    private function package(string $code, int $binaryPercent): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code).'-'.uniqid(),
            'price' => 60000,
            'pv' => 100,
            'activity_pv' => 100,
            'turnover_pv' => 100,
            'referral_percent' => 10,
            'binary_percent' => $binaryPercent,
            'status' => 'active',
            'is_active' => true,
        ]);
    }
}

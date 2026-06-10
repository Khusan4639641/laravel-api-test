<?php

namespace Tests\Feature;

use App\Models\BinaryBonusRun;
use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\PvTransaction;
use App\Models\User;
use App\Services\BonusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBinaryBonusEligibility;
use Tests\TestCase;

class BinaryBonusSoftDeletedPvExclusionTest extends TestCase
{
    use CreatesBinaryBonusEligibility;
    use RefreshDatabase;

    public function test_soft_deleted_user_pv_is_excluded_from_binary_calculation(): void
    {
        $root = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($root);
        $deletedRightBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);

        $this->pv($root, $leftBuyer, 'L', 2000);
        $this->pv($root, $rightBuyer, 'R', 2000);
        $this->pv($root, $deletedRightBuyer, 'R', 200);
        $deletedRightBuyer->delete();

        $bonus = $this->calculateBinary($root);

        $this->assertSame('2000.00', $bonus->matched_pv);
        $this->assertSame('100000.00', $bonus->amount);
        $this->assertWalletBalance($root, 'main', '90000.00');
        $this->assertWalletBalance($root, 'deposit', '10000.00');
        $this->assertNotSame('110000.00', $bonus->amount);
    }

    public function test_soft_deleted_left_pv_does_not_increase_weak_leg(): void
    {
        $root = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($root);
        $deletedLeftBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);

        $this->pv($root, $leftBuyer, 'L', 2000);
        $this->pv($root, $deletedLeftBuyer, 'L', 300);
        $this->pv($root, $rightBuyer, 'R', 2200);
        $deletedLeftBuyer->delete();

        $bonus = $this->calculateBinary($root);

        $this->assertSame('2000.00', $bonus->matched_pv);
        $this->assertSame('100000.00', $bonus->amount);
    }

    public function test_soft_deleted_user_is_excluded_from_branch_binary_pv_but_remains_in_audit(): void
    {
        $root = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($root);
        $deletedRightBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);

        $this->pv($root, $leftBuyer, 'L', 2000);
        $this->pv($root, $rightBuyer, 'R', 2000);
        $this->pv($root, $deletedRightBuyer, 'R', 200);
        $deletedRightBuyer->delete();

        $bonus = $this->calculateBinary($root);
        $run = BinaryBonusRun::query()->firstOrFail();

        $this->assertTrue($deletedRightBuyer->refresh()->trashed());
        $this->assertTrue(PvTransaction::query()->where('buyer_id', $deletedRightBuyer->id)->exists());
        $this->assertSame('2000.00', $bonus->matched_pv);
        $this->assertSame('200.00', $run->metadata['diagnostics']['right_excluded_deleted_pv']);
    }

    public function test_voided_pv_transaction_is_excluded(): void
    {
        $root = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($root);
        $voidedLeftBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);

        $this->pv($root, $leftBuyer, 'L', 2000);
        $this->pv($root, $voidedLeftBuyer, 'L', 500, true, 'reversed_order', now());
        $this->pv($root, $rightBuyer, 'R', 2000);

        $bonus = $this->calculateBinary($root);
        $run = BinaryBonusRun::query()->firstOrFail();

        $this->assertSame('2000.00', $bonus->matched_pv);
        $this->assertSame('500.00', $run->metadata['diagnostics']['left_excluded_voided_pv']);
    }

    public function test_archived_and_blocked_user_pv_is_excluded(): void
    {
        $root = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($root);
        $archivedRightBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'archived']);
        $blockedRightBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'blocked']);

        $this->pv($root, $leftBuyer, 'L', 2000);
        $this->pv($root, $rightBuyer, 'R', 2000);
        $this->pv($root, $archivedRightBuyer, 'R', 300);
        $this->pv($root, $blockedRightBuyer, 'R', 300);

        $bonus = $this->calculateBinary($root);
        $run = BinaryBonusRun::query()->firstOrFail();

        $this->assertSame('2000.00', $bonus->matched_pv);
        $this->assertSame('600.00', $run->metadata['diagnostics']['right_excluded_inactive_or_deleted_pv']);
    }

    public function test_active_non_deleted_users_are_still_counted(): void
    {
        $root = $this->rootWithPackage('START');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($root);

        $this->pv($root, $leftBuyer, 'L', 1500);
        $this->pv($root, $rightBuyer, 'R', 1200);

        $bonus = $this->calculateBinary($root);

        $this->assertSame('1200.00', $bonus->matched_pv);
        $this->assertSame('42000.00', $bonus->amount);
        $this->assertWalletBalance($root, 'main', '37800.00');
        $this->assertWalletBalance($root, 'deposit', '4200.00');
    }

    public function test_elite_non_bonusable_two_hundred_pv_is_excluded_from_binary_payout(): void
    {
        $root = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($root);
        $eliteUpgradeBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);

        $this->pv($root, $leftBuyer, 'L', 2000);
        $this->pv($root, $rightBuyer, 'R', 2000);
        $this->pv($root, $eliteUpgradeBuyer, 'R', 200, false, 'package_elite_upgrade');

        $bonus = $this->calculateBinary($root);
        $run = BinaryBonusRun::query()->firstOrFail();

        $this->assertSame('2000.00', $bonus->matched_pv);
        $this->assertSame('100000.00', $bonus->amount);
        $this->assertSame('200.00', $run->metadata['diagnostics']['right_excluded_elite_non_bonusable_pv']);
        $this->assertSame('200.00', $run->metadata['diagnostics']['right_excluded_non_bonusable_pv']);
    }

    public function test_elite_binary_percent_still_applies_to_eligible_bonusable_weak_leg(): void
    {
        $root = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($root);

        $this->pv($root, $leftBuyer, 'L', 2000);
        $this->pv($root, $rightBuyer, 'R', 2000);

        $bonus = $this->calculateBinary($root);

        $this->assertSame('2000.00', $bonus->matched_pv);
        $this->assertSame('100000.00', $bonus->amount);
        $this->assertWalletBalance($root, 'main', '90000.00');
        $this->assertWalletBalance($root, 'deposit', '10000.00');
    }

    public function test_binary_run_meta_records_excluded_deleted_and_non_bonusable_pv(): void
    {
        $root = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($root);
        $deletedRightBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);
        $eliteUpgradeBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);

        $this->pv($root, $leftBuyer, 'L', 2000);
        $this->pv($root, $rightBuyer, 'R', 2000);
        $this->pv($root, $deletedRightBuyer, 'R', 200);
        $this->pv($root, $eliteUpgradeBuyer, 'R', 200, false, 'package_elite_upgrade');
        $deletedRightBuyer->delete();

        $this->calculateBinary($root);
        $diagnostics = BinaryBonusRun::query()->firstOrFail()->metadata['diagnostics'];

        $this->assertTrue($diagnostics['uses_pv_transactions']);
        $this->assertSame('2400.00', $diagnostics['right_total_pv']);
        $this->assertSame('200.00', $diagnostics['right_excluded_deleted_pv']);
        $this->assertSame('200.00', $diagnostics['right_excluded_elite_non_bonusable_pv']);
        $this->assertSame('2000.00', $diagnostics['right_bonusable_pv']);
        $this->assertSame('2000.00', $diagnostics['weak_leg_pv']);
        $this->assertSame('100000.00', $diagnostics['binary_total']);
    }

    public function test_already_used_pv_from_previous_binary_period_is_not_counted_again(): void
    {
        $root = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($root);

        $this->pv($root, $leftBuyer, 'L', 2500);
        $this->pv($root, $rightBuyer, 'R', 2500);
        BinaryBonusRun::query()->create([
            'user_id' => $root->id,
            'status' => 'completed',
            'period_start' => now()->subDays(20),
            'period_end' => now()->subDays(5),
            'weak_leg_pv' => '500.00',
            'used_left_pv' => '500.00',
            'used_right_pv' => '500.00',
            'carry_left_pv' => '2000.00',
            'carry_right_pv' => '2000.00',
            'amount' => '25000.00',
            'pending_amount' => '0.00',
            'metadata' => ['seeded_previous_run' => true],
        ]);

        $bonus = $this->calculateBinary($root);
        $diagnostics = BinaryBonusRun::query()->latest('id')->firstOrFail()->metadata['diagnostics'];

        $this->assertSame('2000.00', $bonus->matched_pv);
        $this->assertSame('100000.00', $bonus->amount);
        $this->assertSame('500.00', $diagnostics['left_used_prior_pv']);
        $this->assertSame('500.00', $diagnostics['right_used_prior_pv']);
        $this->assertSame('2000.00', $diagnostics['left_available_pv']);
        $this->assertSame('2000.00', $diagnostics['right_available_pv']);
    }

    private function rootWithPackage(string $packageCode): User
    {
        $package = $this->package($packageCode);

        return User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => $package->id,
        ]);
    }

    private function package(string $code): Package
    {
        $matrix = [
            'START' => [60000, 100, 7],
            'VIP' => [180000, 300, 8],
            'ELITE' => [300000, 500, 10],
        ];
        [$price, $pv, $binaryPercent] = $matrix[$code];

        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code).'-'.uniqid(),
            'price' => $price,
            'pv' => $pv,
            'activity_pv' => $pv,
            'turnover_pv' => $code === 'ELITE' ? 200 : $pv,
            'referral_percent' => 10,
            'binary_percent' => $binaryPercent,
            'sort_order' => $code === 'START' ? 1 : ($code === 'VIP' ? 2 : 3),
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }

    private function pv(
        User $upline,
        User $buyer,
        string $branch,
        int $pv,
        bool $isBonusable = true,
        string $source = 'test_binary_turnover',
        mixed $voidedAt = null,
    ): PvTransaction {
        return PvTransaction::query()->create([
            'buyer_id' => $buyer->id,
            'upline_id' => $upline->id,
            'source' => $source,
            'branch' => $branch,
            'pv' => $pv,
            'is_bonusable' => $isBonusable,
            'voided_at' => $voidedAt,
            'metadata' => [
                'test_source' => self::class,
                'package_code' => $source === 'package_elite_upgrade' ? 'ELITE' : null,
            ],
        ]);
    }

    private function calculateBinary(User $root): BonusTransaction
    {
        $bonus = app(BonusService::class)->calculateBinaryBonus($root);

        $this->assertInstanceOf(BonusTransaction::class, $bonus);

        return $bonus->refresh();
    }

    private function assertWalletBalance(User $user, string $type, string $balance): void
    {
        $this->assertSame($balance, $user->wallets()->where('type', $type)->firstOrFail()->balance);
    }
}

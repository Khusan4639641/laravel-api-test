<?php

namespace Tests\Feature;

use App\Models\BinaryBonusRun;
use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\PvTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBinaryBonusEligibility;
use Tests\TestCase;

class AdminBinaryRecalculateButtonTest extends TestCase
{
    use CreatesBinaryBonusEligibility;
    use RefreshDatabase;

    public function test_admin_can_recalculate_binary_for_selected_partner(): void
    {
        $partner = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($partner);
        $this->pv($partner, $leftBuyer, 'L', 2000);
        $this->pv($partner, $rightBuyer, 'R', 2000);
        $this->actingAdmin();

        $this->postJson($this->endpoint($partner))
            ->assertOk()
            ->assertJsonPath('message', 'Бинар пересчитан')
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.weak_leg_pv', '2000.00')
            ->assertJsonPath('data.binary_total', '100000.00')
            ->assertJsonPath('data.main_wallet_amount', '90000.00')
            ->assertJsonPath('data.deposit_amount', '10000.00');

        $this->assertWalletBalance($partner, 'main', '90000.00');
        $this->assertWalletBalance($partner, 'deposit', '10000.00');
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'binary_bonus_main',
            'amount' => '90000.00',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'binary_bonus_deposit',
            'amount' => '10000.00',
        ]);
    }

    public function test_repeat_click_does_not_duplicate_binary(): void
    {
        $partner = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($partner);
        $this->pv($partner, $leftBuyer, 'L', 2000);
        $this->pv($partner, $rightBuyer, 'R', 2000);
        $this->actingAdmin();

        $this->postJson($this->endpoint($partner))->assertOk();
        $this->postJson($this->endpoint($partner))
            ->assertOk()
            ->assertJsonPath('data.adjustment_main', '0.00')
            ->assertJsonPath('data.adjustment_deposit', '0.00');

        $this->assertWalletBalance($partner, 'main', '90000.00');
        $this->assertWalletBalance($partner, 'deposit', '10000.00');
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $partner->id)->where('type', 'binary_bonus_main')->count());
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $partner->id)->where('type', 'binary_bonus_deposit')->count());
        $this->assertSame(0, WalletTransaction::query()->where('user_id', $partner->id)->whereIn('type', [
            'binary_bonus_main_adjustment',
            'binary_bonus_deposit_adjustment',
        ])->count());
    }

    public function test_recalculate_adjusts_previous_wrong_amount(): void
    {
        $partner = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($partner);
        $this->pv($partner, $leftBuyer, 'L', 2000);
        $this->pv($partner, $rightBuyer, 'R', 2000);
        $this->seedCurrentBinaryPayout($partner, '2200.00', '110000.00', '99000.00', '11000.00');
        $this->actingAdmin();

        $this->postJson($this->endpoint($partner))
            ->assertOk()
            ->assertJsonPath('data.binary_total', '100000.00')
            ->assertJsonPath('data.adjustment_main', '-9000.00')
            ->assertJsonPath('data.adjustment_deposit', '-1000.00');

        $this->assertWalletBalance($partner, 'main', '90000.00');
        $this->assertWalletBalance($partner, 'deposit', '10000.00');
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'binary_bonus_main_adjustment',
            'direction' => 'debit',
            'amount' => '9000.00',
            'description' => 'Перерасчёт бинарного бонуса: корректировка основного кошелька',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'binary_bonus_deposit_adjustment',
            'direction' => 'debit',
            'amount' => '1000.00',
            'description' => 'Перерасчёт бинарного бонуса: корректировка депозита',
        ]);
    }

    public function test_soft_deleted_pv_is_excluded_on_recalculation(): void
    {
        $partner = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($partner);
        $deletedRightBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);
        $this->pv($partner, $leftBuyer, 'L', 2000);
        $this->pv($partner, $rightBuyer, 'R', 2000);
        $this->pv($partner, $deletedRightBuyer, 'R', 200);
        $deletedRightBuyer->delete();
        $this->actingAdmin();

        $this->postJson($this->endpoint($partner))
            ->assertOk()
            ->assertJsonPath('data.right_pv_total', '2200.00')
            ->assertJsonPath('data.right_pv_excluded_deleted', '200.00')
            ->assertJsonPath('data.weak_leg_pv', '2000.00')
            ->assertJsonPath('data.binary_total', '100000.00');
    }

    public function test_elite_non_bonusable_pv_is_excluded_on_recalculation(): void
    {
        $partner = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($partner);
        $eliteUpgradeBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);
        $this->pv($partner, $leftBuyer, 'L', 2000);
        $this->pv($partner, $rightBuyer, 'R', 2000);
        $this->pv($partner, $eliteUpgradeBuyer, 'R', 200, false, 'package_elite_upgrade');
        $this->actingAdmin();

        $this->postJson($this->endpoint($partner))
            ->assertOk()
            ->assertJsonPath('data.right_pv_total', '2200.00')
            ->assertJsonPath('data.right_pv_excluded_non_bonusable', '200.00')
            ->assertJsonPath('data.weak_leg_pv', '2000.00')
            ->assertJsonPath('data.binary_total', '100000.00');
    }

    public function test_not_eligible_without_direct_referrals_in_both_branches(): void
    {
        $partner = $this->rootWithPackage('ELITE');
        $leftBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);
        $rightBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);
        $this->pv($partner, $leftBuyer, 'L', 2000);
        $this->pv($partner, $rightBuyer, 'R', 2000);
        $this->actingAdmin();

        $this->postJson($this->endpoint($partner))
            ->assertOk()
            ->assertJsonPath('message', 'Бинар не начислен')
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.reason', 'Нужен минимум 1 лично приглашённый партнёр в левой и правой ветке')
            ->assertJsonPath('data.binary_total', '0.00');

        $this->assertSame(0, WalletTransaction::query()->where('user_id', $partner->id)->count());
    }

    public function test_previous_current_period_payout_is_reversed_to_zero_when_now_not_eligible(): void
    {
        $partner = $this->rootWithPackage('ELITE');
        $leftBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);
        $rightBuyer = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);
        $this->pv($partner, $leftBuyer, 'L', 2000);
        $this->pv($partner, $rightBuyer, 'R', 2000);
        $this->seedCurrentBinaryPayout($partner, '2000.00', '100000.00', '90000.00', '10000.00');
        $this->actingAdmin();

        $this->postJson($this->endpoint($partner))
            ->assertOk()
            ->assertJsonPath('message', 'Бинар не начислен')
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.adjustment_main', '-90000.00')
            ->assertJsonPath('data.adjustment_deposit', '-10000.00');

        $this->assertWalletBalance($partner, 'main', '0.00');
        $this->assertWalletBalance($partner, 'deposit', '0.00');
    }

    public function test_non_admin_cannot_recalculate_binary(): void
    {
        $partner = $this->rootWithPackage('START');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));

        $this->postJson($this->endpoint($partner))->assertForbidden();
    }

    public function test_closed_previous_periods_are_not_touched(): void
    {
        $partner = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($partner);
        $this->pv($partner, $leftBuyer, 'L', 2500);
        $this->pv($partner, $rightBuyer, 'R', 2500);
        $closedRun = BinaryBonusRun::query()->create([
            'user_id' => $partner->id,
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
            'metadata' => ['seeded_closed_period' => true],
        ]);
        $this->actingAdmin();

        $response = $this->postJson($this->endpoint($partner))
            ->assertOk()
            ->assertJsonPath('data.left_used_prior_pv', '500.00')
            ->assertJsonPath('data.right_used_prior_pv', '500.00')
            ->assertJsonPath('data.weak_leg_pv', '2000.00');

        $this->assertSame('25000.00', $closedRun->refresh()->amount);
        $this->assertNotSame($closedRun->id, $response->json('data.period_id'));
    }

    public function test_response_contains_calculation_details(): void
    {
        $partner = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($partner);
        $this->pv($partner, $leftBuyer, 'L', 2000);
        $this->pv($partner, $rightBuyer, 'R', 2000);
        $this->actingAdmin();

        $this->postJson($this->endpoint($partner))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user_id',
                    'period_id',
                    'left_pv_total',
                    'right_pv_total',
                    'left_pv_excluded_deleted',
                    'right_pv_excluded_deleted',
                    'left_pv_excluded_non_bonusable',
                    'right_pv_excluded_non_bonusable',
                    'left_pv_bonusable',
                    'right_pv_bonusable',
                    'weak_leg_pv',
                    'binary_percent',
                    'binary_total',
                    'main_wallet_amount',
                    'deposit_amount',
                    'adjustment_main',
                    'adjustment_deposit',
                    'eligible',
                    'reason',
                ],
            ]);
    }

    private function endpoint(User $partner): string
    {
        return "/api/admin/partners/{$partner->id}/binary/recalculate";
    }

    private function actingAdmin(): User
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        return $admin;
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
    ): PvTransaction {
        return PvTransaction::query()->create([
            'buyer_id' => $buyer->id,
            'upline_id' => $upline->id,
            'source' => $source,
            'branch' => $branch,
            'pv' => $pv,
            'is_bonusable' => $isBonusable,
            'metadata' => [
                'test_source' => self::class,
                'package_code' => $source === 'package_elite_upgrade' ? 'ELITE' : null,
            ],
        ]);
    }

    private function seedCurrentBinaryPayout(
        User $partner,
        string $matchedPv,
        string $totalAmount,
        string $mainAmount,
        string $depositAmount,
    ): BinaryBonusRun {
        $bonusTransaction = BonusTransaction::query()->create([
            'user_id' => $partner->id,
            'bonus_type' => 'binary',
            'amount' => $totalAmount,
            'left_pv' => $matchedPv,
            'right_pv' => $matchedPv,
            'matched_pv' => $matchedPv,
            'status' => 'completed',
            'metadata' => ['seeded_wrong_current_period' => true],
            'calculated_at' => now(),
        ]);
        $run = BinaryBonusRun::query()->create([
            'user_id' => $partner->id,
            'bonus_transaction_id' => $bonusTransaction->id,
            'status' => 'completed',
            'period_start' => now()->subDay(),
            'period_end' => now()->addDays(14),
            'weak_leg_pv' => $matchedPv,
            'used_left_pv' => $matchedPv,
            'used_right_pv' => $matchedPv,
            'carry_left_pv' => '0.00',
            'carry_right_pv' => '0.00',
            'amount' => $totalAmount,
            'pending_amount' => '0.00',
            'metadata' => ['seeded_wrong_current_period' => true],
        ]);
        $walletService = app(WalletService::class);
        $walletService->createUserWallets($partner);
        $mainWallet = $partner->wallets()->where('type', 'main')->firstOrFail();
        $depositWallet = $partner->wallets()->where('type', 'deposit')->firstOrFail();
        $mainTransaction = $walletService->credit($mainWallet, $mainAmount, 'binary_bonus_main', $bonusTransaction);
        $walletService->credit($depositWallet, $depositAmount, 'binary_bonus_deposit', $bonusTransaction);

        $bonusTransaction->forceFill([
            'wallet_transaction_id' => $mainTransaction->id,
            'metadata' => [
                'seeded_wrong_current_period' => true,
                'binary_bonus_run_id' => $run->id,
            ],
        ])->save();

        return $run;
    }

    private function assertWalletBalance(User $user, string $type, string $balance): void
    {
        $this->assertSame($balance, $user->wallets()->where('type', $type)->firstOrFail()->balance);
    }
}

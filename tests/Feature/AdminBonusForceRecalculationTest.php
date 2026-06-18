<?php

namespace Tests\Feature;

use App\Models\BinaryBonusRun;
use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\PvTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\BonusAccruedNotification;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBinaryBonusEligibility;
use Tests\TestCase;

class AdminBonusForceRecalculationTest extends TestCase
{
    use CreatesBinaryBonusEligibility;
    use RefreshDatabase;

    public function test_force_recalculation_updates_already_calculated_binary_bonus_and_adjusts_wallets(): void
    {
        [$dateFrom, $dateTo] = $this->period();
        $partner = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($partner);
        $this->pv($partner, $leftBuyer, 'L', 1600);
        $this->pv($partner, $rightBuyer, 'R', 1600);
        $seeded = $this->seedPeriodBinaryPayout($partner, $dateFrom, $dateTo, '2000.00', '100000.00', '90000.00', '10000.00');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->postJson($this->endpoint(), [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'force' => true,
        ])
            ->assertOk()
            ->assertJsonPath('force', true)
            ->assertJsonPath('processed_count', 1)
            ->assertJsonPath('updated_count', 1)
            ->assertJsonPath('created_count', 0)
            ->assertJsonPath('recalculated_count', 1);

        $bonus = $seeded['bonus']->refresh();
        $run = $seeded['run']->refresh();

        $this->assertSame('80000.00', $bonus->amount);
        $this->assertSame('1600.00', $bonus->matched_pv);
        $this->assertSame('80000.00', $run->amount);
        $this->assertSame('1600.00', $run->used_left_pv);
        $this->assertSame('1600.00', $run->used_right_pv);
        $this->assertWalletBalance($partner, 'main', '72000.00');
        $this->assertWalletBalance($partner, 'deposit', '8000.00');
        $this->assertSame(1, BonusTransaction::query()->where('user_id', $partner->id)->where('bonus_type', 'binary')->count());
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'binary_bonus_main_adjustment',
            'direction' => 'debit',
            'amount' => '18000.00',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'binary_bonus_deposit_adjustment',
            'direction' => 'debit',
            'amount' => '2000.00',
        ]);
    }

    public function test_force_recalculation_is_idempotent_for_same_period(): void
    {
        [$dateFrom, $dateTo] = $this->period();
        $partner = $this->rootWithPackage('ELITE');
        ['left' => $leftBuyer, 'right' => $rightBuyer] = $this->makeBinaryBonusEligible($partner);
        $this->pv($partner, $leftBuyer, 'L', 1000);
        $this->pv($partner, $rightBuyer, 'R', 1000);
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $payload = [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'force' => true,
        ];

        $this->postJson($this->endpoint(), $payload)
            ->assertOk()
            ->assertJsonPath('created_count', 1);
        $this->postJson($this->endpoint(), $payload)
            ->assertOk()
            ->assertJsonPath('updated_count', 1);

        $this->assertSame(1, BinaryBonusRun::query()->where('user_id', $partner->id)->count());
        $this->assertSame(1, BonusTransaction::query()->where('user_id', $partner->id)->where('bonus_type', 'binary')->count());
        $this->assertWalletBalance($partner, 'main', '45000.00');
        $this->assertWalletBalance($partner, 'deposit', '5000.00');
        $this->assertSame(0, WalletTransaction::query()
            ->where('user_id', $partner->id)
            ->whereIn('type', ['binary_bonus_main_adjustment', 'binary_bonus_deposit_adjustment'])
            ->count());
    }

    public function test_super_admin_manual_binary_bonus_update_adjusts_split_wallets_and_notification(): void
    {
        [$dateFrom, $dateTo] = $this->period();
        $partner = $this->rootWithPackage('ELITE');
        $seeded = $this->seedPeriodBinaryPayout($partner, $dateFrom, $dateTo, '2000.00', '100000.00', '90000.00', '10000.00');
        $partner->notify(new BonusAccruedNotification($seeded['bonus']->refresh()));
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->patchJson("/api/admin/bonuses/{$seeded['bonus']->id}", [
            'amount' => 80000,
            'reason' => 'Исправление неправильного бинарного расчёта',
        ])
            ->assertOk()
            ->assertJsonPath('bonus.amount', '80000.00');

        $this->assertWalletBalance($partner, 'main', '72000.00');
        $this->assertWalletBalance($partner, 'deposit', '8000.00');

        $notification = $partner->notifications()->firstOrFail();
        $this->assertSame('80000.00', $notification->data['amount']);
        $this->assertStringContainsString('80 000', $notification->data['message']['ru']);
    }

    public function test_admin_without_super_admin_cannot_manually_update_bonus(): void
    {
        $partner = $this->rootWithPackage('ELITE');
        $bonus = BonusTransaction::query()->create([
            'user_id' => $partner->id,
            'bonus_type' => 'cashback',
            'amount' => '100.00',
            'status' => 'completed',
            'calculated_at' => now(),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->patchJson("/api/admin/bonuses/{$bonus->id}", [
            'amount' => 150,
            'reason' => 'Недостаточно прав',
        ])->assertForbidden();
    }

    public function test_super_admin_can_void_binary_bonus_reverse_wallets_and_remove_notification(): void
    {
        [$dateFrom, $dateTo] = $this->period();
        $partner = $this->rootWithPackage('ELITE');
        $seeded = $this->seedPeriodBinaryPayout($partner, $dateFrom, $dateTo, '2000.00', '100000.00', '90000.00', '10000.00');
        $partner->notify(new BonusAccruedNotification($seeded['bonus']->refresh()));

        Sanctum::actingAs($partner);
        $this->getJson('/api/dashboard/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
        $this->deleteJson("/api/admin/bonuses/{$seeded['bonus']->id}", [
            'reason' => 'Аннулирование неправильного бонуса',
        ])->assertOk();

        $this->assertSame('voided', $seeded['bonus']->refresh()->status);
        $this->assertSame('voided', $seeded['run']->refresh()->status);
        $this->assertWalletBalance($partner, 'main', '0.00');
        $this->assertWalletBalance($partner, 'deposit', '0.00');
        $this->assertSame(0, $partner->notifications()->count());

        Sanctum::actingAs($partner);
        $this->getJson('/api/dashboard/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'notifications');
    }

    public function test_period_recalculation_validates_required_date_range(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->postJson($this->endpoint(), [
            'date_to' => '2026-06-15',
            'force' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_from');

        $this->postJson($this->endpoint(), [
            'date_from' => '2026-06-15',
            'date_to' => '2026-06-01',
            'force' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(): array
    {
        return [
            CarbonImmutable::parse('2026-06-01')->startOfDay(),
            CarbonImmutable::parse('2026-06-15')->endOfDay(),
        ];
    }

    private function endpoint(): string
    {
        return '/api/admin/bonuses/binary/recalculate';
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

    private function pv(User $upline, User $buyer, string $branch, int $pv): PvTransaction
    {
        return PvTransaction::query()->create([
            'buyer_id' => $buyer->id,
            'upline_id' => $upline->id,
            'source' => 'test_period_binary_turnover',
            'branch' => $branch,
            'pv' => $pv,
            'is_bonusable' => true,
        ]);
    }

    /**
     * @return array{run: BinaryBonusRun, bonus: BonusTransaction}
     */
    private function seedPeriodBinaryPayout(
        User $partner,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        string $matchedPv,
        string $totalAmount,
        string $mainAmount,
        string $depositAmount,
    ): array {
        $bonusTransaction = BonusTransaction::query()->create([
            'user_id' => $partner->id,
            'bonus_type' => 'binary',
            'amount' => $totalAmount,
            'left_pv' => $matchedPv,
            'right_pv' => $matchedPv,
            'matched_pv' => $matchedPv,
            'status' => 'completed',
            'metadata' => [
                'seeded_wrong_period' => true,
                'main_amount' => $mainAmount,
                'deposit_amount' => $depositAmount,
            ],
            'calculated_at' => now(),
        ]);

        $run = BinaryBonusRun::query()->create([
            'user_id' => $partner->id,
            'bonus_transaction_id' => $bonusTransaction->id,
            'status' => 'completed',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'weak_leg_pv' => $matchedPv,
            'used_left_pv' => $matchedPv,
            'used_right_pv' => $matchedPv,
            'carry_left_pv' => '0.00',
            'carry_right_pv' => '0.00',
            'amount' => $totalAmount,
            'pending_amount' => '0.00',
            'metadata' => ['seeded_wrong_period' => true],
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
                'seeded_wrong_period' => true,
                'binary_bonus_run_id' => $run->id,
                'main_amount' => $mainAmount,
                'deposit_amount' => $depositAmount,
            ],
        ])->save();

        return [
            'run' => $run,
            'bonus' => $bonusTransaction,
        ];
    }

    private function assertWalletBalance(User $user, string $type, string $balance): void
    {
        $this->assertSame($balance, $user->wallets()->where('type', $type)->firstOrFail()->refresh()->balance);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerDetailBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_package_assignment_exposes_activity_amount_but_keeps_wallet_balances_zero(): void
    {
        [$partner, $payload] = $this->assignPackageAndGetPartnerPayload('START');

        $this->assertEquals(0, $payload['wallet_balance']);
        $this->assertEquals(100, $payload['package_activity_pv']);
        $this->assertEquals(50000, $payload['package_activity_amount']);
        $this->assertEquals(500, $payload['pv_money_rate']);
        $this->assertEquals(0, $payload['available_balance']);
        $this->assertEquals(0, $payload['total_earned']);
        $this->assertSame('100.00', $partner->refresh()->total_pv);
    }

    public function test_vip_package_assignment_calculates_activity_amount_from_activity_pv_without_crediting_balance(): void
    {
        [, $payload] = $this->assignPackageAndGetPartnerPayload('VIP');

        $this->assertEquals(300, $payload['package_activity_pv']);
        $this->assertEquals(150000, $payload['package_activity_amount']);
        $this->assertEquals(0, $payload['available_balance']);
        $this->assertEquals(0, $payload['total_earned']);
        $this->assertNotEquals(180000, $payload['package_activity_amount']);
    }

    public function test_elite_package_assignment_calculates_activity_amount_from_activity_pv_without_crediting_balance(): void
    {
        [, $payload] = $this->assignPackageAndGetPartnerPayload('ELITE');

        $this->assertEquals(500, $payload['package_activity_pv']);
        $this->assertEquals(250000, $payload['package_activity_amount']);
        $this->assertEquals(0, $payload['available_balance']);
        $this->assertEquals(0, $payload['total_earned']);
        $this->assertNotEquals(300000, $payload['package_activity_amount']);
    }

    public function test_package_price_is_not_used_as_pv_activity_amount_or_wallet_balance(): void
    {
        [, $payload] = $this->assignPackageAndGetPartnerPayload('START');

        $this->assertEquals(60000, $payload['current_package']['price']);
        $this->assertEquals(100, $payload['package_activity_pv']);
        $this->assertEquals(50000, $payload['package_activity_amount']);
        $this->assertEquals(0, $payload['available_balance']);
        $this->assertNotEquals(60000, $payload['package_activity_pv']);
        $this->assertNotEquals(60000, $payload['package_activity_amount']);
        $this->assertNotEquals(60000, $payload['available_balance']);
        $this->assertNotEquals(50000, $payload['available_balance']);
    }

    public function test_wallet_amount_remains_wallet_amount_after_package_assignment(): void
    {
        [, $payload] = $this->assignPackageAndGetPartnerPayload('START', walletBalance: 10000);

        $this->assertEquals(10000, $payload['wallet_balance']);
        $this->assertEquals(50000, $payload['package_activity_amount']);
        $this->assertEquals(10000, $payload['available_balance']);
        $this->assertEquals(10000, $payload['total_earned']);
    }

    /**
     * @return array{0: User, 1: array<string, mixed>}
     */
    private function assignPackageAndGetPartnerPayload(string $code, int $walletBalance = 0): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create(['total_pv' => 0]);
        $package = $this->createPackage($code);

        if ($walletBalance > 0) {
            app(WalletService::class)->createUserWallets($partner);
            $partner->wallets()->where('type', 'main')->firstOrFail()->forceFill([
                'balance' => $walletBalance,
            ])->save();
        }

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $package->id,
            'apply_business_effects' => true,
        ])->assertOk();

        $payload = $this->getJson("/api/admin/partners/{$partner->id}")
            ->assertOk()
            ->json('user');

        return [$partner, $payload];
    }

    private function createPackage(string $code): Package
    {
        $matrix = [
            'START' => ['price' => 60000, 'activity_pv' => 100, 'turnover_pv' => 100, 'binary_percent' => 7, 'sort_order' => 1],
            'VIP' => ['price' => 180000, 'activity_pv' => 300, 'turnover_pv' => 300, 'binary_percent' => 8, 'sort_order' => 2],
            'ELITE' => ['price' => 300000, 'activity_pv' => 500, 'turnover_pv' => 200, 'binary_percent' => 10, 'sort_order' => 3],
        ];

        $values = $matrix[$code];

        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $values['price'],
            'pv' => $values['activity_pv'],
            'activity_pv' => $values['activity_pv'],
            'turnover_pv' => $values['turnover_pv'],
            'referral_percent' => 10,
            'binary_percent' => $values['binary_percent'],
            'sort_order' => $values['sort_order'],
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}

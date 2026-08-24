<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerDetailBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_package_assignment_sets_personal_pv_without_partner_balance_credit(): void
    {
        [$partner, $payload] = $this->assignPackageAndGetPartnerPayload('START');

        $this->assertPackagePayload($payload, 100, 0);
        $this->assertSame('100.00', $partner->refresh()->total_pv);
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $partner->id)->count());
    }

    public function test_vip_package_assignment_sets_personal_pv_without_partner_balance_credit(): void
    {
        [$partner, $payload] = $this->assignPackageAndGetPartnerPayload('VIP');

        $this->assertPackagePayload($payload, 300, 0);
        $this->assertSame('300.00', $partner->refresh()->total_pv);
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $partner->id)->count());
    }

    public function test_elite_package_assignment_sets_personal_pv_without_partner_balance_credit(): void
    {
        [$partner, $payload] = $this->assignPackageAndGetPartnerPayload('ELITE');

        $this->assertPackagePayload($payload, 500, 0);
        $this->assertSame('500.00', $partner->refresh()->total_pv);
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $partner->id)->count());
    }

    public function test_package_activity_amount_is_not_used_as_wallet_balance(): void
    {
        [, $payload] = $this->assignPackageAndGetPartnerPayload('START');

        $this->assertEquals(60000, $payload['current_package']['price']);
        $this->assertEquals(100, $payload['package_activity_pv']);
        $this->assertEquals(50000, $payload['package_activity_amount']);
        $this->assertEquals(50000, $payload['pv_amount']);
        $this->assertEquals(0, $payload['wallet_balance']);
        $this->assertEquals(0, $payload['available_balance']);
        $this->assertEquals(0, $payload['total_balance']);
        $this->assertEquals(0, $payload['total_earned']);
        $this->assertNotEquals(60000, $payload['available_balance']);
    }

    public function test_existing_real_wallet_balance_is_preserved_without_package_balance_credit(): void
    {
        [, $payload] = $this->assignPackageAndGetPartnerPayload('START', walletBalance: 10000);

        $this->assertEquals(10000, $payload['wallet_balance']);
        $this->assertEquals(10000, $payload['available_balance']);
        $this->assertEquals(10000, $payload['total_balance']);
        $this->assertEquals(10000, $payload['total_earned']);
        $this->assertEquals(100, $payload['package_activity_pv']);
        $this->assertEquals(50000, $payload['package_activity_amount']);
    }

    public function test_partner_detail_returns_package_assignment_transaction(): void
    {
        [$partner] = $this->assignPackageAndGetPartnerPayload('START');

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->getJson("/api/admin/partners/{$partner->id}")
            ->assertOk();

        $transaction = $response->json('recent_transactions.0');

        $this->assertSame('package_assignment', $transaction['type']);
        $this->assertSame('60000.00', $transaction['amount']);
        $this->assertFalse($transaction['affects_balance']);
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'package_activity_credit',
        ]);
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
            Wallet::query()->create([
                'user_id' => $partner->id,
                'type' => 'main',
                'currency' => 'KZT',
                'balance' => $walletBalance,
                'hold_balance' => 0,
                'status' => 'active',
            ]);
        }

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $package->id,
            'apply_business_effects' => true,
        ])->assertOk();

        $payload = $this->getJson("/api/admin/partners/{$partner->id}")
            ->assertOk()
            ->json('user');

        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'package_activity_credit',
        ]);

        return [$partner, $payload];
    }

    private function assertPackagePayload(array $payload, int $expectedPv, int $expectedBalance): void
    {
        $this->assertEquals($expectedPv, $payload['total_pv']);
        $this->assertEquals($expectedPv, $payload['package_activity_pv']);
        $this->assertEquals($expectedPv * 500, $payload['package_activity_amount']);
        $this->assertEquals($expectedPv * 500, $payload['pv_amount']);
        $this->assertEquals($expectedBalance, $payload['wallet_balance']);
        $this->assertEquals($expectedBalance, $payload['available_balance']);
        $this->assertEquals($expectedBalance, $payload['total_balance']);
        $this->assertEquals($expectedBalance, $payload['total_earned']);
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

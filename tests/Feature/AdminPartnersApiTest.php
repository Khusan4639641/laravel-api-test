<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_partners_summary_counts_real_partners(): void
    {
        User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        User::factory()->count(3)->create(['role' => User::ROLE_USER]);

        $summary = $this->adminSummaryPayload();

        $this->assertSame(3, $summary['total_partners']);
    }

    public function test_admin_partners_summary_excludes_staff_roles_and_counts_user_status_and_packages(): void
    {
        $start = $this->createPackage('START');
        $vip = $this->createPackage('VIP');
        $elite = $this->createPackage('ELITE');

        User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
            'current_package_id' => $elite->id,
        ]);
        User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'account_status' => 'active',
            'current_package_id' => $vip->id,
        ]);
        User::factory()->create([
            'role' => User::ROLE_ACCOUNTANT,
            'account_status' => 'active',
            'current_package_id' => $elite->id,
        ]);
        User::factory()->create([
            'role' => User::ROLE_SUPPORT,
            'account_status' => 'active',
            'current_package_id' => $vip->id,
        ]);
        User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => $start->id,
        ]);
        User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => $vip->id,
        ]);
        User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'blocked',
            'current_package_id' => $elite->id,
        ]);

        $summary = $this->adminSummaryPayload();

        $this->assertSame(3, $summary['total_partners']);
        $this->assertSame(2, $summary['active_partners']);
        $this->assertSame(2, $summary['vip_elite_partners']);
    }

    public function test_admin_partners_summary_counts_active_partners(): void
    {
        User::factory()->count(2)->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
        ]);
        User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'blocked',
        ]);

        $summary = $this->adminSummaryPayload();

        $this->assertSame(2, $summary['active_partners']);
    }

    public function test_admin_partners_summary_counts_vip_and_elite_only(): void
    {
        $start = $this->createPackage('START');
        $vip = $this->createPackage('VIP');
        $elite = $this->createPackage('ELITE');

        User::factory()->create(['role' => User::ROLE_USER, 'current_package_id' => $start->id]);
        User::factory()->create(['role' => User::ROLE_USER, 'current_package_id' => $vip->id]);
        User::factory()->create(['role' => User::ROLE_USER, 'current_package_id' => $elite->id]);

        $summary = $this->adminSummaryPayload();

        $this->assertSame(2, $summary['vip_elite_partners']);
    }

    public function test_admin_partners_summary_sums_partner_wallet_balances(): void
    {
        $first = User::factory()->create(['role' => User::ROLE_USER]);
        $second = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->createWallet($first, 'main', 30000);
        $this->createWallet($first, 'bonus', 20000);
        $this->createWallet($second, 'main', 100000);
        $this->createWallet($second, 'deposit', 50000);
        $this->createWallet($admin, 'main', 999999);

        $summary = $this->adminSummaryPayload();

        $this->assertEquals(200000, $summary['total_balance']);
    }

    public function test_admin_partners_summary_is_not_affected_by_pagination(): void
    {
        User::factory()->count(20)->create(['role' => User::ROLE_USER]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->getJson('/api/admin/partners?per_page=10')
            ->assertOk();

        $this->assertCount(10, $response->json('data'));
        $this->assertSame(20, $response->json('summary.total_partners'));
    }

    public function test_user_and_support_cannot_access_admin_partners_summary(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));
        $this->getJson('/api/admin/partners')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPPORT]));
        $this->getJson('/api/admin/partners')->assertForbidden();
    }

    public function test_admin_partners_list_returns_invited_count(): void
    {
        $partner = User::factory()->create(['role' => 'user']);
        User::factory()->create(['role' => 'user', 'sponsor_id' => $partner->id]);
        User::factory()->create(['role' => 'user', 'sponsor_id' => $partner->id]);

        $payload = $this->adminPayloadFor($partner);

        $this->assertSame(2, $payload['invited_count']);
        $this->assertSame(2, $payload['referrals_count']);
    }

    public function test_admin_partners_list_returns_pv_fields(): void
    {
        $partner = User::factory()->create([
            'role' => 'user',
            'left_pv' => 9600,
            'right_pv' => 8700,
            'total_pv' => 3200,
        ]);

        $payload = $this->adminPayloadFor($partner);

        $this->assertEquals(9600, (float) $payload['left_pv']);
        $this->assertEquals(8700, (float) $payload['right_pv']);
        $this->assertEquals(3200, (float) $payload['total_pv']);
    }

    public function test_admin_partners_list_returns_wallet_balances(): void
    {
        $partner = User::factory()->create(['role' => 'user']);
        $this->createWallet($partner, 'main', 12000);
        $this->createWallet($partner, 'bonus', 900);
        $this->createWallet($partner, 'deposit', 900);

        $payload = $this->adminPayloadFor($partner);

        $this->assertEquals(12000, $payload['balance']);
        $this->assertEquals(900, $payload['bonus_balance']);
        $this->assertEquals(900, $payload['deposit_balance']);
        $this->assertEquals(13800, $payload['total_balance']);
    }

    public function test_admin_partners_list_includes_start_package_activity_amount_in_balance(): void
    {
        $payload = $this->assignPackageAndGetAdminPayload('START');

        $this->assertEquals(0, $payload['wallet_balance']);
        $this->assertEquals(100, $payload['package_activity_pv']);
        $this->assertEquals(50000, $payload['package_activity_amount']);
        $this->assertEquals(50000, $payload['balance']);
        $this->assertEquals(50000, $payload['available_balance']);
        $this->assertEquals(50000, $payload['total_balance']);
        $this->assertEquals(50000, $payload['total_earned']);
    }

    public function test_admin_partners_list_vip_package_balance_uses_activity_pv_amount(): void
    {
        $payload = $this->assignPackageAndGetAdminPayload('VIP');

        $this->assertEquals(300, $payload['package_activity_pv']);
        $this->assertEquals(150000, $payload['package_activity_amount']);
        $this->assertEquals(150000, $payload['balance']);
        $this->assertEquals(150000, $payload['total_balance']);
        $this->assertNotEquals(180000, $payload['balance']);
    }

    public function test_admin_partners_list_elite_package_balance_uses_activity_pv_amount(): void
    {
        $payload = $this->assignPackageAndGetAdminPayload('ELITE');

        $this->assertEquals(500, $payload['package_activity_pv']);
        $this->assertEquals(250000, $payload['package_activity_amount']);
        $this->assertEquals(250000, $payload['balance']);
        $this->assertEquals(250000, $payload['total_balance']);
        $this->assertNotEquals(300000, $payload['balance']);
    }

    public function test_admin_partners_list_package_price_is_not_used_for_balance(): void
    {
        $payload = $this->assignPackageAndGetAdminPayload('START');

        $this->assertEquals(60000, $payload['current_package']['price']);
        $this->assertEquals(50000, $payload['balance']);
        $this->assertEquals(50000, $payload['total_balance']);
        $this->assertNotEquals(60000, $payload['balance']);
        $this->assertNotEquals(60000, $payload['total_balance']);
    }

    public function test_admin_partners_list_adds_wallet_balance_to_package_activity_amount(): void
    {
        $payload = $this->assignPackageAndGetAdminPayload('START', walletBalance: 10000);

        $this->assertEquals(10000, $payload['wallet_balance']);
        $this->assertEquals(50000, $payload['package_activity_amount']);
        $this->assertEquals(60000, $payload['balance']);
        $this->assertEquals(60000, $payload['available_balance']);
        $this->assertEquals(60000, $payload['total_balance']);
        $this->assertEquals(60000, $payload['total_earned']);
    }

    public function test_admin_partners_summary_total_balance_includes_package_activity_amount(): void
    {
        $start = $this->createPackage('START');
        $vip = $this->createPackage('VIP');

        User::factory()->create(['role' => User::ROLE_USER, 'current_package_id' => $start->id]);
        User::factory()->create(['role' => User::ROLE_USER, 'current_package_id' => $vip->id]);
        User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'current_package_id' => $vip->id]);

        $summary = $this->adminSummaryPayload();

        $this->assertEquals(200000, $summary['total_balance']);
    }

    public function test_admin_partners_list_includes_sponsor_and_package_data(): void
    {
        $sponsor = User::factory()->create([
            'role' => 'user',
            'name' => 'Айдар Алиханов',
            'login' => 'aidar',
        ]);
        $package = Package::query()->create([
            'code' => 'START',
            'name' => 'START',
            'slug' => 'start',
            'price' => 1000,
            'pv' => 100,
            'status' => 'active',
            'is_active' => true,
        ]);
        $partner = User::factory()->create([
            'role' => 'user',
            'sponsor_id' => $sponsor->id,
            'current_package_id' => $package->id,
        ]);

        $payload = $this->adminPayloadFor($partner);

        $this->assertSame($sponsor->id, $payload['sponsor']['id']);
        $this->assertSame('Айдар Алиханов', $payload['sponsor']['name']);
        $this->assertSame('aidar', $payload['sponsor']['login']);
        $this->assertSame($package->id, $payload['package']['id']);
        $this->assertSame('START', $payload['package']['name']);
    }

    public function test_admin_partners_list_returns_account_status_and_mlm_status_separately(): void
    {
        $partner = User::factory()->create([
            'role' => 'user',
            'status' => 'leader',
            'account_status' => 'blocked',
        ]);

        $payload = $this->adminPayloadFor($partner);

        $this->assertSame('leader', $payload['status']);
        $this->assertSame('blocked', $payload['account_status']);
        $this->assertArrayNotHasKey('activity', $payload);
    }

    public function test_admin_partners_list_handles_missing_sponsor_wallet_and_package(): void
    {
        $partner = User::factory()->create([
            'role' => 'user',
            'sponsor_id' => null,
            'current_package_id' => null,
        ]);

        $payload = $this->adminPayloadFor($partner);

        $this->assertNull($payload['sponsor']);
        $this->assertNull($payload['package']);
        $this->assertEquals(0, $payload['package_activity_pv']);
        $this->assertEquals(0, $payload['package_activity_amount']);
        $this->assertEquals(0, $payload['balance']);
        $this->assertEquals(0, $payload['bonus_balance']);
        $this->assertEquals(0, $payload['deposit_balance']);
        $this->assertEquals(0, $payload['total_balance']);
    }

    public function test_normal_user_cannot_access_admin_partners_list(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_support_cannot_access_admin_partners_list(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'support']));

        $this->getJson('/api/admin/users')->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPayloadFor(User $partner): array
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $users = $this->getJson('/api/admin/users?per_page=100')
            ->assertOk()
            ->json('users');

        $payload = collect($users)->firstWhere('id', $partner->id);

        $this->assertIsArray($payload);

        return $payload;
    }

    private function createWallet(User $user, string $type, int $balance): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'currency' => 'KZT',
            'balance' => $balance,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function assignPackageAndGetAdminPayload(string $code, int $walletBalance = 0): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create([
            'role' => User::ROLE_USER,
            'total_pv' => 0,
        ]);
        $package = $this->createPackage($code);

        if ($walletBalance > 0) {
            $this->createWallet($partner, 'main', $walletBalance);
        }

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $package->id,
            'apply_business_effects' => true,
        ])->assertOk();

        $payload = collect($this->getJson('/api/admin/partners?per_page=100')
            ->assertOk()
            ->json('data'))
            ->firstWhere('id', $partner->id);

        $this->assertIsArray($payload);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function adminSummaryPayload(): array
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $summary = $this->getJson('/api/admin/partners')
            ->assertOk()
            ->json('summary');

        $this->assertIsArray($summary);

        return $summary;
    }

    private function createPackage(string $code): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => match ($code) {
                'VIP' => 180000,
                'ELITE' => 300000,
                default => 60000,
            },
            'pv' => match ($code) {
                'VIP' => 300,
                'ELITE' => 500,
                default => 100,
            },
            'activity_pv' => match ($code) {
                'VIP' => 300,
                'ELITE' => 500,
                default => 100,
            },
            'turnover_pv' => $code === 'ELITE' ? 200 : match ($code) {
                'VIP' => 300,
                default => 100,
            },
            'referral_percent' => 10,
            'binary_percent' => match ($code) {
                'VIP' => 8,
                'ELITE' => 10,
                default => 7,
            },
            'sort_order' => match ($code) {
                'VIP' => 2,
                'ELITE' => 3,
                default => 1,
            },
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}

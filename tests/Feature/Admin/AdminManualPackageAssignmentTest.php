<?php

namespace Tests\Feature\Admin;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BinaryTreeService;
use App\Services\WalletService;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminManualPackageAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manually_assign_package(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create();
        $start = $this->createPackage('START');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $start->id,
        ])
            ->assertOk()
            ->assertJsonPath('user.current_package.id', $start->id);

        $this->assertSame($start->id, $partner->refresh()->current_package_id);
    }

    public function test_manual_start_package_assignment_uses_package_pv_not_price(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $sponsor = User::factory()->create();
        $partner = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'total_pv' => 0,
        ]);
        $start = $this->createPackage('START');
        $tree = app(BinaryTreeService::class);
        $tree->placeUser($sponsor);
        $tree->placeUser($partner, $sponsor, 'L');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $start->id,
            'apply_business_effects' => true,
        ])->assertOk();

        $partner->refresh();

        $this->assertSame($start->id, $partner->current_package_id);
        $this->assertSame('100.00', $partner->total_pv);
        $this->assertNotSame('60000.00', $partner->total_pv);
        $this->assertSame('0.00', $partner->left_pv);
        $this->assertSame('0.00', $partner->right_pv);
        $this->assertSame('100.00', $sponsor->refresh()->left_pv);
        $this->assertSame('100.00', $sponsor->remaining_left_pv);
    }

    public function test_manual_vip_package_assignment_uses_package_pv_not_price(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $sponsor = User::factory()->create();
        $partner = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'total_pv' => 0,
        ]);
        $vip = $this->createPackage('VIP');
        $tree = app(BinaryTreeService::class);
        $tree->placeUser($sponsor);
        $tree->placeUser($partner, $sponsor, 'R');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $vip->id,
            'apply_business_effects' => true,
        ])->assertOk();

        $partner->refresh();

        $this->assertSame($vip->id, $partner->current_package_id);
        $this->assertSame('300.00', $partner->total_pv);
        $this->assertNotSame('180000.00', $partner->total_pv);
        $this->assertSame('0.00', $partner->left_pv);
        $this->assertSame('0.00', $partner->right_pv);
        $this->assertSame('300.00', $sponsor->refresh()->right_pv);
        $this->assertSame('300.00', $sponsor->remaining_right_pv);
    }

    public function test_manual_elite_package_assignment_uses_activity_pv_and_turnover_pv_correctly(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $sponsor = User::factory()->create();
        $partner = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'total_pv' => 0,
        ]);
        $elite = $this->createPackage('ELITE');

        $tree = app(BinaryTreeService::class);
        $tree->placeUser($sponsor);
        $tree->placeUser($partner, $sponsor, 'L');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $elite->id,
            'apply_business_effects' => true,
        ])
            ->assertOk()
            ->assertJsonPath('user.current_package.id', $elite->id);

        $partner->refresh();
        $sponsor->refresh();

        $this->assertSame('500.00', $partner->total_pv);
        $this->assertNotSame('300000.00', $partner->total_pv);
        $this->assertSame('0.00', $partner->left_pv);
        $this->assertSame('0.00', $partner->right_pv);
        $this->assertSame('200.00', $sponsor->left_pv);
        $this->assertSame('0.00', $sponsor->remaining_left_pv);
        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'referral')->count());
        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'binary')->count());
    }

    public function test_manual_package_assignment_accrues_referral_bonus_from_pv_volume_not_price(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $sponsor = User::factory()->create();
        $partner = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);
        $vip = $this->createPackage('VIP');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $vip->id,
            'apply_business_effects' => true,
        ])->assertOk();

        $bonus = BonusTransaction::query()
            ->where('user_id', $sponsor->id)
            ->where('source_user_id', $partner->id)
            ->where('bonus_type', 'referral')
            ->first();

        $this->assertNotNull($bonus);
        $this->assertSame('15000.00', $bonus->amount);
        $this->assertSame('150000.00', $bonus->metadata['base_amount']);
        $this->assertNotSame('18000.00', $bonus->amount);
    }

    public function test_manual_package_assignment_updates_binary_volume(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $sponsor = User::factory()->create();
        $partner = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);
        $start = $this->createPackage('START');

        $tree = app(BinaryTreeService::class);
        $tree->placeUser($sponsor);
        $tree->placeUser($partner, $sponsor, 'L');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $start->id,
        ])->assertOk();

        $sponsor->refresh();

        $this->assertSame('100.00', $sponsor->left_pv);
        $this->assertSame('100.00', $sponsor->remaining_left_pv);
    }

    public function test_manual_package_assignment_without_business_effects_does_not_change_pv(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $sponsor = User::factory()->create();
        $partner = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'total_pv' => 42,
        ]);
        $start = $this->createPackage('START');
        $tree = app(BinaryTreeService::class);
        $tree->placeUser($sponsor);
        $tree->placeUser($partner, $sponsor, 'L');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $start->id,
            'apply_business_effects' => false,
        ])
            ->assertOk()
            ->assertJsonPath('user.current_package.id', $start->id);

        $this->assertSame($start->id, $partner->refresh()->current_package_id);
        $this->assertSame('42.00', $partner->total_pv);
        $this->assertSame('0.00', $partner->left_pv);
        $this->assertSame('0.00', $partner->right_pv);
        $this->assertSame('0.00', $sponsor->refresh()->left_pv);
        $this->assertSame('0.00', $sponsor->remaining_left_pv);
        $this->assertSame(0, BonusTransaction::query()->count());
    }

    public function test_manual_package_assignment_does_not_add_package_price_to_partner_wallet_balance(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create(['total_pv' => 0]);
        $start = $this->createPackage('START');

        app(WalletService::class)->createUserWallets($partner);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $start->id,
            'apply_business_effects' => true,
        ])
            ->assertOk()
            ->assertJsonPath('user.balance', 0)
            ->assertJsonPath('user.available_balance', 0)
            ->assertJsonPath('user.total_earned', 0)
            ->assertJsonPath('user.package_activity_amount', 50000);

        $mainWallet = $partner->wallets()->where('type', 'main')->firstOrFail();

        $this->assertSame('0.00', $mainWallet->refresh()->balance);
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $partner->id,
            'amount' => '60000.00',
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $partner->id,
            'amount' => '50000.00',
        ]);
        $this->assertSame(0, WalletTransaction::query()->where('user_id', $partner->id)->count());
    }

    public function test_package_matrix_uses_price_activity_pv_and_turnover_pv_separately(): void
    {
        $this->seed(PackageSeeder::class);

        $this->assertPackageMatrix('START', '60000.00', '100.00', '100.00', '50000.00', '10.00', '7.00');
        $this->assertPackageMatrix('VIP', '180000.00', '300.00', '300.00', '150000.00', '10.00', '8.00');
        $this->assertPackageMatrix('ELITE', '300000.00', '500.00', '200.00', '250000.00', '10.00', '10.00');
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

    private function assertPackageMatrix(
        string $code,
        string $price,
        string $activityPv,
        string $turnoverPv,
        string $volumeAmount,
        string $referralPercent,
        string $binaryPercent,
    ): void {
        $package = Package::query()->where('code', $code)->firstOrFail();

        $this->assertSame($price, $package->price);
        $this->assertSame($activityPv, $package->activity_pv);
        $this->assertSame($turnoverPv, $package->turnover_pv);
        $this->assertSame($volumeAmount, $package->volumeAmount());
        $this->assertSame($referralPercent, $package->referral_percent);
        $this->assertSame($binaryPercent, $package->binary_percent);
    }
}

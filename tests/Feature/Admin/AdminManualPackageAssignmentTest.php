<?php

namespace Tests\Feature\Admin;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use App\Services\BinaryTreeService;
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
        $start = $this->createPackage('START', 60000, 100);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $start->id,
        ])
            ->assertOk()
            ->assertJsonPath('user.current_package.id', $start->id);

        $this->assertSame($start->id, $partner->refresh()->current_package_id);
    }

    public function test_manual_package_assignment_updates_partner_pv_turnover(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create([
            'total_pv' => 0,
        ]);
        $start = $this->createPackage('START', 60000, 100);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $start->id,
        ])->assertOk();

        $this->assertSame('100.00', $partner->refresh()->total_pv);
    }

    public function test_manual_package_assignment_accrues_referral_bonus_to_sponsor(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $sponsor = User::factory()->create();
        $partner = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);
        $vip = $this->createPackage('VIP', 180000, 300);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $vip->id,
        ])->assertOk();

        $bonus = BonusTransaction::query()
            ->where('user_id', $sponsor->id)
            ->where('source_user_id', $partner->id)
            ->where('bonus_type', 'referral')
            ->first();

        $this->assertNotNull($bonus);
        $this->assertSame('13500.00', $bonus->amount);
        $this->assertSame('135000.00', $bonus->metadata['base_amount']);
    }

    public function test_manual_package_assignment_updates_binary_volume(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $sponsor = User::factory()->create();
        $partner = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);
        $start = $this->createPackage('START', 60000, 100);

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
        $partner = User::factory()->create([
            'total_pv' => 0,
        ]);
        $start = $this->createPackage('START', 60000, 100);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $start->id,
            'apply_business_effects' => false,
        ])
            ->assertOk()
            ->assertJsonPath('user.current_package.id', $start->id);

        $this->assertSame($start->id, $partner->refresh()->current_package_id);
        $this->assertSame('0.00', $partner->total_pv);
        $this->assertSame(0, BonusTransaction::query()->count());
    }

    public function test_elite_manual_assignment_excludes_first_two_hundred_pv_from_referral_and_binary_volume(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $sponsor = User::factory()->create();
        $partner = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'total_pv' => 0,
        ]);
        $elite = $this->createPackage('ELITE', 300000, 500);

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

        $this->assertSame('200.00', $partner->total_pv);
        $this->assertSame('200.00', $sponsor->left_pv);
        $this->assertSame('0.00', $sponsor->remaining_left_pv);
        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'referral')->count());
    }

    private function createPackage(string $code, int $price, int $pv): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $price,
            'pv' => $pv,
            'activity_pv' => $pv,
            'turnover_pv' => $code === 'ELITE' ? 200 : $pv,
            'referral_percent' => 10,
            'binary_percent' => 8,
            'sort_order' => $code === 'START' ? 1 : ($code === 'VIP' ? 2 : 3),
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}

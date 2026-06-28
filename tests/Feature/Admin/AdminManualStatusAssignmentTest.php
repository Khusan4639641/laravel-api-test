<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\StatusBonusDefinition;
use App\Models\User;
use App\Models\UserStatusBonus;
use App\Models\WalletTransaction;
use Database\Seeders\StatusBonusDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminManualStatusAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_status_change_with_bonus_effects_creates_status_bonus_transaction(): void
    {
        $elite = $this->createPackage('ELITE');
        $partner = User::factory()->create([
            'current_package_id' => $elite->id,
            'status' => 'user',
        ]);
        StatusBonusDefinition::query()->create([
            'status_code' => 'manager',
            'status_name' => 'Manager',
            'threshold_pv' => 1000,
            'reward_type' => 'cash',
            'amount' => 25000,
            'cash_amount' => 25000,
            'currency' => 'KZT',
            'reward_text' => 'Manager bonus',
            'is_cash_bonus' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->patchJson("/api/admin/partners/{$partner->id}/status", [
            'status' => 'manager',
            'apply_bonus_effects' => true,
        ])
            ->assertOk()
            ->assertJsonPath('user.status', 'manager')
            ->assertJsonPath('user.available_balance', 25000)
            ->assertJsonPath('recent_transactions.0.type', 'status_bonus')
            ->assertJsonPath('recent_transactions.0.amount', '25000.00');

        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $partner->id,
            'bonus_type' => 'status_bonus',
            'amount' => '25000.00',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'status_bonus',
            'amount' => '25000.00',
            'description' => 'Статусный бонус: Manager',
        ]);
    }

    public function test_super_admin_manual_director_assignment_awards_elite_status_bonus_without_checkbox(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $partner = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 5000,
            'right_pv' => 5000,
            'status' => 'user',
        ]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        Sanctum::actingAs($superAdmin);

        $this->patchJson("/api/admin/partners/{$partner->id}/status", [
            'status' => 'director',
        ])
            ->assertOk()
            ->assertJsonPath('user.status', 'director')
            ->assertJsonPath('user.available_balance', 250000)
            ->assertJsonPath('recent_transactions.0.type', 'status_bonus')
            ->assertJsonPath('recent_transactions.0.amount', '250000.00');

        $this->assertDatabaseHas('user_status_bonuses', [
            'user_id' => $partner->id,
            'status_code' => 'director',
            'amount' => '250000.00',
        ]);
        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $partner->id,
            'bonus_type' => 'status_bonus',
            'amount' => '250000.00',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'status_bonus',
            'direction' => 'credit',
            'amount' => '250000.00',
            'description' => 'Статусный бонус: Директор',
        ]);
        $this->assertDatabaseHas('admin_action_logs', [
            'admin_id' => $superAdmin->id,
            'target_user_id' => $partner->id,
            'action' => 'manual_status_assigned',
        ]);
        $this->assertDatabaseHas('admin_action_logs', [
            'admin_id' => $superAdmin->id,
            'target_user_id' => $partner->id,
            'action' => 'status_bonus_paid',
        ]);
    }

    public function test_repeated_manual_director_assignment_does_not_duplicate_status_bonus(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $partner = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 5000,
            'right_pv' => 5000,
            'status' => 'user',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->patchJson("/api/admin/partners/{$partner->id}/status", [
            'status' => 'director',
        ])->assertOk();

        $this->patchJson("/api/admin/partners/{$partner->id}/status", [
            'status' => 'director',
        ])->assertOk();

        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $partner->id)
            ->where('type', 'status_bonus')
            ->where('amount', '250000.00')
            ->count());
        $this->assertSame(1, UserStatusBonus::query()
            ->where('user_id', $partner->id)
            ->where('status_code', 'director')
            ->count());
    }

    public function test_manual_director_assignment_does_not_pay_non_elite_user(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $start = $this->createPackage('START');
        $partner = User::factory()->create([
            'current_package_id' => $start->id,
            'left_pv' => 5000,
            'right_pv' => 5000,
            'status' => 'user',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->patchJson("/api/admin/partners/{$partner->id}/status", [
            'status' => 'director',
        ])
            ->assertOk()
            ->assertJsonPath('user.status', 'director')
            ->assertJsonPath('user.available_balance', 0);

        $this->assertDatabaseCount('bonus_transactions', 0);
        $this->assertSame(0, WalletTransaction::query()
            ->where('user_id', $partner->id)
            ->where('type', 'status_bonus')
            ->count());
        $this->assertSame(0, UserStatusBonus::query()
            ->where('user_id', $partner->id)
            ->where('status_code', 'director')
            ->count());
    }

    public function test_regular_admin_manual_director_assignment_does_not_trigger_status_bonus(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $partner = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 5000,
            'right_pv' => 5000,
            'status' => 'user',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->patchJson("/api/admin/partners/{$partner->id}/status", [
            'status' => 'director',
            'apply_bonus_effects' => true,
        ])
            ->assertOk()
            ->assertJsonPath('user.status', 'director')
            ->assertJsonPath('user.available_balance', 0);

        $this->assertDatabaseCount('bonus_transactions', 0);
        $this->assertSame(0, WalletTransaction::query()
            ->where('user_id', $partner->id)
            ->where('type', 'status_bonus')
            ->count());
    }

    public function test_manual_status_change_without_bonus_effects_does_not_create_transaction(): void
    {
        $partner = User::factory()->create(['status' => 'user']);
        StatusBonusDefinition::query()->create([
            'status_code' => 'manager',
            'status_name' => 'Manager',
            'threshold_pv' => 1000,
            'reward_type' => 'cash',
            'amount' => 25000,
            'cash_amount' => 25000,
            'currency' => 'KZT',
            'reward_text' => 'Manager bonus',
            'is_cash_bonus' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->patchJson("/api/admin/partners/{$partner->id}/status", [
            'status' => 'manager',
            'apply_bonus_effects' => false,
        ])
            ->assertOk()
            ->assertJsonPath('user.status', 'manager')
            ->assertJsonPath('user.available_balance', 0);

        $this->assertSame(0, WalletTransaction::query()->where('user_id', $partner->id)->count());
        $this->assertDatabaseCount('bonus_transactions', 0);
    }

    private function createPackage(string $code): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $code === 'ELITE' ? 300000 : 60000,
            'pv' => $code === 'ELITE' ? 500 : 100,
            'activity_pv' => $code === 'ELITE' ? 500 : 100,
            'turnover_pv' => $code === 'ELITE' ? 200 : 100,
            'referral_percent' => 10,
            'binary_percent' => $code === 'ELITE' ? 10 : 7,
            'sort_order' => $code === 'ELITE' ? 3 : 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}

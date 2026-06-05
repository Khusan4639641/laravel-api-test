<?php

namespace Tests\Feature\Admin;

use App\Models\StatusBonusDefinition;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminManualStatusAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_status_change_with_bonus_effects_creates_status_bonus_transaction(): void
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
            'apply_bonus_effects' => true,
        ])
            ->assertOk()
            ->assertJsonPath('user.status', 'manager')
            ->assertJsonPath('user.available_balance', 25000)
            ->assertJsonPath('recent_transactions.0.type', 'status_bonus')
            ->assertJsonPath('recent_transactions.0.amount', '25000.00');

        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $partner->id,
            'bonus_type' => 'status',
            'amount' => '25000.00',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'status_bonus',
            'amount' => '25000.00',
            'description' => 'Manual status assignment: manager',
        ]);
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
}

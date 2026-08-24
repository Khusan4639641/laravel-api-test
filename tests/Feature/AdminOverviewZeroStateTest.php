<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOverviewZeroStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_ignores_super_admin_in_partner_count(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('users.total', 0)
            ->assertJsonPath('users.active', 0)
            ->assertJsonPath('users.inactive', 0);
    }

    public function test_overview_returns_zero_values_when_no_business_data(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        User::factory()->create(['role' => User::ROLE_ADMIN]);
        User::factory()->create(['role' => User::ROLE_ACCOUNTANT]);
        User::factory()->create(['role' => User::ROLE_SUPPORT]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/overview')->assertOk();

        $this->assertSame(0, $response->json('users.total'));
        $this->assertSame(0, $response->json('users.active'));
        $this->assertSame(0, $response->json('users.inactive'));
        $this->assertEquals(0, $response->json('orders.revenue'));
        $this->assertEquals(0, $response->json('bonuses.paid'));
        $this->assertEquals(0, $response->json('withdrawals.pending_amount'));
        $this->assertEquals(0, $response->json('orders.total_pv'));
        $this->assertEquals(0, $response->json('orders.packages_sold'));
        $this->assertSame([], $response->json('recent_transactions'));
    }

    public function test_overview_updates_after_adding_real_partner(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('users.total', 1)
            ->assertJsonPath('users.active', 1)
            ->assertJsonPath('users.inactive', 0);
    }

    public function test_overview_updates_after_partner_transaction(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create(['role' => User::ROLE_USER]);
        $transaction = $this->createTransaction($partner, 22000);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('transactions.total', 1)
            ->assertJsonPath('recent_transactions.0.id', $transaction->id)
            ->assertJsonPath('recent_transactions.0.user.id', $partner->id);
    }

    public function test_accountant_can_access_overview(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ACCOUNTANT]));

        $this->getJson('/api/admin/overview')->assertOk();
    }

    public function test_normal_user_cannot_access_overview(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));

        $this->getJson('/api/admin/overview')->assertForbidden();
    }

    private function createTransaction(User $user, int $amount): WalletTransaction
    {
        $wallet = Wallet::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'type' => 'main',
            ],
            [
                'currency' => 'KZT',
                'balance' => $amount,
                'hold_balance' => 0,
                'status' => 'active',
            ]
        );

        return WalletTransaction::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'manual_adjustment',
            'direction' => 'credit',
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'status' => 'completed',
            'description' => 'Overview test transaction',
        ]);
    }
}

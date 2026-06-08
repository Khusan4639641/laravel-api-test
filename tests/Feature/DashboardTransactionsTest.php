<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_package_purchase_transaction(): void
    {
        $user = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")->assertOk();

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('transactions.0.type', 'package_activation')
            ->assertJsonPath('transactions.0.amount', '60000.00')
            ->assertJsonPath('transactions.0.affects_balance', false);
    }

    public function test_package_transaction_does_not_increase_dashboard_income_summary(): void
    {
        $user = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")->assertOk();

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('summary.total_earned', '0')
            ->assertJsonPath('summary.available', '0');
    }

    public function test_balance_remains_zero_after_package_purchase_without_bonuses(): void
    {
        $user = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")
            ->assertOk()
            ->assertJsonPath('user.available_balance', 0)
            ->assertJsonPath('user.total_earned', 0);

        $this->assertSame('0.00', $user->wallets()->where('type', 'main')->firstOrFail()->balance);
    }

    public function test_real_bonus_increases_dashboard_income_summary(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 10000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'referral_bonus',
            'direction' => 'credit',
            'amount' => 10000,
            'balance_before' => 0,
            'balance_after' => 10000,
            'status' => 'completed',
            'affects_balance' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('summary.total_earned', '10000')
            ->assertJsonPath('summary.available', '10000');
    }

    private function package(string $code): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => 60000,
            'pv' => 100,
            'activity_pv' => 100,
            'turnover_pv' => 100,
            'referral_percent' => 10,
            'binary_percent' => 7,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}

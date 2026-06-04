<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardOverviewBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_available_balance_excludes_package_activity_amount(): void
    {
        $start = $this->createPackage('START', 60000, 100, 100);
        $user = User::factory()->create([
            'current_package_id' => $start->id,
            'total_pv' => 100,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('balances.available', '0')
            ->assertJsonPath('user.available_balance', 0)
            ->assertJsonPath('user.total_earned', 0)
            ->assertJsonPath('user.package_activity_amount', 50000);
    }

    public function test_dashboard_available_balance_uses_main_wallet_only(): void
    {
        $user = User::factory()->create();
        $this->createWallet($user, 'main', 1000);
        $this->createWallet($user, 'bonus', 200);
        $this->createWallet($user, 'deposit', 5000);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('balances.available', '1000')
            ->assertJsonPath('balances.withdrawable', '1000')
            ->assertJsonPath('balances.total_wallet_balance', '6200')
            ->assertJsonPath('user.available_balance', 1000)
            ->assertJsonPath('user.total_balance', 6200);
    }

    private function createPackage(string $code, int $price, int $activityPv, int $turnoverPv): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $price,
            'pv' => $activityPv,
            'activity_pv' => $activityPv,
            'turnover_pv' => $turnoverPv,
            'referral_percent' => 10,
            'binary_percent' => 7,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
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
}

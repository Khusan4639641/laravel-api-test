<?php

namespace Tests\Feature;

use App\Models\BinaryBonusRun;
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
            ->assertJsonPath('user.package_activity_pv', 100)
            ->assertJsonPath('user.package_activity_amount', 0);
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

    public function test_dashboard_exposes_pending_binary_amount_without_adding_it_to_available_balance(): void
    {
        $user = User::factory()->create();
        $this->createWallet($user, 'main', 1000);

        BinaryBonusRun::query()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'period_start' => now(),
            'period_end' => now()->addDays(15),
            'weak_leg_pv' => 100,
            'used_left_pv' => 100,
            'used_right_pv' => 100,
            'carry_left_pv' => 0,
            'carry_right_pv' => 0,
            'amount' => 3500,
            'pending_amount' => 3500,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('balances.available', '1000')
            ->assertJsonPath('balances.pending_binary', '3500');

        $this->getJson('/api/dashboard/bonuses')
            ->assertOk()
            ->assertJsonPath('summary.pending_binary', '3500');
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

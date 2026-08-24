<?php

namespace Tests\Feature;

use App\Models\BinaryBonusRun;
use App\Models\BonusTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardEarningsSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_returns_referral_total(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->bonus($user, 'referral', 1000);
        $this->bonus($user, 'referral', 250);
        $this->bonus($otherUser, 'referral', 999);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/earnings-summary')
            ->assertOk()
            ->assertJsonPath('summary.referral_total', '1250')
            ->assertJsonPath('summary.by_type.referral', '1250')
            ->assertJsonPath('summary.total_earned', '1250');
    }

    public function test_summary_returns_binary_total(): void
    {
        $user = User::factory()->create();
        $this->bonus($user, 'binary', 5000);
        $this->bonus($user, 'status', 2000);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/earnings-summary')
            ->assertOk()
            ->assertJsonPath('summary.binary_total', '5000')
            ->assertJsonPath('summary.status_total', '2000')
            ->assertJsonPath('summary.total_earned', '7000');
    }

    public function test_summary_returns_pending_binary(): void
    {
        $user = User::factory()->create();

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
        BinaryBonusRun::query()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'period_start' => now()->subDays(20),
            'period_end' => now()->subDays(5),
            'weak_leg_pv' => 50,
            'used_left_pv' => 50,
            'used_right_pv' => 50,
            'carry_left_pv' => 0,
            'carry_right_pv' => 0,
            'amount' => 1000,
            'pending_amount' => 1000,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/earnings-summary')
            ->assertOk()
            ->assertJsonPath('summary.pending_binary', '3500');
    }

    public function test_summary_returns_deposit_balance_and_withdrawal_totals(): void
    {
        $user = User::factory()->create();
        $mainWallet = $this->wallet($user, 'main', 1000);
        $this->wallet($user, 'deposit', 2400);

        WithdrawalRequest::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $mainWallet->id,
            'amount' => 300,
            'fee_amount' => 0,
            'net_amount' => 300,
            'currency' => 'KZT',
            'status' => 'pending',
            'payment_method' => 'card_account',
        ]);
        WithdrawalRequest::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $mainWallet->id,
            'amount' => 500,
            'fee_amount' => 0,
            'net_amount' => 500,
            'currency' => 'KZT',
            'status' => 'approved',
            'payment_method' => 'card_account',
        ]);
        WithdrawalRequest::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $mainWallet->id,
            'amount' => 700,
            'fee_amount' => 0,
            'net_amount' => 700,
            'currency' => 'KZT',
            'status' => 'rejected',
            'payment_method' => 'card_account',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/earnings-summary')
            ->assertOk()
            ->assertJsonPath('summary.available_to_withdraw', '1000')
            ->assertJsonPath('summary.deposit_balance', '2400')
            ->assertJsonPath('summary.pending_withdrawal', '300')
            ->assertJsonPath('summary.withdrawn_total', '500');
    }

    public function test_summary_excludes_pv_from_money(): void
    {
        $user = User::factory()->create([
            'left_pv' => 100000,
            'right_pv' => 50000,
            'total_pv' => 150000,
        ]);
        $this->wallet($user, 'main', 0);

        Sanctum::actingAs($user);

        $summary = $this->getJson('/api/dashboard/earnings-summary')
            ->assertOk()
            ->assertJsonPath('summary.total_earned', '0')
            ->assertJsonPath('summary.available_to_withdraw', '0')
            ->json('summary');

        $this->assertArrayNotHasKey('pv', $summary);
        $this->assertArrayNotHasKey('total_pv', $summary);
        $this->assertArrayNotHasKey('left_pv', $summary);
        $this->assertArrayNotHasKey('right_pv', $summary);
    }

    private function bonus(User $user, string $type, int $amount): BonusTransaction
    {
        return BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => $type,
            'amount' => $amount,
            'status' => 'completed',
            'calculated_at' => now(),
        ]);
    }

    private function wallet(User $user, string $type, int $balance): Wallet
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

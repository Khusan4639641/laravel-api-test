<?php

namespace Tests\Feature;

use App\Models\BinaryBonusRun;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTransactionsSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_operation_turnover_includes_package_transactions(): void
    {
        $partner = User::factory()->create();
        $this->transaction($partner, 'package_assignment', 60000, 'neutral', false);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/transactions')
            ->assertOk()
            ->assertJsonPath('summary.operation_turnover', '60000')
            ->assertJsonPath('summary.total_credited', '0');
    }

    public function test_total_credited_excludes_package_transactions(): void
    {
        $partner = User::factory()->create();
        $this->transaction($partner, 'package_assignment', 60000, 'neutral', false);
        $this->transaction($partner, 'package_activation', 180000, 'neutral', false);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/transactions')
            ->assertOk()
            ->assertJsonPath('summary.operation_turnover', '240000')
            ->assertJsonPath('summary.total_credited', '0');
    }

    public function test_total_credited_includes_balance_affecting_income(): void
    {
        $partner = User::factory()->create();
        $this->transaction($partner, 'package_assignment', 60000, 'neutral', false);
        $this->transaction($partner, 'referral_bonus', 10000, 'credit', true);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/transactions')
            ->assertOk()
            ->assertJsonPath('summary.operation_turnover', '70000')
            ->assertJsonPath('summary.total_credited', '10000');
    }

    public function test_total_paid_includes_approved_withdrawal_transactions(): void
    {
        $partner = User::factory()->create();
        $this->transaction($partner, 'withdrawal_approved', 15000, 'neutral', false);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/transactions')
            ->assertOk()
            ->assertJsonPath('summary.total_paid', '15000');
    }

    public function test_pending_includes_pending_withdrawals_and_binary(): void
    {
        $partner = User::factory()->create();
        $wallet = $this->wallet($partner);
        WithdrawalRequest::query()->create([
            'user_id' => $partner->id,
            'wallet_id' => $wallet->id,
            'amount' => 7000,
            'fee_amount' => 0,
            'net_amount' => 7000,
            'currency' => 'KZT',
            'status' => 'pending',
            'payment_method' => 'card_account',
        ]);
        BinaryBonusRun::query()->create([
            'user_id' => $partner->id,
            'status' => 'pending',
            'period_start' => now(),
            'period_end' => now()->addDays(15),
            'weak_leg_pv' => 100,
            'used_left_pv' => 100,
            'used_right_pv' => 100,
            'carry_left_pv' => 0,
            'carry_right_pv' => 0,
            'amount' => 3000,
            'pending_amount' => 3000,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/transactions')
            ->assertOk()
            ->assertJsonPath('summary.pending', '10000');
    }

    private function transaction(User $user, string $type, int $amount, string $direction, bool $affectsBalance): WalletTransaction
    {
        $wallet = $this->wallet($user);

        return WalletTransaction::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $affectsBalance ? $amount : 0,
            'status' => 'completed',
            'affects_balance' => $affectsBalance,
            'description' => 'Summary test transaction',
        ]);
    }

    private function wallet(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate([
            'user_id' => $user->id,
            'type' => 'main',
        ], [
            'currency' => 'KZT',
            'balance' => 0,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
    }
}

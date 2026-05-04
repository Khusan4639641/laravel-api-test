<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'user',
        ]));

        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_admin_can_list_users_and_withdrawals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $wallet = $this->createMainWallet($user, 750, 250);
        WithdrawalRequest::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 250,
            'fee_amount' => 0,
            'net_amount' => 250,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(2, 'users');

        $this->getJson('/api/admin/withdrawals')
            ->assertOk()
            ->assertJsonCount(1, 'withdrawals')
            ->assertJsonPath('withdrawals.0.status', 'pending');
    }

    public function test_admin_can_approve_withdrawal_and_spend_hold_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $wallet = $this->createMainWallet($user, 750, 250);
        $withdrawal = $this->createWithdrawal($user, $wallet, 250);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/approve")
            ->assertOk()
            ->assertJsonPath('withdrawal.status', 'approved');

        $wallet->refresh();
        $withdrawal->refresh();
        $walletTransaction = WalletTransaction::query()
            ->where('type', 'withdrawal_approve')
            ->firstOrFail();

        $this->assertSame('750.00', $wallet->balance);
        $this->assertSame('0.00', $wallet->hold_balance);
        $this->assertSame('approved', $withdrawal->status);
        $this->assertNotNull($withdrawal->processed_at);
        $this->assertSame('debit', $walletTransaction->direction);
        $this->assertSame('250.00', $walletTransaction->amount);
        $this->assertSame('750.00', $walletTransaction->balance_before);
        $this->assertSame('750.00', $walletTransaction->balance_after);
    }

    public function test_admin_can_reject_withdrawal_and_return_hold_to_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $wallet = $this->createMainWallet($user, 750, 250);
        $withdrawal = $this->createWithdrawal($user, $wallet, 250);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/reject", [
            'reason' => 'Invalid payment details',
        ])->assertOk()
            ->assertJsonPath('withdrawal.status', 'rejected')
            ->assertJsonPath('withdrawal.admin_comment', 'Invalid payment details');

        $wallet->refresh();
        $withdrawal->refresh();
        $walletTransaction = WalletTransaction::query()
            ->where('type', 'withdrawal_reject')
            ->firstOrFail();

        $this->assertSame('1000.00', $wallet->balance);
        $this->assertSame('0.00', $wallet->hold_balance);
        $this->assertSame('rejected', $withdrawal->status);
        $this->assertSame('Invalid payment details', $withdrawal->admin_comment);
        $this->assertSame('credit', $walletTransaction->direction);
        $this->assertSame('250.00', $walletTransaction->amount);
        $this->assertSame('750.00', $walletTransaction->balance_before);
        $this->assertSame('1000.00', $walletTransaction->balance_after);
    }

    public function test_admin_cannot_process_non_pending_withdrawal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $wallet = $this->createMainWallet($user, 750, 250);
        $withdrawal = $this->createWithdrawal($user, $wallet, 250, 'approved');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('withdrawal');
    }

    private function createMainWallet(User $user, int $balance, int $holdBalance): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'USD',
            'balance' => $balance,
            'hold_balance' => $holdBalance,
            'status' => 'active',
        ]);
    }

    private function createWithdrawal(User $user, Wallet $wallet, int $amount, string $status = 'pending'): WithdrawalRequest
    {
        return WithdrawalRequest::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'fee_amount' => 0,
            'net_amount' => $amount,
            'currency' => 'USD',
            'status' => $status,
        ]);
    }
}

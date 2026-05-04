<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WithdrawalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_withdrawal_request_from_main_wallet(): void
    {
        $user = User::factory()->create();
        $wallet = $this->createMainWallet($user, 1000);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/withdrawals', [
            'amount' => 250,
            'payment_method' => 'card',
            'payment_details' => [
                'card_last4' => '4242',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('withdrawal.status', 'pending')
            ->assertJsonPath('withdrawal.amount', '250.00')
            ->assertJsonPath('withdrawal.net_amount', '250.00');

        $wallet->refresh();
        $withdrawal = WithdrawalRequest::query()->firstOrFail();
        $walletTransaction = WalletTransaction::query()->firstOrFail();

        $this->assertSame('750.00', $wallet->balance);
        $this->assertSame('250.00', $wallet->hold_balance);
        $this->assertSame($user->id, $withdrawal->user_id);
        $this->assertSame($wallet->id, $withdrawal->wallet_id);
        $this->assertSame('pending', $withdrawal->status);
        $this->assertSame('withdrawal_hold', $walletTransaction->type);
        $this->assertSame('debit', $walletTransaction->direction);
        $this->assertSame('250.00', $walletTransaction->amount);
        $this->assertSame('1000.00', $walletTransaction->balance_before);
        $this->assertSame('750.00', $walletTransaction->balance_after);
        $this->assertSame(WithdrawalRequest::class, $walletTransaction->source_type);
        $this->assertSame($withdrawal->id, $walletTransaction->source_id);
    }

    public function test_user_cannot_withdraw_more_than_main_wallet_balance(): void
    {
        $user = User::factory()->create();
        $wallet = $this->createMainWallet($user, 100);

        Sanctum::actingAs($user);

        $this->postJson('/api/withdrawals', [
            'amount' => 250,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $wallet->refresh();

        $this->assertSame('100.00', $wallet->balance);
        $this->assertSame('0.00', $wallet->hold_balance);
        $this->assertDatabaseCount('withdrawal_requests', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_withdrawal_amount_must_be_greater_than_zero(): void
    {
        $user = User::factory()->create();
        $this->createMainWallet($user, 100);

        Sanctum::actingAs($user);

        $this->postJson('/api/withdrawals', [
            'amount' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_user_can_list_own_withdrawals(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $wallet = $this->createMainWallet($user, 1000);
        $otherWallet = $this->createMainWallet($otherUser, 1000);

        WithdrawalRequest::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 100,
            'fee_amount' => 0,
            'net_amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);
        WithdrawalRequest::query()->create([
            'user_id' => $otherUser->id,
            'wallet_id' => $otherWallet->id,
            'amount' => 200,
            'fee_amount' => 0,
            'net_amount' => 200,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/withdrawals');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'withdrawals')
            ->assertJsonPath('withdrawals.0.user_id', $user->id)
            ->assertJsonPath('withdrawals.0.amount', '100.00');
    }

    private function createMainWallet(User $user, int $balance): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'USD',
            'balance' => $balance,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
    }
}

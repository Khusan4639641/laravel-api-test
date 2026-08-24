<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WithdrawalTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_withdrawal_to_ip_account(): void
    {
        $user = $this->createUserWithMainWallet();

        Sanctum::actingAs($user);

        $this->postJson('/api/withdrawals', [
            'amount' => 10000,
            'payment_method' => 'ip_account',
            'payment_details' => [
                'label' => 'Счет ИП',
            ],
        ])->assertCreated()
            ->assertJsonPath('withdrawal.payment_method', 'ip_account')
            ->assertJsonPath('withdrawal.payout_period_days', 14);
    }

    public function test_user_can_create_withdrawal_to_card_account(): void
    {
        $user = $this->createUserWithMainWallet();

        Sanctum::actingAs($user);

        $this->postJson('/api/withdrawals', [
            'amount' => 10000,
            'payment_method' => 'card_account',
            'payment_details' => [
                'label' => 'Карта партнера',
            ],
        ])->assertCreated()
            ->assertJsonPath('withdrawal.payment_method', 'card_account')
            ->assertJsonPath('withdrawal.payout_period_days', 14);
    }

    public function test_invalid_payment_method_fails(): void
    {
        $user = $this->createUserWithMainWallet();

        Sanctum::actingAs($user);

        $this->postJson('/api/withdrawals', [
            'amount' => 10000,
            'payment_method' => 'cash',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_payout_period_is_fourteen_days(): void
    {
        $user = $this->createUserWithMainWallet();

        Sanctum::actingAs($user);

        $this->postJson('/api/withdrawals', [
            'amount' => 10000,
            'payment_method' => 'card_account',
        ])->assertCreated()
            ->assertJsonPath('withdrawal.payout_period_days', 14);

        $this->assertDatabaseHas('withdrawal_requests', [
            'user_id' => $user->id,
            'payout_period_days' => 14,
        ]);
    }

    private function createUserWithMainWallet(): User
    {
        $user = User::factory()->create();

        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 50000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        return $user;
    }
}

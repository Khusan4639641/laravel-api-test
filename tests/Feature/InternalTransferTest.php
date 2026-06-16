<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\PartnerTransfer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\InternalWalletTransferService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class InternalTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_transfer_from_main_balance_to_deposit_balance(): void
    {
        $user = User::factory()->create();
        $this->wallet($user, 'main', 10000);
        $this->wallet($user, 'deposit', 500);

        Sanctum::actingAs($user);

        $this->postJson('/api/dashboard/wallets/internal-transfer', [
            'from' => 'main',
            'to' => 'deposit',
            'amount' => 1000,
            'comment' => 'Пополнение депозитного баланса',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Перевод между счетами выполнен')
            ->assertJsonPath('data.from', 'main')
            ->assertJsonPath('data.to', 'deposit')
            ->assertJsonPath('data.amount', '1000.00')
            ->assertJsonPath('data.main_balance', '9000.00')
            ->assertJsonPath('data.deposit_balance', '1500.00');

        $this->assertSame('9000.00', $this->walletFor($user, 'main')->balance);
        $this->assertSame('1500.00', $this->walletFor($user, 'deposit')->balance);

        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'main_to_deposit_debit')
            ->where('direction', 'debit')
            ->where('amount', 1000)
            ->count());
        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'main_to_deposit_credit')
            ->where('direction', 'credit')
            ->where('amount', 1000)
            ->count());
    }

    public function test_user_cannot_transfer_more_than_main_balance(): void
    {
        $user = User::factory()->create();
        $this->wallet($user, 'main', 1000);
        $this->wallet($user, 'deposit', 500);

        Sanctum::actingAs($user);

        $this->postJson('/api/dashboard/wallets/internal-transfer', [
            'from' => 'main',
            'to' => 'deposit',
            'amount' => 2000,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Недостаточно средств на основном балансе');

        $this->assertSame('1000.00', $this->walletFor($user, 'main')->balance);
        $this->assertSame('500.00', $this->walletFor($user, 'deposit')->balance);
        $this->assertSame(0, WalletTransaction::query()->whereIn('type', [
            'main_to_deposit_debit',
            'main_to_deposit_credit',
        ])->count());
    }

    public function test_user_cannot_transfer_from_deposit_to_main_balance(): void
    {
        $user = User::factory()->create();
        $this->wallet($user, 'main', 1000);
        $this->wallet($user, 'deposit', 5000);

        Sanctum::actingAs($user);

        $this->postJson('/api/dashboard/wallets/internal-transfer', [
            'from' => 'deposit',
            'to' => 'main',
            'amount' => 1000,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Перевод с депозитного баланса на основной недоступен');

        $this->assertSame('1000.00', $this->walletFor($user, 'main')->balance);
        $this->assertSame('5000.00', $this->walletFor($user, 'deposit')->balance);
        $this->assertSame(0, WalletTransaction::query()->whereIn('type', [
            'main_to_deposit_debit',
            'main_to_deposit_credit',
        ])->count());
    }

    public function test_deposit_balance_cannot_be_sent_to_partner(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $this->wallet($sender, 'main', 0);
        $this->wallet($sender, 'deposit', 10000);
        $this->wallet($recipient, 'main', 0);

        Sanctum::actingAs($sender);

        $this->postJson('/api/dashboard/wallet/transfers', [
            'from' => 'deposit',
            'recipient_user_id' => $recipient->id,
            'amount' => 1000,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Депозитный баланс нельзя переводить партнёрам');

        $this->assertSame('0.00', $this->walletFor($sender, 'main')->balance);
        $this->assertSame('10000.00', $this->walletFor($sender, 'deposit')->balance);
        $this->assertSame('0.00', $this->walletFor($recipient, 'main')->balance);
        $this->assertSame(0, PartnerTransfer::query()->count());
        $this->assertSame(0, WalletTransaction::query()->whereIn('type', [
            'partner_transfer_out',
            'partner_transfer_in',
        ])->count());
    }

    public function test_deposit_balance_cannot_be_withdrawn(): void
    {
        $user = User::factory()->create();
        $this->wallet($user, 'main', 0);
        $this->wallet($user, 'deposit', 10000);

        Sanctum::actingAs($user);

        $this->postJson('/api/dashboard/withdrawals', [
            'amount' => 1000,
            'method' => 'card_account',
        ])->assertUnprocessable();

        $this->assertSame('0.00', $this->walletFor($user, 'main')->balance);
        $this->assertSame('10000.00', $this->walletFor($user, 'deposit')->balance);
        $this->assertSame(0, WithdrawalRequest::query()->count());
        $this->assertSame(0, WalletTransaction::query()->where('type', 'withdrawal_hold')->count());
    }

    public function test_cashback_total_is_not_treated_as_wallet_balance(): void
    {
        $user = User::factory()->create();
        $this->wallet($user, 'main', 1000);
        $this->wallet($user, 'deposit', 0);

        BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => 'cashback',
            'amount' => 10000,
            'status' => 'completed',
            'calculated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/earnings-summary')
            ->assertOk()
            ->assertJsonPath('summary.cashback_total', '10000')
            ->assertJsonPath('summary.available_to_withdraw', '1000');

        $this->postJson('/api/dashboard/wallets/internal-transfer', [
            'from' => 'main',
            'to' => 'deposit',
            'amount' => 5000,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Недостаточно средств на основном балансе');

        $this->assertSame('1000.00', $this->walletFor($user, 'main')->balance);
        $this->assertSame('0.00', $this->walletFor($user, 'deposit')->balance);
    }

    public function test_internal_transfer_is_atomic(): void
    {
        $user = User::factory()->create();
        $this->wallet($user, 'main', 10000);
        $this->wallet($user, 'deposit', 500);

        $this->app->bind(InternalWalletTransferService::class, fn ($app) => new class($app->make(WalletService::class)) extends InternalWalletTransferService
        {
            protected function afterMainDebit(WalletTransaction $debitTransaction): void
            {
                throw new RuntimeException('Simulated internal transfer failure');
            }
        });

        Sanctum::actingAs($user);

        $this->postJson('/api/dashboard/wallets/internal-transfer', [
            'from' => 'main',
            'to' => 'deposit',
            'amount' => 2000,
        ])->assertStatus(500);

        $this->assertSame('10000.00', $this->walletFor($user, 'main')->balance);
        $this->assertSame('500.00', $this->walletFor($user, 'deposit')->balance);
        $this->assertSame(0, WalletTransaction::query()->whereIn('type', [
            'main_to_deposit_debit',
            'main_to_deposit_credit',
        ])->count());
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

    private function walletFor(User $user, string $type): Wallet
    {
        return $user->wallets()->where('type', $type)->firstOrFail()->refresh();
    }
}

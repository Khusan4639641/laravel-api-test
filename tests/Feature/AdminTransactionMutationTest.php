<?php

namespace Tests\Feature;

use App\Models\TransactionAdminAudit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTransactionMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_credit_transaction_amount_and_notification(): void
    {
        $partner = User::factory()->create();
        $wallet = $this->wallet($partner, 10000);
        $transaction = $this->transaction($partner, $wallet, 'credit', 10000, 0, 10000);
        $this->notification($partner, $transaction);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->patchJson("/api/admin/transactions/{$transaction->id}", [
            'amount' => 15000,
            'reason' => 'Корректировка суммы по заявке администратора',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Транзакция обновлена')
            ->assertJsonPath('data.transaction.id', $transaction->id)
            ->assertJsonPath('data.transaction.amount', '15000.00');

        $this->assertSame('15000.00', $transaction->refresh()->amount);
        $this->assertSame('15000.00', $wallet->refresh()->balance);

        $notification = $partner->notifications()->firstOrFail();
        $this->assertSame('15000.00', $notification->data['amount']);
        $this->assertSame($transaction->id, $notification->data['transaction_id']);
        $this->assertSame('Начислен реферальный бонус: 15 000 ₸.', $notification->data['message']['ru']);

        $this->assertDatabaseHas('transaction_admin_audits', [
            'transaction_id' => $transaction->id,
            'action' => TransactionAdminAudit::ACTION_AMOUNT_UPDATED,
            'old_amount' => '10000.00',
            'new_amount' => '15000.00',
        ]);
    }

    public function test_admin_cannot_delete_transaction(): void
    {
        $partner = User::factory()->create();
        $wallet = $this->wallet($partner, 10000);
        $transaction = $this->transaction($partner, $wallet, 'credit', 10000, 0, 10000);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->deleteJson("/api/admin/transactions/{$transaction->id}", [
            'reason' => 'Удаление ошибочной транзакции',
        ])->assertForbidden();
    }

    public function test_super_admin_can_delete_credit_transaction_and_notification_disappears(): void
    {
        $partner = User::factory()->create();
        $wallet = $this->wallet($partner, 10000);
        $transaction = $this->transaction($partner, $wallet, 'credit', 10000, 0, 10000);
        $this->notification($partner, $transaction);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->deleteJson("/api/admin/transactions/{$transaction->id}", [
            'reason' => 'Удаление ошибочной транзакции',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Транзакция удалена')
            ->assertJsonPath('data.transaction_id', $transaction->id);

        $this->assertSame(WalletTransaction::STATUS_VOIDED, $transaction->refresh()->status);
        $this->assertSame('0.00', $wallet->refresh()->balance);
        $this->assertSame(0, $partner->notifications()->count());

        Sanctum::actingAs($partner);

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonCount(0, 'transactions');
        $this->getJson('/api/dashboard/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'notifications');

        $this->assertDatabaseHas('transaction_admin_audits', [
            'transaction_id' => $transaction->id,
            'action' => TransactionAdminAudit::ACTION_DELETED,
        ]);
    }

    public function test_delete_debit_transaction_reverses_balance(): void
    {
        $partner = User::factory()->create();
        $wallet = $this->wallet($partner, 50000);
        $transaction = $this->transaction($partner, $wallet, 'debit', 50000, 100000, 50000);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->deleteJson("/api/admin/transactions/{$transaction->id}", [
            'reason' => 'Удаление ошибочной debit транзакции',
        ])->assertOk();

        $this->assertSame(WalletTransaction::STATUS_VOIDED, $transaction->refresh()->status);
        $this->assertSame('100000.00', $wallet->refresh()->balance);
    }

    public function test_update_debit_transaction_adjusts_balance_by_delta(): void
    {
        $partner = User::factory()->create();
        $wallet = $this->wallet($partner, 50000);
        $transaction = $this->transaction($partner, $wallet, 'debit', 50000, 100000, 50000);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->patchJson("/api/admin/transactions/{$transaction->id}", [
            'amount' => 30000,
            'reason' => 'Корректировка debit суммы',
        ])->assertOk();

        $this->assertSame('30000.00', $transaction->refresh()->amount);
        $this->assertSame('70000.00', $wallet->refresh()->balance);
        $this->assertSame('70000.00', $transaction->balance_after);
    }

    public function test_user_cannot_update_or_delete_transaction(): void
    {
        $partner = User::factory()->create();
        $wallet = $this->wallet($partner, 10000);
        $transaction = $this->transaction($partner, $wallet, 'credit', 10000, 0, 10000);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));

        $this->patchJson("/api/admin/transactions/{$transaction->id}", [
            'amount' => 15000,
            'reason' => 'Недоступно пользователю',
        ])->assertForbidden();
        $this->deleteJson("/api/admin/transactions/{$transaction->id}", [
            'reason' => 'Недоступно пользователю',
        ])->assertForbidden();
    }

    public function test_reason_is_required_for_update_and_delete(): void
    {
        $partner = User::factory()->create();
        $wallet = $this->wallet($partner, 10000);
        $transaction = $this->transaction($partner, $wallet, 'credit', 10000, 0, 10000);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->patchJson("/api/admin/transactions/{$transaction->id}", [
            'amount' => 15000,
        ])->assertUnprocessable();

        $this->deleteJson("/api/admin/transactions/{$transaction->id}")
            ->assertUnprocessable();
    }

    public function test_notification_unread_count_updates_after_delete(): void
    {
        $partner = User::factory()->create();
        $wallet = $this->wallet($partner, 10000);
        $transaction = $this->transaction($partner, $wallet, 'credit', 10000, 0, 10000);
        $this->notification($partner, $transaction);

        Sanctum::actingAs($partner);
        $this->getJson('/api/dashboard/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
        $this->deleteJson("/api/admin/transactions/{$transaction->id}", [
            'reason' => 'Удаление ошибочной транзакции',
        ])->assertOk();

        Sanctum::actingAs($partner);
        $this->getJson('/api/dashboard/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'notifications');
    }

    private function wallet(User $user, int $balance): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => $balance,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
    }

    private function transaction(User $user, Wallet $wallet, string $direction, int $amount, int $before, int $after): WalletTransaction
    {
        return WalletTransaction::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => $direction === 'credit' ? 'referral_bonus' : 'withdrawal_approved',
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'status' => WalletTransaction::STATUS_COMPLETED,
            'affects_balance' => true,
            'description' => 'Mutation test transaction',
        ]);
    }

    private function notification(User $user, WalletTransaction $transaction): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'database',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'type' => 'referral_bonus',
                'title' => ['ru' => 'Реферальный бонус', 'en' => 'Referral bonus'],
                'message' => ['ru' => 'Начислен реферальный бонус: 10 000 ₸.', 'en' => 'Referral bonus accrued: 10 000 ₸.'],
                'amount' => '10000.00',
                'currency' => 'KZT',
                'transaction_id' => $transaction->id,
                'wallet_transaction_id' => $transaction->id,
            ], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

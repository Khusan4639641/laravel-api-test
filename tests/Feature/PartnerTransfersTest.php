<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\PartnerTransfer;
use App\Models\PvTransaction;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\PartnerTransferService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class PartnerTransfersTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_partner_can_transfer_money_to_another_partner(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(175000, 0);

        Sanctum::actingAs($sender);

        $this->postJson('/api/dashboard/wallet/transfers', [
            'recipient_user_id' => $recipient->id,
            'amount' => 50000,
            'comment' => 'Test transfer',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Перевод выполнен')
            ->assertJsonPath('data.sender_id', $sender->id)
            ->assertJsonPath('data.recipient_id', $recipient->id)
            ->assertJsonPath('data.amount', 50000)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.sender_available_balance', 125000)
            ->assertJsonPath('data.recipient_available_balance', 50000);

        $this->assertSame('125000.00', $this->mainWallet($sender)->balance);
        $this->assertSame('50000.00', $this->mainWallet($recipient)->balance);
        $this->assertSame(1, PartnerTransfer::query()->where('status', 'completed')->count());
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $sender->id)->where('type', 'partner_transfer_out')->count());
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $recipient->id)->where('type', 'partner_transfer_in')->count());
    }

    public function test_transfer_does_not_create_withdrawal_request(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(175000, 0);

        Sanctum::actingAs($sender);

        $this->postJson('/api/dashboard/wallet/transfers', [
            'recipient_user_id' => $recipient->id,
            'amount' => 50000,
        ])->assertCreated();

        $this->assertSame(0, WithdrawalRequest::query()->count());
    }

    public function test_transfer_does_not_require_admin_or_accountant_approval(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(175000, 0);

        Sanctum::actingAs($sender);

        $this->postJson('/api/dashboard/wallet/transfers', [
            'recipient_user_id' => $recipient->id,
            'amount' => 50000,
        ])->assertCreated();

        $transfer = PartnerTransfer::query()->firstOrFail();

        $this->assertSame('completed', $transfer->status);
        $this->assertSame('125000.00', $this->mainWallet($sender)->balance);
        $this->assertSame('50000.00', $this->mainWallet($recipient)->balance);
    }

    public function test_sender_cannot_transfer_more_than_available_balance(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(1000, 0);

        Sanctum::actingAs($sender);

        $this->postJson('/api/dashboard/wallet/transfers', [
            'recipient_user_id' => $recipient->id,
            'amount' => 50000,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Недостаточно средств');

        $this->assertSame('1000.00', $this->mainWallet($sender)->balance);
        $this->assertSame('0.00', $this->mainWallet($recipient)->balance);
    }

    public function test_sender_cannot_transfer_to_self(): void
    {
        [$sender] = $this->partnersWithWallets(1000, 0);

        Sanctum::actingAs($sender);

        $this->postJson('/api/dashboard/wallet/transfers', [
            'recipient_user_id' => $sender->id,
            'amount' => 500,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Нельзя переводить средства самому себе');
    }

    public function test_sender_cannot_transfer_to_deleted_recipient(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(1000, 0);
        $recipient->delete();

        Sanctum::actingAs($sender);

        $this->postJson('/api/dashboard/wallet/transfers', [
            'recipient_user_id' => $recipient->id,
            'amount' => 500,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Получатель недоступен для перевода');
    }

    public function test_sender_cannot_transfer_to_blocked_recipient(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(1000, 0);
        $recipient->forceFill(['account_status' => 'blocked'])->save();

        Sanctum::actingAs($sender);

        $this->postJson('/api/dashboard/wallet/transfers', [
            'recipient_user_id' => $recipient->id,
            'amount' => 500,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Получатель недоступен для перевода');
    }

    public function test_transfer_is_atomic(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(175000, 0);

        $this->app->bind(PartnerTransferService::class, fn ($app) => new class($app->make(WalletService::class)) extends PartnerTransferService
        {
            protected function afterSenderDebit(PartnerTransfer $transfer, WalletTransaction $senderTransaction): void
            {
                throw new RuntimeException('Simulated transfer failure');
            }
        });

        Sanctum::actingAs($sender);

        $this->postJson('/api/dashboard/wallet/transfers', [
            'recipient_user_id' => $recipient->id,
            'amount' => 50000,
        ])->assertStatus(500);

        $this->assertSame('175000.00', $this->mainWallet($sender)->balance);
        $this->assertSame('0.00', $this->mainWallet($recipient)->balance);
        $this->assertSame(0, PartnerTransfer::query()->count());
        $this->assertSame(0, WalletTransaction::query()->whereIn('type', ['partner_transfer_out', 'partner_transfer_in'])->count());
    }

    public function test_transfer_is_visible_in_sender_transactions(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(175000, 0);
        $this->transfer($sender, $recipient, 50000);

        Sanctum::actingAs($sender);

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'partner_transfer_out')
            ->assertJsonPath('data.0.direction', 'debit')
            ->assertJsonPath('data.0.amount', '50000.00');
    }

    public function test_transfer_is_visible_in_recipient_transactions(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(175000, 0);
        $this->transfer($sender, $recipient, 50000);

        Sanctum::actingAs($recipient);

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'partner_transfer_in')
            ->assertJsonPath('data.0.direction', 'credit')
            ->assertJsonPath('data.0.amount', '50000.00');
    }

    public function test_partner_transfer_in_does_not_increase_bonus_or_earned_totals(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(175000, 0);
        $this->transfer($sender, $recipient, 50000);

        Sanctum::actingAs($recipient);

        $this->getJson('/api/dashboard/earnings-summary')
            ->assertOk()
            ->assertJsonPath('summary.total_earned', '0')
            ->assertJsonPath('summary.referral_total', '0')
            ->assertJsonPath('summary.binary_total', '0')
            ->assertJsonPath('summary.status_total', '0')
            ->assertJsonPath('summary.bonus_x2_total', '0');

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('summary.total_earned', '0');

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('balances.total_earned', '0')
            ->assertJsonPath('user.total_earned', 0);

        $this->assertSame(0, BonusTransaction::query()->count());
        $this->assertSame(0, PvTransaction::query()->count());
    }

    public function test_partner_search_excludes_current_user(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(1000, 0);

        Sanctum::actingAs($sender);

        $ids = collect($this->getJson('/api/dashboard/partners/search?q=partner&limit=20')
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();

        $this->assertNotContains($sender->id, $ids);
        $this->assertContains($recipient->id, $ids);
    }

    public function test_partner_search_returns_active_partners_by_name_login_email_phone_and_id(): void
    {
        $sender = User::factory()->create(['name' => 'Current Partner', 'login' => 'current-transfer']);
        $target = User::factory()->create([
            'name' => 'Балдыран Садыкова',
            'login' => 'safi-baldyran',
            'email' => 'baldyran@example.test',
        ]);
        UserProfile::query()->create([
            'user_id' => $target->id,
            'phone' => '+77771234567',
        ]);

        Sanctum::actingAs($sender);

        foreach (['Балдыран', 'safi-baldyran', 'baldyran@example.test', '+77771234567', (string) $target->id] as $query) {
            $this->getJson('/api/dashboard/partners/search?q='.urlencode($query).'&limit=20')
                ->assertOk()
                ->assertJsonFragment(['id' => $target->id]);
        }
    }

    public function test_double_submit_with_same_idempotency_key_does_not_create_duplicate_transfer(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(175000, 0);
        $payload = [
            'recipient_user_id' => $recipient->id,
            'amount' => 50000,
            'idempotency_key' => 'transfer-key-1',
        ];

        Sanctum::actingAs($sender);

        $this->postJson('/api/dashboard/wallet/transfers', $payload)->assertCreated();
        $this->postJson('/api/dashboard/wallet/transfers', $payload)->assertCreated();

        $this->assertSame('125000.00', $this->mainWallet($sender)->balance);
        $this->assertSame('50000.00', $this->mainWallet($recipient)->balance);
        $this->assertSame(1, PartnerTransfer::query()->count());
        $this->assertSame(1, WalletTransaction::query()->where('type', 'partner_transfer_out')->count());
        $this->assertSame(1, WalletTransaction::query()->where('type', 'partner_transfer_in')->count());
    }

    public function test_admin_can_see_partner_transfer_transactions(): void
    {
        [$sender, $recipient] = $this->partnersWithWallets(175000, 0);
        $this->transfer($sender, $recipient, 50000);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/transactions?type=partner_transfer_in')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'partner_transfer_in')
            ->assertJsonPath('data.0.user_id', $recipient->id);

        $this->getJson("/api/admin/partners/{$sender->id}/transactions?limit=10")
            ->assertOk()
            ->assertJsonPath('transactions.0.type', 'partner_transfer_out');
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function partnersWithWallets(int $senderBalance, int $recipientBalance): array
    {
        $sender = User::factory()->create([
            'name' => 'Khusan Bakhronov',
            'login' => 'sender-partner',
            'role' => User::ROLE_USER,
        ]);
        $recipient = User::factory()->create([
            'name' => 'Recipient Partner',
            'login' => 'recipient-partner',
            'role' => User::ROLE_USER,
        ]);

        $this->wallet($sender, $senderBalance);
        $this->wallet($recipient, $recipientBalance);

        return [$sender, $recipient];
    }

    private function transfer(User $sender, User $recipient, int $amount): PartnerTransfer
    {
        return app(PartnerTransferService::class)->transfer($sender, $recipient->id, $amount);
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

    private function mainWallet(User $user): Wallet
    {
        return $user->wallets()->where('type', 'main')->firstOrFail()->refresh();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTransactionsSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_search_transactions_by_transaction_id(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create();
        $transaction = $this->createTransaction($partner, 15000);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/transactions?search={$transaction->id}")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $transaction->id,
                'transaction_type' => 'wallet_transaction',
            ]);
    }

    public function test_super_admin_can_search_transactions_by_partner_user_id(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        User::factory()->count(10)->create();
        $partner = User::factory()->create();
        $otherPartner = User::factory()->create();
        $firstTransaction = $this->createTransaction($partner, 10000);
        $secondTransaction = $this->createTransaction($partner, 25000);
        $this->createTransaction($otherPartner, 50000);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/transactions?search={$partner->id}")
            ->assertOk()
            ->assertJsonCount(2, 'transactions');

        $expectedIds = [$firstTransaction->id, $secondTransaction->id];
        $actualIds = collect($response->json('transactions'))->pluck('id')->all();
        sort($expectedIds);
        sort($actualIds);

        $this->assertSame($expectedIds, $actualIds);
    }

    public function test_search_by_user_id_does_not_return_other_users_transactions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        User::factory()->count(10)->create();
        $partner = User::factory()->create();
        $otherPartner = User::factory()->create();
        $this->createTransaction($partner, 10000);
        $otherTransaction = $this->createTransaction($otherPartner, 50000);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/transactions?search={$partner->id}")
            ->assertOk();

        $this->assertNotContains($otherTransaction->id, collect($response->json('transactions'))->pluck('id')->all());
    }

    public function test_search_by_text_still_matches_user_identity(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create([
            'name' => 'Searchable Partner',
            'login' => 'searchable-login',
            'email' => 'searchable@safilife.test',
        ]);
        $transaction = $this->createTransaction($partner, 12000);
        $this->createTransaction(User::factory()->create(), 8000);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/transactions?search=searchable-login')
            ->assertOk()
            ->assertJsonCount(1, 'transactions')
            ->assertJsonPath('transactions.0.id', $transaction->id);
    }

    public function test_accountant_can_search_transactions(): void
    {
        $accountant = User::factory()->create(['role' => User::ROLE_ACCOUNTANT]);
        $partner = User::factory()->create();
        $transaction = $this->createTransaction($partner, 17000);

        Sanctum::actingAs($accountant);

        $this->getJson("/api/admin/transactions?search={$transaction->id}")
            ->assertOk()
            ->assertJsonPath('transactions.0.id', $transaction->id);
    }

    public function test_user_cannot_access_admin_transactions(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));

        $this->getJson('/api/admin/transactions?search=1')->assertForbidden();
    }

    private function createTransaction(User $user, int $amount): WalletTransaction
    {
        $wallet = Wallet::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'type' => 'main',
            ],
            [
                'currency' => 'KZT',
                'balance' => $amount,
                'hold_balance' => 0,
                'status' => 'active',
            ]
        );

        return WalletTransaction::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'manual_adjustment',
            'direction' => 'credit',
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'status' => 'completed',
            'description' => 'Search test transaction',
        ]);
    }
}

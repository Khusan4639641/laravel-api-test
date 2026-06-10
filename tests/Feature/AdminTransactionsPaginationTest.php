<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTransactionsPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_transactions_endpoint_returns_paginated_data(): void
    {
        $partner = User::factory()->create();
        $this->createTransactions($partner, 25);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/transactions?per_page=20')
            ->assertOk()
            ->assertJsonCount(20, 'transactions')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 25);
    }

    public function test_per_page_20_returns_20_rows(): void
    {
        $partner = User::factory()->create();
        $this->createTransactions($partner, 25);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/transactions?per_page=20')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonCount(20, 'transactions');
    }

    public function test_page_2_returns_second_page(): void
    {
        $partner = User::factory()->create();
        $transactions = $this->createTransactions($partner, 25);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->getJson('/api/admin/transactions?page=2&per_page=20')
            ->assertOk()
            ->assertJsonCount(5, 'transactions')
            ->assertJsonPath('meta.current_page', 2);

        $ids = collect($response->json('transactions'))->pluck('id')->all();

        $this->assertContains($transactions[20]->id, $ids);
        $this->assertNotContains($transactions[0]->id, $ids);
    }

    public function test_meta_total_is_correct(): void
    {
        $partner = User::factory()->create();
        $this->createTransactions($partner, 23);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/transactions?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 23)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_search_finds_transaction_globally(): void
    {
        $this->createTransactions(User::factory()->create(), 30);
        $targetUser = User::factory()->create([
            'name' => 'Global Transaction Partner',
            'login' => 'global-transaction-login',
            'email' => 'global-transaction@safi.test',
        ]);
        $target = $this->createTransaction($targetUser, 777, 200);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->getJson('/api/admin/transactions?search=global-transaction-login&per_page=5')
            ->assertOk()
            ->assertJsonCount(1, 'transactions')
            ->assertJsonPath('meta.total', 1);

        $this->assertSame($target->id, $response->json('transactions.0.id'));
    }

    public function test_summary_totals_are_not_limited_by_current_page(): void
    {
        $partner = User::factory()->create();
        $this->createTransactions($partner, 25, 100);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/transactions?page=1&per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'transactions')
            ->assertJsonPath('summary.operation_turnover', '2500')
            ->assertJsonPath('meta.total', 25);
    }

    /**
     * @return array<int, WalletTransaction>
     */
    private function createTransactions(User $user, int $count, int $amount = 100): array
    {
        $transactions = [];

        for ($index = 1; $index <= $count; $index++) {
            $transactions[] = $this->createTransaction($user, $amount, $index);
        }

        return $transactions;
    }

    private function createTransaction(User $user, int $amount, int $minutesAgo): WalletTransaction
    {
        $wallet = Wallet::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'type' => 'main',
            ],
            [
                'currency' => 'KZT',
                'balance' => 0,
                'hold_balance' => 0,
                'status' => 'active',
            ]
        );
        $createdAt = now()->subMinutes($minutesAgo);

        return WalletTransaction::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'manual_adjustment',
            'direction' => 'credit',
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'status' => 'completed',
            'affects_balance' => true,
            'description' => 'Pagination test transaction',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}

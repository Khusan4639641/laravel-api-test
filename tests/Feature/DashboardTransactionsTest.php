<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_package_purchase_transaction(): void
    {
        $user = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")->assertOk();

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('transactions.0.type', 'package_activation')
            ->assertJsonPath('transactions.0.amount', '60000.00')
            ->assertJsonPath('transactions.0.affects_balance', false);
    }

    public function test_package_transaction_does_not_increase_dashboard_income_summary(): void
    {
        $user = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")->assertOk();

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('summary.total_earned', '0')
            ->assertJsonPath('summary.available', '0');
    }

    public function test_balance_remains_zero_after_package_purchase_without_bonuses(): void
    {
        $user = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")
            ->assertOk()
            ->assertJsonPath('user.available_balance', 0)
            ->assertJsonPath('user.total_earned', 0);

        $this->assertSame('0.00', $user->wallets()->where('type', 'main')->firstOrFail()->balance);
    }

    public function test_real_bonus_increases_dashboard_income_summary(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 10000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'referral_bonus',
            'direction' => 'credit',
            'amount' => 10000,
            'balance_before' => 0,
            'balance_after' => 10000,
            'status' => 'completed',
            'affects_balance' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('summary.total_earned', '10000')
            ->assertJsonPath('summary.available', '10000');
    }

    public function test_dashboard_transactions_are_scoped_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $wallet = $this->wallet($user);
        $otherWallet = $this->wallet($other);
        $ownTransaction = $this->transaction($user, $wallet, ['description' => 'Own transaction']);
        $otherTransaction = $this->transaction($other, $otherWallet, ['description' => 'Other transaction']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonCount(1, 'transactions')
            ->assertJsonPath('transactions.0.id', $ownTransaction->id);

        $ids = collect($response->json('transactions'))->pluck('id');

        $this->assertFalse($ids->contains($otherTransaction->id));
    }

    public function test_dashboard_transactions_are_paginated(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user);
        $start = CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC');

        foreach (range(1, 25) as $index) {
            $this->transaction($user, $wallet, [
                'description' => "Transaction {$index}",
                'created_at' => $start->addMinutes($index),
                'updated_at' => $start->addMinutes($index),
            ]);
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/transactions?page=2&per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'transactions')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 25);
    }

    public function test_dashboard_transaction_datetime_is_formatted_in_tashkent_timezone(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user);

        $this->transaction($user, $wallet, [
            'created_at' => CarbonImmutable::parse('2026-06-25 13:22:51', 'Asia/Tashkent'),
            'updated_at' => CarbonImmutable::parse('2026-06-25 13:30:51', 'Asia/Tashkent'),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('transactions.0.created_at', '2026-06-25 13:22:51')
            ->assertJsonPath('transactions.0.updated_at', '2026-06-25 13:30:51');
    }

    private function package(string $code): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => 60000,
            'pv' => 100,
            'activity_pv' => 100,
            'turnover_pv' => 100,
            'referral_percent' => 10,
            'binary_percent' => 7,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }

    private function wallet(User $user): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 0,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transaction(User $user, Wallet $wallet, array $attributes = []): WalletTransaction
    {
        $createdAt = $attributes['created_at'] ?? null;
        $updatedAt = $attributes['updated_at'] ?? $createdAt;
        unset($attributes['created_at'], $attributes['updated_at']);

        $transaction = WalletTransaction::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'referral_bonus',
            'direction' => 'credit',
            'amount' => 1000,
            'balance_before' => 0,
            'balance_after' => 1000,
            'status' => 'completed',
            'affects_balance' => true,
            ...$attributes,
        ]);

        if ($createdAt || $updatedAt) {
            $transaction->forceFill([
                'created_at' => $createdAt ?? $transaction->created_at,
                'updated_at' => $updatedAt ?? $transaction->updated_at,
            ])->save();
        }

        return $transaction->refresh();
    }
}

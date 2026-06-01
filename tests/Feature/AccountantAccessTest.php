<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_access_accounting_admin_api(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 1000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
        WithdrawalRequest::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 100,
            'fee_amount' => 0,
            'net_amount' => 100,
            'currency' => 'KZT',
            'status' => 'pending',
            'payment_method' => 'card_account',
            'payout_period_days' => 14,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'accountant']));

        $this->getJson('/api/admin/overview')->assertOk();
        $this->getJson('/api/admin/transactions')->assertOk();
        $this->getJson('/api/admin/withdrawals')->assertOk()->assertJsonCount(1, 'withdrawals');
        $this->getJson('/api/admin/reports/summary')->assertOk();
    }

    public function test_accountant_cannot_access_restricted_admin_api(): void
    {
        $product = Product::query()->create([
            'name' => 'Restricted Product',
            'sku' => 'RESTRICTED-001',
            'price' => 100,
            'pv' => 10,
            'status' => 'active',
        ]);
        $news = News::query()->create([
            'title' => 'Restricted News',
            'slug' => 'restricted-news',
            'content' => 'Restricted content.',
            'status' => 'published',
            'is_published' => true,
        ]);
        $partner = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $partner->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 1000,
            'hold_balance' => 100,
            'status' => 'active',
        ]);
        $withdrawal = WithdrawalRequest::query()->create([
            'user_id' => $partner->id,
            'wallet_id' => $wallet->id,
            'amount' => 100,
            'fee_amount' => 0,
            'net_amount' => 100,
            'currency' => 'KZT',
            'status' => 'pending',
            'payment_method' => 'card_account',
            'payout_period_days' => 14,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'accountant']));

        $this->getJson('/api/admin/users')->assertForbidden();
        $this->getJson('/api/admin/structure')->assertForbidden();
        $this->getJson('/api/admin/products')->assertForbidden();
        $this->postJson('/api/admin/products', [])->assertForbidden();
        $this->putJson("/api/admin/products/{$product->id}", [])->assertForbidden();
        $this->deleteJson("/api/admin/products/{$product->id}")->assertForbidden();
        $this->getJson('/api/admin/news')->assertForbidden();
        $this->postJson('/api/admin/news', [])->assertForbidden();
        $this->putJson("/api/admin/news/{$news->id}", [])->assertForbidden();
        $this->deleteJson("/api/admin/news/{$news->id}")->assertForbidden();
        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->postJson('/api/admin/partners', [])->assertForbidden();
        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/approve")->assertForbidden();
        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/reject")->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\Package;
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
        $package = Package::query()->create([
            'code' => 'START',
            'name' => 'START',
            'slug' => 'start-accountant-test',
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

        Sanctum::actingAs(User::factory()->create(['role' => 'accountant']));

        $this->getJson('/api/admin/users')->assertForbidden();
        $this->getJson('/api/admin/structure')->assertForbidden();
        $this->getJson('/api/admin/products')->assertForbidden();
        $this->postJson('/api/admin/products', [])->assertForbidden();
        $this->putJson("/api/admin/products/{$product->id}", [])->assertForbidden();
        $this->deleteJson("/api/admin/products/{$product->id}")->assertForbidden();
        $this->getJson('/api/admin/packages')->assertForbidden();
        $this->postJson('/api/admin/packages', [])->assertForbidden();
        $this->putJson("/api/admin/packages/{$package->id}", [])->assertForbidden();
        $this->getJson('/api/admin/statuses')->assertForbidden();
        $this->getJson('/api/admin/bonuses')->assertForbidden();
        $this->postJson('/api/admin/bonuses/binary/calculate', ['user_id' => $partner->id])->assertForbidden();
        $this->postJson('/api/bonuses/binary/calculate', ['user_id' => $partner->id])->assertForbidden();
        $this->getJson('/api/admin/news')->assertForbidden();
        $this->postJson('/api/admin/news', [])->assertForbidden();
        $this->putJson("/api/admin/news/{$news->id}", [])->assertForbidden();
        $this->deleteJson("/api/admin/news/{$news->id}")->assertForbidden();
        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->putJson('/api/admin/settings', ['settings' => []])->assertForbidden();
        $this->postJson('/api/admin/partners', [])->assertForbidden();
        $this->postJson('/api/admin/partners/bulk-create', ['partners' => []])->assertForbidden();
        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $package->id,
            'apply_business_effects' => true,
        ])->assertForbidden();
        $this->patchJson("/api/admin/partners/{$partner->id}/status", [
            'status' => 'manager',
        ])->assertForbidden();
    }

    public function test_accountant_can_approve_and_reject_withdrawals(): void
    {
        $firstUser = User::factory()->create();
        $firstWallet = Wallet::query()->create([
            'user_id' => $firstUser->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 1000,
            'hold_balance' => 100,
            'status' => 'active',
        ]);
        $firstWithdrawal = WithdrawalRequest::query()->create([
            'user_id' => $firstUser->id,
            'wallet_id' => $firstWallet->id,
            'amount' => 100,
            'fee_amount' => 0,
            'net_amount' => 100,
            'currency' => 'KZT',
            'status' => 'pending',
            'payment_method' => 'card_account',
            'payout_period_days' => 14,
        ]);

        $secondUser = User::factory()->create();
        $secondWallet = Wallet::query()->create([
            'user_id' => $secondUser->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 900,
            'hold_balance' => 100,
            'status' => 'active',
        ]);
        $secondWithdrawal = WithdrawalRequest::query()->create([
            'user_id' => $secondUser->id,
            'wallet_id' => $secondWallet->id,
            'amount' => 100,
            'fee_amount' => 0,
            'net_amount' => 100,
            'currency' => 'KZT',
            'status' => 'pending',
            'payment_method' => 'card_account',
            'payout_period_days' => 14,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'accountant']));

        $this->patchJson("/api/admin/withdrawals/{$firstWithdrawal->id}/approve")
            ->assertOk()
            ->assertJsonPath('withdrawal.status', 'approved');
        $this->assertSame('approved', $firstWithdrawal->refresh()->status);
        $this->assertSame('0.00', $firstWallet->refresh()->hold_balance);

        $this->patchJson("/api/admin/withdrawals/{$secondWithdrawal->id}/reject", [
            'reason' => 'Incorrect details',
        ])
            ->assertOk()
            ->assertJsonPath('withdrawal.status', 'rejected');
        $this->assertSame('rejected', $secondWithdrawal->refresh()->status);
        $this->assertSame('1000.00', $secondWallet->refresh()->balance);
        $this->assertSame('0.00', $secondWallet->hold_balance);
    }
}

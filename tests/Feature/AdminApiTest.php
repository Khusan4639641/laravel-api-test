<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'user',
        ]));

        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_support_cannot_access_super_admin_crud_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'support',
        ]));

        $this->getJson('/api/admin/products')->assertForbidden();
        $this->postJson('/api/admin/products', [])->assertForbidden();
        $this->getJson('/api/admin/news')->assertForbidden();
        $this->postJson('/api/admin/news', [])->assertForbidden();
        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->putJson('/api/admin/settings', [])->assertForbidden();
        $this->getJson('/api/admin/packages')->assertForbidden();
        $this->postJson('/api/admin/packages', [])->assertForbidden();
        $this->getJson('/api/admin/statuses')->assertForbidden();
        $this->getJson('/api/admin/reports/summary')->assertForbidden();
    }

    public function test_super_admin_can_manage_products_news_packages_and_settings(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'super_admin',
        ]));

        $productResponse = $this->postJson('/api/admin/products', [
            'name' => 'Safi Test Product',
            'sku' => 'SAFI-TEST-001',
            'description' => 'Test product description.',
            'price' => 12000,
            'stock_quantity' => 12,
            'status' => 'active',
            'metadata' => ['category' => 'Test'],
        ])->assertCreated()
            ->assertJsonPath('product.name', 'Safi Test Product')
            ->assertJsonPath('product.pv', '24.00');

        $productId = $productResponse->json('product.id');

        $this->getJson("/api/admin/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('product.id', $productId);

        $this->putJson("/api/admin/products/{$productId}", [
            'price' => 15000,
            'metadata' => ['image_url' => 'https://example.test/product.png'],
        ])->assertOk()
            ->assertJsonPath('product.price', '15000.00')
            ->assertJsonPath('product.pv', '30.00')
            ->assertJsonPath('product.metadata.category', 'Test')
            ->assertJsonPath('product.metadata.image_url', 'https://example.test/product.png');

        $this->deleteJson("/api/admin/products/{$productId}")
            ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $productId]);

        $newsResponse = $this->postJson('/api/admin/news', [
            'title' => 'Safi Test News',
            'category' => 'Company',
            'excerpt' => 'Short text.',
            'content' => 'Full news content.',
            'status' => 'published',
            'is_published' => true,
        ])->assertCreated()
            ->assertJsonPath('news.title', 'Safi Test News');

        $newsId = $newsResponse->json('news.id');

        $this->getJson("/api/admin/news/{$newsId}")
            ->assertOk()
            ->assertJsonPath('news.id', $newsId);

        $this->putJson("/api/admin/news/{$newsId}", [
            'title' => 'Updated Safi Test News',
            'content' => 'Updated full news content.',
        ])->assertOk()
            ->assertJsonPath('news.title', 'Updated Safi Test News')
            ->assertJsonPath('news.slug', 'updated-safi-test-news');

        $this->deleteJson("/api/admin/news/{$newsId}")
            ->assertOk();

        $this->assertDatabaseMissing('news', ['id' => $newsId]);

        $packageResponse = $this->postJson('/api/admin/packages', [
            'code' => 'TEST-PACKAGE',
            'name' => 'Test Package',
            'slug' => 'test-package',
            'description' => 'Package description.',
            'price' => 50000,
            'pv' => 250,
            'referral_percent' => 10,
            'binary_percent' => 5,
            'sort_order' => 10,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ])->assertCreated()
            ->assertJsonPath('package.name', 'Test Package');

        $packageId = $packageResponse->json('package.id');

        $this->getJson('/api/admin/packages')
            ->assertOk()
            ->assertJsonCount(1, 'packages');

        $this->putJson("/api/admin/packages/{$packageId}", [
            'price' => 55000,
            'is_upgradeable' => false,
        ])->assertOk()
            ->assertJsonPath('package.price', '55000.00')
            ->assertJsonPath('package.is_upgradeable', false);

        $settingsResponse = $this->putJson('/api/admin/settings', [
            'settings' => [
                'company.name' => 'Safi Life Test',
                'withdrawals.minimum_amount' => 5000,
            ],
        ])->assertOk();
        $settings = $settingsResponse->json('settings');

        $this->assertSame('Safi Life Test', $settings['company.name']);
        $this->assertSame(5000, $settings['withdrawals.minimum_amount']);

        $this->getJson('/api/admin/settings')->assertOk();
        $this->getJson('/api/admin/statuses')->assertOk()->assertJsonStructure(['statuses']);
        $this->getJson('/api/admin/reports/summary')->assertOk()->assertJsonStructure(['partners', 'finance', 'packages']);
    }

    public function test_public_catalog_routes_do_not_require_token(): void
    {
        Product::query()->create([
            'name' => 'Public Product',
            'sku' => 'PUBLIC-001',
            'price' => 100,
            'pv' => 10,
            'status' => 'active',
        ]);

        News::query()->create([
            'title' => 'Public News',
            'slug' => 'public-news',
            'content' => 'Public news content.',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Package::query()->create([
            'code' => 'START',
            'name' => 'START',
            'slug' => 'start',
            'price' => 60000,
            'pv' => 60000,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->getJson('/api/public/products')->assertOk()->assertJsonCount(1, 'products');
        $this->getJson('/api/public/news')->assertOk()->assertJsonCount(1, 'news');
        $this->getJson('/api/public/faqs')->assertOk();
        $this->getJson('/api/public/packages')->assertOk()->assertJsonCount(1, 'packages');
    }

    public function test_admin_can_list_users_and_withdrawals(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create();
        $wallet = $this->createMainWallet($user, 750, 250);
        WithdrawalRequest::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 250,
            'fee_amount' => 0,
            'net_amount' => 250,
            'currency' => 'KZT',
            'status' => 'pending',
            'payment_method' => 'card_account',
            'payout_period_days' => 14,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(2, 'users');

        $this->getJson('/api/admin/withdrawals')
            ->assertOk()
            ->assertJsonCount(1, 'withdrawals')
            ->assertJsonPath('withdrawals.0.status', 'pending');
    }

    public function test_admin_can_approve_withdrawal_and_spend_hold_balance(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create();
        $wallet = $this->createMainWallet($user, 750, 250);
        $withdrawal = $this->createWithdrawal($user, $wallet, 250);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/approve")
            ->assertOk()
            ->assertJsonPath('withdrawal.status', 'approved');

        $wallet->refresh();
        $withdrawal->refresh();
        $walletTransaction = WalletTransaction::query()
            ->where('type', 'withdrawal_approved')
            ->firstOrFail();

        $this->assertSame('750.00', $wallet->balance);
        $this->assertSame('0.00', $wallet->hold_balance);
        $this->assertSame('approved', $withdrawal->status);
        $this->assertNotNull($withdrawal->processed_at);
        $this->assertSame('neutral', $walletTransaction->direction);
        $this->assertSame('250.00', $walletTransaction->amount);
        $this->assertSame('750.00', $walletTransaction->balance_before);
        $this->assertSame('750.00', $walletTransaction->balance_after);
    }

    public function test_admin_can_reject_withdrawal_and_return_hold_to_balance(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create();
        $wallet = $this->createMainWallet($user, 750, 250);
        $withdrawal = $this->createWithdrawal($user, $wallet, 250);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/reject", [
            'reason' => 'Invalid payment details',
        ])->assertOk()
            ->assertJsonPath('withdrawal.status', 'rejected')
            ->assertJsonPath('withdrawal.admin_comment', 'Invalid payment details');

        $wallet->refresh();
        $withdrawal->refresh();
        $walletTransaction = WalletTransaction::query()
            ->where('type', 'withdrawal_rejected')
            ->firstOrFail();

        $this->assertSame('1000.00', $wallet->balance);
        $this->assertSame('0.00', $wallet->hold_balance);
        $this->assertSame('rejected', $withdrawal->status);
        $this->assertSame('Invalid payment details', $withdrawal->admin_comment);
        $this->assertSame('credit', $walletTransaction->direction);
        $this->assertSame('250.00', $walletTransaction->amount);
        $this->assertSame('750.00', $walletTransaction->balance_before);
        $this->assertSame('1000.00', $walletTransaction->balance_after);
    }

    public function test_admin_cannot_process_non_pending_withdrawal(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create();
        $wallet = $this->createMainWallet($user, 750, 250);
        $withdrawal = $this->createWithdrawal($user, $wallet, 250, 'approved');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('withdrawal');
    }

    public function test_withdrawal_lifecycle_does_not_double_debit_main_wallet(): void
    {
        $partner = User::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $wallet = $this->createMainWallet($partner, 100000, 0);

        Sanctum::actingAs($partner);

        $withdrawalId = $this->postJson('/api/withdrawals', [
            'amount' => 50000,
            'payment_method' => 'card_account',
        ])
            ->assertCreated()
            ->assertJsonPath('withdrawal.status', 'pending')
            ->json('withdrawal.id');

        $this->assertSame('50000.00', $wallet->refresh()->balance);
        $this->assertSame('50000.00', $wallet->hold_balance);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/withdrawals/{$withdrawalId}/approve")
            ->assertOk()
            ->assertJsonPath('withdrawal.status', 'approved');

        $wallet->refresh();

        $this->assertSame('50000.00', $wallet->balance);
        $this->assertSame('0.00', $wallet->hold_balance);
        $this->assertEquals(50000, (float) WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('direction', 'debit')
            ->sum('amount'));
        $this->assertSame(1, WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('direction', 'debit')
            ->count());
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'withdrawal_hold',
            'direction' => 'debit',
            'amount' => 50000,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'withdrawal_approved',
            'direction' => 'neutral',
            'amount' => 50000,
            'balance_before' => 50000,
            'balance_after' => 50000,
        ]);
    }

    public function test_admin_cannot_reject_already_approved_withdrawal(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create();
        $wallet = $this->createMainWallet($user, 750, 0);
        $withdrawal = $this->createWithdrawal($user, $wallet, 250, 'approved');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/reject", [
            'reason' => 'Attempted double processing',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('withdrawal');

        $this->assertSame('approved', $withdrawal->refresh()->status);
        $this->assertSame('750.00', $wallet->refresh()->balance);
        $this->assertSame('0.00', $wallet->hold_balance);
    }

    private function createMainWallet(User $user, int $balance, int $holdBalance): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => $balance,
            'hold_balance' => $holdBalance,
            'status' => 'active',
        ]);
    }

    private function createWithdrawal(User $user, Wallet $wallet, int $amount, string $status = 'pending'): WithdrawalRequest
    {
        return WithdrawalRequest::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'fee_amount' => 0,
            'net_amount' => $amount,
            'currency' => 'KZT',
            'status' => $status,
            'payment_method' => 'card_account',
            'payout_period_days' => 14,
        ]);
    }
}

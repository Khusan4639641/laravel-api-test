<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\Product;
use App\Models\PvTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\BonusAccruedNotification;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_index_and_show_return_active_products(): void
    {
        $active = $this->createProduct('Active Product', 'ACTIVE-001', 1000, 'active');
        $inactive = $this->createProduct('Inactive Product', 'INACTIVE-001', 2000, 'inactive');

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $active->id);

        $this->getJson("/api/products/{$active->id}")
            ->assertOk()
            ->assertJsonPath('product.id', $active->id);

        $this->getJson("/api/products/{$inactive->id}")
            ->assertNotFound();
    }

    public function test_authenticated_user_can_create_order_with_calculated_totals(): void
    {
        $user = User::factory()->create();
        $first = $this->createProduct('Omega', 'OMEGA-001', 1000, 'active', 500);
        $second = $this->createProduct('Cream', 'CREAM-001', 2500, 'active', 1000);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', $this->orderPayload([
            ['product_id' => $first->id, 'quantity' => 2],
            ['product_id' => $second->id, 'quantity' => 3],
        ], [
            'recipient_name' => 'Dana Client',
            'phone' => '+77011112233',
            'city' => 'Tashkent',
            'delivery_address' => 'Navoi 20',
            'comment' => 'Deliver after 18:00',
            'shipping_address' => [
                'legacy_note' => 'keep existing address metadata',
            ],
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.payment_status', 'unpaid')
            ->assertJsonPath('order.subtotal_amount', '9500.00')
            ->assertJsonPath('order.total_amount', '9500.00')
            ->assertJsonPath('order.total_pv', '19.00')
            ->assertJsonPath('order.recipient_name', 'Dana Client')
            ->assertJsonPath('order.phone', '+77011112233')
            ->assertJsonPath('order.city', 'Tashkent')
            ->assertJsonPath('order.delivery_address', 'Navoi 20')
            ->assertJsonPath('order.comment', 'Deliver after 18:00')
            ->assertJsonPath('order.delivery.phone', '+77011112233')
            ->assertJsonCount(2, 'order.items');

        $order = Order::query()->with('items')->firstOrFail();

        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('9500.00', $order->total_amount);
        $this->assertSame('19.00', $order->total_pv);
        $this->assertSame('Dana Client', $order->recipient_name);
        $this->assertSame('+77011112233', $order->phone);
        $this->assertSame('Tashkent', $order->city);
        $this->assertSame('Navoi 20', $order->delivery_address);
        $this->assertSame('Deliver after 18:00', $order->comment);
        $this->assertSame('Navoi 20', $order->shipping_address['delivery_address']);
        $this->assertSame('keep existing address metadata', $order->shipping_address['legacy_note']);
        $this->assertSame('2000.00', $order->items[0]->total_price);
        $this->assertSame('7500.00', $order->items[1]->total_price);
    }

    public function test_order_cannot_be_created_with_inactive_product(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Inactive', 'INACTIVE-002', 1000, 'inactive');

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->orderPayload([
            ['product_id' => $product->id, 'quantity' => 1],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_cannot_be_created_with_deposit_product(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Deposit Product', 'DEPOSIT-CHECKOUT-BLOCK', 1000, 'active', 2, true);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->orderPayload([
            ['product_id' => $product->id, 'quantity' => 1],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->refresh()->stock_quantity);
    }

    public function test_order_cannot_be_created_with_non_positive_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Omega', 'OMEGA-002', 1000);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->orderPayload([
            ['product_id' => $product->id, 'quantity' => 0],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_user_can_list_and_view_only_own_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownOrder = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-OWN',
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal_amount' => 100,
            'discount_amount' => 0,
            'total_amount' => 100,
            'total_pv' => 100,
        ]);
        $otherOrder = Order::query()->create([
            'user_id' => $otherUser->id,
            'order_number' => 'ORD-OTHER',
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal_amount' => 100,
            'discount_amount' => 0,
            'total_amount' => 100,
            'total_pv' => 100,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $ownOrder->id);

        $this->getJson("/api/orders/{$ownOrder->id}")
            ->assertOk()
            ->assertJsonPath('order.id', $ownOrder->id);

        $this->getJson("/api/orders/{$otherOrder->id}")
            ->assertNotFound();
    }

    public function test_product_seeder_creates_active_products(): void
    {
        $this->seed(ProductSeeder::class);

        $this->assertGreaterThanOrEqual(4, Product::query()->where('status', 'active')->count());
        $this->assertDatabaseHas('products', [
            'sku' => 'SAFI-OMEGA-3',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('products', [
            'sku' => 'SAFI-FACE-SERUM',
            'status' => 'active',
        ]);
    }

    public function test_user_can_list_deposit_products_only(): void
    {
        $user = User::factory()->create();
        $depositProduct = $this->createProduct('Deposit Product', 'DEPOSIT-001', 1000, 'active', 10, true);
        $normalProduct = $this->createProduct('Normal Product', 'NORMAL-001', 2000);

        Sanctum::actingAs($user);

        $this->getJson('/api/products/deposit')
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $depositProduct->id)
            ->assertJsonPath('products.0.is_deposit_product', true);

        $this->getJson('/api/dashboard/deposit-products')
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $depositProduct->id)
            ->assertJsonPath('products.0.is_deposit_product', true);

        $this->getJson('/api/dashboard/products')
            ->assertOk()
            ->assertJsonMissing(['id' => $depositProduct->id])
            ->assertJsonFragment(['id' => $normalProduct->id]);
    }

    public function test_deposit_purchase_endpoint_requires_product(): void
    {
        $user = User::factory()->create();
        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'currency' => 'KZT',
            'balance' => 100000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/deposits/purchase', [
            'amount' => 50000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('bonus_transactions', 0);
        $this->assertDatabaseMissing('wallet_transactions', [
            'type' => 'deposit_purchase',
        ]);
        $this->assertSame('100000.00', $user->wallets()->where('type', 'deposit')->firstOrFail()->balance);
    }

    public function test_user_can_buy_deposit_product_with_twenty_percent_cashback(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $product = $this->createProduct('Deposit Tea', 'DEPOSIT-TEA', 1000, 'active', 2, true);
        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'currency' => 'KZT',
            'balance' => 5000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/deposit-products/{$product->id}/purchase", [
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('message', 'Покупка депозитного товара выполнена.')
            ->assertJsonPath('data.total', '1000.00')
            ->assertJsonPath('data.deposit_debited', '1000.00')
            ->assertJsonPath('data.cashback', '200.00')
            ->assertJsonPath('data.main_balance', '200.00')
            ->assertJsonPath('data.deposit_balance', '4000.00')
            ->assertJsonPath('order.total_amount', '1000.00')
            ->assertJsonPath('order.total_pv', '0.00')
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.payment_provider', Order::PAYMENT_PROVIDER_DEPOSIT)
            ->assertJsonPath('order.items.0.unit_pv', '0.00')
            ->assertJsonPath('order.items.0.total_pv', '0.00')
            ->assertJsonPath('deposit_transaction.type', 'deposit_product_purchase')
            ->assertJsonPath('deposit_transaction.amount', '1000.00')
            ->assertJsonPath('cashback_bonus.bonus_type', 'cashback')
            ->assertJsonPath('cashback_bonus.amount', '200.00');

        $depositWallet = $user->wallets()->where('type', 'deposit')->firstOrFail();
        $mainWallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $cashbackBonus = BonusTransaction::query()->where('bonus_type', 'cashback')->firstOrFail();

        $this->assertSame('4000.00', $depositWallet->balance);
        $this->assertSame('200.00', $mainWallet->balance);
        $this->assertSame(9, $product->refresh()->stock_quantity);
        $this->assertSame('20', $cashbackBonus->metadata['cashback_percent']);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'payment_status' => 'paid',
            'payment_provider' => Order::PAYMENT_PROVIDER_DEPOSIT,
            'total_amount' => '1000.00',
            'total_pv' => '0.00',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'deposit_product_purchase',
            'direction' => 'debit',
            'amount' => '1000.00',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'deposit_purchase_cashback',
            'direction' => 'credit',
            'amount' => '200.00',
        ]);

        Notification::assertSentTo($user, BonusAccruedNotification::class);
    }

    public function test_deposit_product_purchase_does_not_apply_mlm_or_package_effects(): void
    {
        $this->createPackages();
        $sponsor = User::factory()->create(['total_pv' => 1000]);
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'total_pv' => 0,
        ]);
        $product = $this->createProduct('Deposit Package Blocker', 'DEPOSIT-NO-MLM', 300000, 'active', 600, true);
        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'currency' => 'KZT',
            'balance' => 300000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/deposits/purchase', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('order.total_amount', '300000.00')
            ->assertJsonPath('order.total_pv', '0.00')
            ->assertJsonPath('cashback_bonus.amount', '60000.00');

        $this->assertNull($user->refresh()->current_package_id);
        $this->assertSame('0.00', $user->total_pv);
        $this->assertSame('1000.00', $sponsor->refresh()->total_pv);
        $this->assertSame(0, PvTransaction::query()->count());
        $this->assertSame(0, BonusTransaction::query()->whereIn('bonus_type', ['referral', 'binary'])->count());
        $this->assertSame(0, WalletTransaction::query()->where('type', 'package_auto_upgrade')->count());
        $this->assertSame(1, BonusTransaction::query()->where('bonus_type', 'cashback')->count());
        $this->assertSame(0, Order::query()->sum('total_pv'));
    }

    public function test_deposit_product_purchase_requires_deposit_balance(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Deposit Product', 'DEPOSIT-LOW-BALANCE', 1000, 'active', 1, true);
        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'currency' => 'KZT',
            'balance' => 500,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/deposits/purchase', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('500.00', $user->wallets()->where('type', 'deposit')->firstOrFail()->balance);
    }

    public function test_user_cannot_buy_non_deposit_product_from_deposit_catalog(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Normal Product', 'NORMAL-DEPOSIT-BLOCK', 1000);
        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'currency' => 'KZT',
            'balance' => 5000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/deposits/purchase', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseMissing('wallet_transactions', [
            'type' => 'deposit_product_purchase',
        ]);
    }

    private function createProduct(
        string $name,
        string $sku,
        int $price,
        string $status = 'active',
        int $pv = 1000,
        bool $isDepositProduct = false,
    ): Product {
        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'description' => $name,
            'price' => $price,
            'pv' => $pv,
            'stock_quantity' => 10,
            'status' => $status,
            'is_deposit_product' => $isDepositProduct,
        ]);
    }

    private function createPackages(): void
    {
        foreach ([
            'START' => [60000, 100, 100, 7, 1],
            'VIP' => [180000, 300, 300, 8, 2],
            'ELITE' => [300000, 500, 200, 10, 3],
        ] as $code => [$price, $activityPv, $turnoverPv, $binaryPercent, $sortOrder]) {
            Package::query()->create([
                'code' => $code,
                'name' => $code,
                'slug' => strtolower($code),
                'price' => $price,
                'pv' => $activityPv,
                'activity_pv' => $activityPv,
                'turnover_pv' => $turnoverPv,
                'referral_percent' => 10,
                'binary_percent' => $binaryPercent,
                'sort_order' => $sortOrder,
                'status' => 'active',
                'is_active' => true,
                'is_upgradeable' => true,
            ]);
        }
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function orderPayload(array $items, array $overrides = []): array
    {
        return array_merge([
            'items' => $items,
            'recipient_name' => 'Safi Client',
            'phone' => '+77010000000',
            'city' => 'Almaty',
            'delivery_address' => 'Abay 10',
            'comment' => 'Call before delivery',
        ], $overrides);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\BonusTransaction;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $first->id, 'quantity' => 2],
                ['product_id' => $second->id, 'quantity' => 3],
            ],
            'shipping_address' => [
                'city' => 'Tashkent',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.payment_status', 'pending')
            ->assertJsonPath('order.subtotal_amount', '9500.00')
            ->assertJsonPath('order.total_amount', '9500.00')
            ->assertJsonPath('order.total_pv', '4000.00')
            ->assertJsonCount(2, 'order.items');

        $order = Order::query()->with('items')->firstOrFail();

        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('9500.00', $order->total_amount);
        $this->assertSame('4000.00', $order->total_pv);
        $this->assertSame('2000.00', $order->items[0]->total_price);
        $this->assertSame('7500.00', $order->items[1]->total_price);
    }

    public function test_order_cannot_be_created_with_inactive_product(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Inactive', 'INACTIVE-002', 1000, 'inactive');

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_cannot_be_created_with_non_positive_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Omega', 'OMEGA-002', 1000);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 0],
            ],
        ])->assertUnprocessable()
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

    public function test_user_can_create_deposit_purchase_with_twenty_percent_cashback(): void
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
        ])->assertCreated()
            ->assertJsonPath('deposit_transaction.type', 'deposit_purchase')
            ->assertJsonPath('cashback_bonus.bonus_type', 'cashback')
            ->assertJsonPath('cashback_bonus.amount', '10000.00');

        $depositWallet = $user->wallets()->where('type', 'deposit')->firstOrFail();
        $mainWallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $cashbackBonus = BonusTransaction::query()->where('bonus_type', 'cashback')->firstOrFail();

        $this->assertSame('50000.00', $depositWallet->balance);
        $this->assertSame('10000.00', $mainWallet->balance);
        $this->assertSame('20', $cashbackBonus->metadata['cashback_percent']);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'deposit_purchase',
            'direction' => 'debit',
            'amount' => '50000.00',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'deposit_purchase_cashback',
            'direction' => 'credit',
            'amount' => '10000.00',
        ]);
    }

    private function createProduct(
        string $name,
        string $sku,
        int $price,
        string $status = 'active',
        int $pv = 1000,
    ): Product {
        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'description' => $name,
            'price' => $price,
            'pv' => $pv,
            'stock_quantity' => 10,
            'status' => $status,
        ]);
    }
}

<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserOrdersPageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_orders(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        $ownOrder = $this->orderFor($user);
        $this->orderFor($otherUser);

        Sanctum::actingAs($user);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownOrder->id);
    }

    public function test_user_cannot_see_another_user_order(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $otherOrder = $this->orderFor(User::factory()->create(['role' => 'user']));

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$otherOrder->id}")->assertNotFound();
    }

    public function test_order_list_returns_items_count_total_amount_and_total_pv(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $order = $this->orderFor($user, quantity: 2, totalAmount: 36000, totalPv: 60);

        Sanctum::actingAs($user);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.items_count', 2)
            ->assertJsonPath('data.0.total_amount', '36000.00')
            ->assertJsonPath('data.0.total_pv', '60.00');
    }

    public function test_order_detail_returns_items(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $order = $this->orderFor($user, quantity: 2);

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.recipient_name', 'Safi Client')
            ->assertJsonPath('order.phone', '+77010000000')
            ->assertJsonPath('order.city', 'Almaty')
            ->assertJsonPath('order.delivery_address', 'Abay 10')
            ->assertJsonPath('order.delivery.delivery_address', 'Abay 10')
            ->assertJsonCount(1, 'order.items')
            ->assertJsonPath('order.items.0.product_name', 'Safi Product');
    }

    public function test_unauthenticated_user_cannot_list_orders(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    private function orderFor(User $user, int $quantity = 1, int $totalAmount = 18000, int $totalPv = 30): Order
    {
        $product = Product::query()->create([
            'name' => 'Safi Product',
            'sku' => 'SAFI-'.uniqid(),
            'description' => 'Safi Product',
            'price' => 18000,
            'pv' => 30,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-'.uniqid(),
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal_amount' => $totalAmount,
            'discount_amount' => 0,
            'total_amount' => $totalAmount,
            'total_pv' => $totalPv,
            'shipping_address' => [
                'recipient_name' => 'Safi Client',
                'phone' => '+77010000000',
                'city' => 'Almaty',
                'delivery_address' => 'Abay 10',
                'address' => 'Abay 10',
                'comment' => 'Call before delivery',
            ],
            'recipient_name' => 'Safi Client',
            'phone' => '+77010000000',
            'city' => 'Almaty',
            'delivery_address' => 'Abay 10',
            'comment' => 'Call before delivery',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => 18000,
            'unit_pv' => 30,
            'total_price' => $totalAmount,
            'total_pv' => $totalPv,
            'item_snapshot' => ['name' => $product->name],
        ]);

        return $order;
    }
}

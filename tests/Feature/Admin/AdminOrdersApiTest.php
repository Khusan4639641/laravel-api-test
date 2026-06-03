<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_all_orders(): void
    {
        $first = $this->orderFor(User::factory()->create(['role' => 'user']));
        $second = $this->orderFor(User::factory()->create(['role' => 'user']));

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $first->id])
            ->assertJsonFragment(['id' => $second->id]);
    }

    public function test_admin_can_list_orders_if_permission_allows(): void
    {
        $this->orderFor(User::factory()->create(['role' => 'user']));

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/orders')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_accountant_can_list_orders_if_permission_allows(): void
    {
        $this->orderFor(User::factory()->create(['role' => 'user']));

        Sanctum::actingAs(User::factory()->create(['role' => 'accountant']));

        $this->getJson('/api/admin/orders')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_user_and_support_cannot_access_admin_orders(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/orders')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'support']));
        $this->getJson('/api/admin/orders')->assertForbidden();
    }

    public function test_admin_can_search_orders_by_order_id(): void
    {
        $target = $this->orderFor(User::factory()->create(['id' => 501, 'role' => 'user']), orderId: 901);
        $this->orderFor(User::factory()->create(['id' => 777, 'role' => 'user']), orderId: 902);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson("/api/admin/orders?search={$target->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_admin_can_search_orders_by_partner_user_id(): void
    {
        $targetUser = User::factory()->create(['id' => 501, 'role' => 'user']);
        $otherUser = User::factory()->create(['id' => 777, 'role' => 'user']);
        $target = $this->orderFor($targetUser);
        $this->orderFor($otherUser);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/orders?search=501')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_admin_can_filter_orders_by_status(): void
    {
        $this->orderFor(User::factory()->create(['role' => 'user']), status: 'pending');
        $completed = $this->orderFor(User::factory()->create(['role' => 'user']), status: 'completed');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/orders?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $completed->id);
    }

    public function test_admin_can_update_order_status(): void
    {
        $order = $this->orderFor(User::factory()->create(['role' => 'user']));

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'confirmed',
        ])
            ->assertOk()
            ->assertJsonPath('order.status', 'confirmed');

        $this->assertSame('confirmed', $order->refresh()->status);
    }

    public function test_cancelling_pending_order_returns_stock_if_stock_was_deducted(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product(stock: 3);
        $order = $this->orderFor($user, product: $product, quantity: 2, status: 'pending');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ])
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled');

        $this->assertSame(5, $product->refresh()->stock_quantity);
    }

    private function orderFor(User $user, ?Product $product = null, int $quantity = 1, string $status = 'pending', ?int $orderId = null): Order
    {
        $product ??= $this->product();
        $totalAmount = (int) $product->price * $quantity;
        $totalPv = (int) $product->pv * $quantity;

        $order = new Order([
            'user_id' => $user->id,
            'order_number' => 'ORD-'.uniqid(),
            'status' => $status,
            'payment_status' => 'pending',
            'subtotal_amount' => $totalAmount,
            'discount_amount' => 0,
            'total_amount' => $totalAmount,
            'total_pv' => $totalPv,
        ]);

        if ($orderId !== null) {
            $order->id = $orderId;
        }

        $order->save();

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'unit_pv' => $product->pv,
            'total_price' => $totalAmount,
            'total_pv' => $totalPv,
            'item_snapshot' => ['name' => $product->name],
        ]);

        return $order;
    }

    private function product(int $stock = 10): Product
    {
        return Product::query()->create([
            'name' => 'Safi Product',
            'sku' => 'SAFI-'.uniqid(),
            'description' => 'Safi Product',
            'price' => 18000,
            'pv' => 30,
            'stock_quantity' => $stock,
            'reserved_quantity' => 0,
            'status' => 'active',
        ]);
    }
}

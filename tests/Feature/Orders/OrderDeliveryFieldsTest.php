<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderDeliveryFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_create_order_without_phone(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product();

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->payload($product, ['phone' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_user_cannot_create_order_without_delivery_address(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product();

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->payload($product, ['delivery_address' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_address');
    }

    public function test_user_cannot_create_order_without_city(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product();

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->payload($product, ['city' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('city');
    }

    public function test_order_stores_delivery_fields_and_snapshot(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product();

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->payload($product))
            ->assertCreated()
            ->assertJsonPath('order.phone', '+77010000001')
            ->assertJsonPath('order.city', 'Almaty')
            ->assertJsonPath('order.delivery_address', 'Abay avenue 10')
            ->assertJsonPath('order.delivery.phone', '+77010000001');

        $order = Order::query()->firstOrFail();

        $this->assertSame('Safi Client', $order->recipient_name);
        $this->assertSame('+77010000001', $order->phone);
        $this->assertSame('Almaty', $order->city);
        $this->assertSame('Abay avenue 10', $order->delivery_address);
        $this->assertSame('+77010000001', $order->metadata['delivery_snapshot']['phone']);
        $this->assertSame('Abay avenue 10', $order->metadata['delivery_snapshot']['delivery_address']);
    }

    public function test_user_order_detail_returns_delivery_fields(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product();

        Sanctum::actingAs($user);

        $orderId = $this->postJson('/api/orders', $this->payload($product))
            ->assertCreated()
            ->json('order.id');

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('order.recipient_name', 'Safi Client')
            ->assertJsonPath('order.phone', '+77010000001')
            ->assertJsonPath('order.city', 'Almaty')
            ->assertJsonPath('order.delivery_address', 'Abay avenue 10');
    }

    public function test_admin_order_list_and_detail_return_delivery_fields(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product();

        Sanctum::actingAs($user);

        $orderId = $this->postJson('/api/orders', $this->payload($product))
            ->assertCreated()
            ->json('order.id');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.phone', '+77010000001')
            ->assertJsonPath('data.0.city', 'Almaty')
            ->assertJsonPath('data.0.delivery_address', 'Abay avenue 10');

        $this->getJson("/api/admin/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('order.user.id', $user->id)
            ->assertJsonPath('order.phone', '+77010000001')
            ->assertJsonPath('order.delivery_address', 'Abay avenue 10');
    }

    public function test_old_order_without_address_does_not_crash_admin_orders(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-OLD-ADDRESS',
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'total_pv' => 0,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.delivery_address', null)
            ->assertJsonPath('data.0.phone', null);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Product $product, array $overrides = []): array
    {
        $payload = [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'recipient_name' => 'Safi Client',
            'phone' => '+77010000001',
            'city' => 'Almaty',
            'delivery_address' => 'Abay avenue 10',
            'comment' => 'Call before delivery',
        ];

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($payload[$key]);
                continue;
            }

            $payload[$key] = $value;
        }

        return $payload;
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Safi Product',
            'sku' => 'SAFI-'.uniqid(),
            'description' => 'Safi Product',
            'price' => 12500,
            'pv' => 25,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'status' => 'active',
        ]);
    }
}

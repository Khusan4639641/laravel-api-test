<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipTopPayOrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_order_payment_intent(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create();
        $order = $this->orderFor($user, quantity: 2, unitPrice: 15000);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payments/tiptoppay/intent")
            ->assertOk()
            ->assertJsonPath('payment_id', 1)
            ->assertJsonPath('intent.currency', 'KZT')
            ->assertJsonPath('intent.amount', 30000)
            ->assertJsonPath('intent.metadata.order_id', $order->id)
            ->assertJsonPath('intent.metadata.type', 'order');

        $this->assertDatabaseHas('payments', [
            'id' => 1,
            'user_id' => $user->id,
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'type' => 'order',
            'provider' => 'tiptoppay',
            'status' => 'pending',
            'currency' => 'KZT',
        ]);
    }

    public function test_order_intent_includes_items_without_pv(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create();
        $order = $this->orderFor($user, productName: 'ActiveYM', unitPv: 60);

        Sanctum::actingAs($user);

        $item = $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertOk()
            ->json('intent.items.0');

        $this->assertSame('ActiveYM', $item['name']);
        $this->assertSame(30000, $item['price']);
        $this->assertArrayNotHasKey('pv', $item);
        $this->assertArrayNotHasKey('unit_pv', $item);
        $this->assertArrayNotHasKey('total_pv', $item);
    }

    public function test_cannot_pay_empty_order(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-EMPTY',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal_amount' => 1000,
            'discount_amount' => 0,
            'total_amount' => 1000,
            'total_pv' => 0,
            'recipient_name' => 'Client',
            'phone' => '+77010000000',
            'city' => 'Алматы',
            'delivery_address' => 'Абая 10',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
    }

    public function test_cannot_pay_another_users_order(): void
    {
        $this->enableTipTopPay();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $order = $this->orderFor($owner);

        Sanctum::actingAs($actor);

        $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertNotFound();
    }

    public function test_frontend_success_callback_does_not_mark_order_paid(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")->assertOk();

        $order->refresh();
        $this->assertSame('pending', $order->payment_status);
        $this->assertNull($order->paid_at);
        $this->assertSame(0, Payment::query()->where('status', 'paid')->count());
    }

    public function test_pay_webhook_marks_order_paid_and_is_idempotent(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        Sanctum::actingAs($user);
        $externalId = $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertOk()
            ->json('external_id');

        $payload = [
            'InvoiceId' => $externalId,
            'TransactionId' => 'order-pay-1',
            'Amount' => 30000,
            'Currency' => 'KZT',
        ];

        $this->postJson('/api/payments/tiptoppay/pay', $payload)->assertOk()->assertJsonPath('code', 0);
        $this->postJson('/api/payments/tiptoppay/pay', $payload)->assertOk()->assertJsonPath('code', 0);

        $order->refresh();
        $payment = Payment::query()->where('external_id', $externalId)->firstOrFail();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(1, $user->walletTransactions()->where('type', 'order_payment')->count());
    }

    public function test_fail_webhook_does_not_mark_order_paid(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        Sanctum::actingAs($user);
        $externalId = $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertOk()
            ->json('external_id');

        $this->postJson('/api/payments/tiptoppay/fail', [
            'InvoiceId' => $externalId,
            'TransactionId' => 'order-fail-1',
            'Amount' => 30000,
            'Currency' => 'KZT',
            'Reason' => 'Insufficient funds',
        ])->assertOk()->assertJsonPath('code', 0);

        $this->assertSame('failed', $order->refresh()->payment_status);
        $this->assertNull($order->paid_at);
        $this->assertSame('failed', Payment::query()->where('external_id', $externalId)->firstOrFail()->status);
    }

    private function enableTipTopPay(): void
    {
        Config::set('tiptoppay.enabled', true);
        Config::set('tiptoppay.public_terminal_id', 'pk_test');
        Config::set('tiptoppay.payment_schema', 'Single');
        Config::set('tiptoppay.currency', 'KZT');
        Config::set('tiptoppay.webhook_secret', '');
        Config::set('tiptoppay.success_url', 'https://safilife.test/payment/success');
        Config::set('tiptoppay.fail_url', 'https://safilife.test/payment/fail');
    }

    private function orderFor(User $user, int $quantity = 1, int $unitPrice = 30000, int $unitPv = 60, string $productName = 'ActiveYM'): Order
    {
        $product = Product::query()->create([
            'name' => $productName,
            'sku' => 'PRD-'.uniqid(),
            'price' => $unitPrice,
            'pv' => $unitPv,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-'.uniqid(),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal_amount' => $unitPrice * $quantity,
            'discount_amount' => 0,
            'total_amount' => $unitPrice * $quantity,
            'total_pv' => $unitPv * $quantity,
            'recipient_name' => 'Client',
            'phone' => '+77010000000',
            'city' => 'Алматы',
            'delivery_address' => 'Абая 10',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $productName,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_pv' => $unitPv,
            'total_price' => $unitPrice * $quantity,
            'total_pv' => $unitPv * $quantity,
            'item_snapshot' => ['name' => $productName],
        ]);

        return $order->load('items.product');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipTopPayPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_payment_intent_for_own_order(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create(['role' => 'user']);
        $order = $this->orderFor($user, quantity: 2, unitPrice: 12500);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertOk()
            ->assertJsonPath('intent.description', "Оплата заказа #{$order->order_number} на Safi Life (100% карта)")
            ->assertJsonPath('intent.currency', 'KZT')
            ->assertJsonPath('intent.amount', 25000)
            ->assertJsonPath('intent.metadata.order_id', $order->id)
            ->assertJsonPath('intent.metadata.payment_strategy', Order::PAYMENT_STRATEGY_CARD_100)
            ->assertJsonPath('intent.metadata.user_id', $user->id);

        $order->refresh();
        $this->assertSame('tiptoppay', $order->payment_provider);
        $this->assertSame('pending', $order->payment_status);
        $this->assertStringStartsWith("order-{$order->id}-", $order->payment_external_id);
    }

    public function test_intent_returns_amount_in_tenge_and_matching_items(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create(['role' => 'user']);
        $order = $this->orderFor($user, quantity: 3, unitPrice: 18166, productName: 'Safi Omega');

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertOk()
            ->assertJsonPath('intent.currency', 'KZT')
            ->assertJsonPath('intent.amount', 54498)
            ->assertJsonPath('intent.items.0.name', 'Safi Omega')
            ->assertJsonPath('intent.items.0.count', 3)
            ->assertJsonPath('intent.items.0.price', 18166);
    }

    public function test_intent_includes_public_terminal_id_and_external_id(): void
    {
        $this->enableTipTopPay(publicTerminalId: 'public-test-terminal');
        $user = User::factory()->create(['role' => 'user']);
        $order = $this->orderFor($user);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertOk()
            ->assertJsonPath('intent.publicTerminalId', 'public-test-terminal')
            ->assertJsonPath('intent.paymentSchema', 'Single')
            ->json('intent');

        $this->assertStringStartsWith("order-{$order->id}-", $response['externalId']);
        $this->assertStringContainsString('/payment/success?order='.$order->id, $response['successRedirectUrl']);
        $this->assertStringContainsString('/payment/fail?order='.$order->id, $response['failRedirectUrl']);
    }

    public function test_user_cannot_request_payment_intent_for_another_user_order(): void
    {
        $this->enableTipTopPay();
        $owner = User::factory()->create(['role' => 'user']);
        $actor = User::factory()->create(['role' => 'user']);
        $order = $this->orderFor($owner);

        Sanctum::actingAs($actor);

        $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertNotFound();
    }

    public function test_cannot_pay_order_with_empty_items(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create(['role' => 'user']);
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-EMPTY-ITEMS',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal_amount' => 12500,
            'discount_amount' => 0,
            'total_amount' => 12500,
            'total_pv' => 25,
            'recipient_name' => 'Safi Client',
            'phone' => '+77010000000',
            'city' => 'Almaty',
            'delivery_address' => 'Abay 10',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
    }

    public function test_fail_webhook_does_not_mark_order_paid(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create(['role' => 'user']);
        $order = $this->orderFor($user, paymentStatus: 'pending', externalId: 'order-fail-001');

        $this->postJson('/api/payments/tiptoppay/fail', $this->webhookPayload($order, [
            'TransactionId' => 'fail-transaction-1',
            'Reason' => 'Insufficient funds',
            'ReasonCode' => 5051,
        ]))->assertOk()->assertJsonPath('code', 0);

        $order->refresh();
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('fail-transaction-1', $order->payment_transaction_id);
        $this->assertNull($order->paid_at);
        $this->assertSame('pending', $order->status);
    }

    public function test_pay_webhook_marks_order_paid(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create(['role' => 'user']);
        $order = $this->orderFor($user, paymentStatus: 'pending', externalId: 'order-pay-001');

        $this->postJson('/api/payments/tiptoppay/pay', $this->webhookPayload($order, [
            'TransactionId' => 'pay-transaction-1',
        ]))->assertOk()->assertJsonPath('code', 0);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('pay-transaction-1', $order->payment_transaction_id);
        $this->assertNotNull($order->paid_at);
    }

    public function test_repeated_pay_webhook_is_idempotent(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create(['role' => 'user']);
        $order = $this->orderFor($user, paymentStatus: 'pending', externalId: 'order-pay-duplicate-001');
        $payload = $this->webhookPayload($order, ['TransactionId' => 'pay-transaction-duplicate']);

        $this->postJson('/api/payments/tiptoppay/pay', $payload)->assertOk()->assertJsonPath('code', 0);
        $firstPaidAt = $order->refresh()->paid_at?->toISOString();
        $firstMeta = $order->payment_meta;

        $this->postJson('/api/payments/tiptoppay/pay', $payload)->assertOk()->assertJsonPath('code', 0);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('pay-transaction-duplicate', $order->payment_transaction_id);
        $this->assertSame($firstPaidAt, $order->paid_at?->toISOString());
        $this->assertCount(1, collect($firstMeta['webhooks'] ?? [])->where('event', 'pay'));
        $this->assertCount(1, collect($order->payment_meta['webhooks'] ?? [])->where('event', 'pay'));
        $this->assertCount(1, collect($order->payment_meta['webhooks'] ?? [])->where('event', 'pay_duplicate'));
    }

    public function test_admin_sees_payment_status_in_order_resource(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $order = $this->orderFor(
            $user,
            paymentStatus: 'paid',
            paymentProvider: 'tiptoppay',
            externalId: 'order-admin-001',
            transactionId: 'admin-transaction-1',
        );

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.payment_provider', 'tiptoppay')
            ->assertJsonPath('data.0.payment_status', 'paid')
            ->assertJsonPath('data.0.payment_external_id', 'order-admin-001')
            ->assertJsonPath('data.0.payment_transaction_id', 'admin-transaction-1');
    }

    public function test_admin_can_filter_orders_by_payment_status(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->orderFor($user, paymentStatus: 'unpaid');
        $paidOrder = $this->orderFor($user, paymentStatus: 'paid', externalId: 'order-filter-paid');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/orders?payment_status=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $paidOrder->id)
            ->assertJsonPath('data.0.payment_status', 'paid');
    }

    public function test_payment_success_and_fail_pages_return_200(): void
    {
        $this->get('/payment/success')->assertOk();
        $this->get('/payment/fail')->assertOk();
    }

    private function enableTipTopPay(string $publicTerminalId = 'test-public-terminal'): void
    {
        Config::set('tiptoppay.enabled', true);
        Config::set('tiptoppay.public_terminal_id', $publicTerminalId);
        Config::set('tiptoppay.payment_schema', 'Single');
        Config::set('tiptoppay.currency', 'KZT');
        Config::set('tiptoppay.webhook_secret', '');
        Config::set('tiptoppay.success_url', 'https://safilife.test/payment/success');
        Config::set('tiptoppay.fail_url', 'https://safilife.test/payment/fail');
    }

    private function orderFor(
        User $user,
        int $quantity = 1,
        int $unitPrice = 12500,
        int $unitPv = 25,
        string $productName = 'Safi Product',
        string $status = 'pending',
        string $paymentStatus = 'unpaid',
        ?string $paymentProvider = null,
        ?string $externalId = null,
        ?string $transactionId = null,
    ): Order {
        $product = Product::query()->create([
            'name' => $productName,
            'sku' => 'SAFI-'.uniqid(),
            'description' => $productName,
            'price' => $unitPrice,
            'pv' => $unitPv,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'status' => 'active',
        ]);

        $totalAmount = $unitPrice * $quantity;
        $totalPv = $unitPv * $quantity;

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-'.uniqid(),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_provider' => $paymentProvider,
            'payment_external_id' => $externalId,
            'payment_transaction_id' => $transactionId,
            'subtotal_amount' => $totalAmount,
            'discount_amount' => 0,
            'total_amount' => $totalAmount,
            'total_pv' => $totalPv,
            'shipping_address' => [
                'recipient_name' => 'Safi Client',
                'phone' => '+77010000000',
                'city' => 'Almaty',
                'delivery_address' => 'Abay 10',
            ],
            'recipient_name' => 'Safi Client',
            'phone' => '+77010000000',
            'city' => 'Almaty',
            'delivery_address' => 'Abay 10',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $productName,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_pv' => $unitPv,
            'total_price' => $totalAmount,
            'total_pv' => $totalPv,
            'item_snapshot' => ['name' => $productName],
        ]);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function webhookPayload(Order $order, array $overrides = []): array
    {
        return array_replace([
            'InvoiceId' => $order->payment_external_id,
            'TransactionId' => 'tiptop-transaction-1',
            'Amount' => (float) $order->total_amount,
            'Currency' => 'KZT',
            'Data' => [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
            ],
        ], $overrides);
    }
}

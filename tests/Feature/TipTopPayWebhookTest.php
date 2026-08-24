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

class TipTopPayWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_webhook_validates_external_id(): void
    {
        $this->enableTipTopPay();
        [$order, $externalId] = $this->orderPayment();

        $this->postJson('/api/payments/tiptoppay/check', [
            'InvoiceId' => $externalId,
            'Amount' => (float) $order->total_amount,
            'Currency' => 'KZT',
        ])->assertOk()->assertJsonPath('code', 0);
    }

    public function test_check_webhook_rejects_wrong_amount(): void
    {
        $this->enableTipTopPay();
        [, $externalId] = $this->orderPayment();

        $this->postJson('/api/payments/tiptoppay/check', [
            'InvoiceId' => $externalId,
            'Amount' => 1,
            'Currency' => 'KZT',
        ])->assertOk()->assertJsonPath('code', 12);
    }

    public function test_pay_webhook_validates_amount(): void
    {
        $this->enableTipTopPay();
        [$order, $externalId] = $this->orderPayment();

        $this->postJson('/api/payments/tiptoppay/pay', [
            'InvoiceId' => $externalId,
            'TransactionId' => 'wrong-amount-pay',
            'Amount' => 1,
            'Currency' => 'KZT',
        ])->assertOk()->assertJsonPath('code', 12);

        $this->assertNotSame('paid', $order->refresh()->payment_status);
        $this->assertSame('pending', Payment::query()->where('external_id', $externalId)->firstOrFail()->status);
    }

    public function test_fail_webhook_stores_error(): void
    {
        $this->enableTipTopPay();
        [$order, $externalId] = $this->orderPayment();

        $this->postJson('/api/payments/tiptoppay/fail', [
            'InvoiceId' => $externalId,
            'TransactionId' => 'failed-webhook-1',
            'Amount' => (float) $order->total_amount,
            'Currency' => 'KZT',
            'Reason' => 'Card declined',
            'ReasonCode' => 5051,
        ])->assertOk()->assertJsonPath('code', 0);

        $payment = Payment::query()->where('external_id', $externalId)->firstOrFail();
        $event = collect($payment->provider_response['webhooks'] ?? [])->firstWhere('event', 'fail');

        $this->assertSame('failed', $payment->status);
        $this->assertNotNull($payment->failed_at);
        $this->assertSame('Card declined', $event['reason']);
        $this->assertSame('failed', $order->refresh()->payment_status);
    }

    public function test_unknown_external_id_is_handled_safely(): void
    {
        $this->enableTipTopPay();

        $this->postJson('/api/payments/tiptoppay/check', [
            'InvoiceId' => 'unknown-payment-id',
            'Amount' => 1000,
            'Currency' => 'KZT',
        ])->assertOk()->assertJsonPath('code', 10);

        $this->postJson('/api/payments/tiptoppay/pay', [
            'InvoiceId' => 'unknown-payment-id',
            'Amount' => 1000,
            'Currency' => 'KZT',
        ])->assertOk()->assertJsonPath('code', 10);
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

    /**
     * @return array{0: Order, 1: string}
     */
    private function orderPayment(): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'ActiveYM',
            'sku' => 'WEBHOOK-'.uniqid(),
            'price' => 30000,
            'pv' => 60,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-'.uniqid(),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal_amount' => 30000,
            'discount_amount' => 0,
            'total_amount' => 30000,
            'total_pv' => 60,
            'recipient_name' => 'Client',
            'phone' => '+77010000000',
            'city' => 'Алматы',
            'delivery_address' => 'Абая 10',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'ActiveYM',
            'quantity' => 1,
            'unit_price' => 30000,
            'unit_pv' => 60,
            'total_price' => 30000,
            'total_pv' => 60,
        ]);

        Sanctum::actingAs($user);
        $externalId = $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertOk()
            ->json('external_id');

        return [$order->refresh(), $externalId];
    }
}

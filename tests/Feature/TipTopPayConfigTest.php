<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipTopPayConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_reads_public_terminal_id_from_env(): void
    {
        $this->withEnv('TIPTOPPAY_PUBLIC_TERMINAL_ID', 'pk_7b117cb025c07bcf93c9e41df9078', function (): void {
            $config = require config_path('tiptoppay.php');

            $this->assertSame('pk_7b117cb025c07bcf93c9e41df9078', $config['public_terminal_id']);
        });
    }

    public function test_payment_intent_returns_public_terminal_id_and_never_returns_api_password(): void
    {
        $this->enableTipTopPay(
            publicTerminalId: 'pk_7b117cb025c07bcf93c9e41df9078',
            apiPassword: 'secret-api-password',
        );
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertOk()
            ->assertJsonPath('intent.publicTerminalId', 'pk_7b117cb025c07bcf93c9e41df9078');

        $this->assertStringNotContainsString('secret-api-password', $response->getContent());
    }

    public function test_missing_public_terminal_id_returns_clear_error(): void
    {
        $this->enableTipTopPay(publicTerminalId: '');
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'TipTop Pay terminal is not configured')
            ->assertJsonValidationErrors('publicTerminalId');
    }

    public function test_payment_intent_uses_kzt_currency_from_config(): void
    {
        $this->enableTipTopPay(currency: 'KZT');
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertOk()
            ->assertJsonPath('intent.currency', 'KZT');
    }

    public function test_payment_schema_is_single_by_default(): void
    {
        $this->withEnv('TIPTOPPAY_PAYMENT_SCHEMA', null, function (): void {
            $config = require config_path('tiptoppay.php');

            $this->assertSame('Single', $config['payment_schema']);
        });

        $this->enableTipTopPay(paymentSchema: null);
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertOk()
            ->assertJsonPath('intent.paymentSchema', 'Single');
    }

    private function enableTipTopPay(
        string $publicTerminalId = 'pk_test',
        ?string $paymentSchema = 'Single',
        string $currency = 'KZT',
        ?string $apiPassword = null,
    ): void {
        Config::set('tiptoppay.enabled', true);
        Config::set('tiptoppay.public_terminal_id', $publicTerminalId);
        Config::set('tiptoppay.payment_schema', $paymentSchema);
        Config::set('tiptoppay.currency', $currency);
        Config::set('tiptoppay.api_password', $apiPassword);
        Config::set('tiptoppay.webhook_secret', '');
        Config::set('tiptoppay.success_url', 'https://safilife.test/payment/success');
        Config::set('tiptoppay.fail_url', 'https://safilife.test/payment/fail');
    }

    private function orderFor(User $user): Order
    {
        $product = Product::query()->create([
            'name' => 'Safi Product',
            'sku' => 'CFG-'.uniqid(),
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
            'city' => 'Almaty',
            'delivery_address' => 'Abay 10',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Safi Product',
            'quantity' => 1,
            'unit_price' => 30000,
            'unit_pv' => 60,
            'total_price' => 30000,
            'total_pv' => 60,
            'item_snapshot' => ['name' => 'Safi Product'],
        ]);

        return $order->load('items.product');
    }

    private function withEnv(string $key, ?string $value, Closure $callback): void
    {
        $previousEnv = $_ENV[$key] ?? null;
        $previousServer = $_SERVER[$key] ?? null;
        $previousPutenv = getenv($key);
        $hadEnv = array_key_exists($key, $_ENV);
        $hadServer = array_key_exists($key, $_SERVER);

        try {
            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv($key.'='.$value);
            }

            $callback();
        } finally {
            if ($hadEnv) {
                $_ENV[$key] = $previousEnv;
            } else {
                unset($_ENV[$key]);
            }

            if ($hadServer) {
                $_SERVER[$key] = $previousServer;
            } else {
                unset($_SERVER[$key]);
            }

            if ($previousPutenv === false) {
                putenv($key);
            } else {
                putenv($key.'='.$previousPutenv);
            }
        }
    }
}

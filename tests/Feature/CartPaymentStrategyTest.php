<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartPaymentStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_product_card_50_deposit_50_stores_payment_split(): void
    {
        $user = User::factory()->create();
        $product = $this->product('Regular Split', 4001);
        $this->wallet($user, 'deposit', 3000);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->orderPayload($product, [
            'payment_strategy' => Order::PAYMENT_STRATEGY_CARD_50_DEPOSIT_50,
        ]))
            ->assertCreated()
            ->assertJsonPath('order.payment_strategy', Order::PAYMENT_STRATEGY_CARD_50_DEPOSIT_50)
            ->assertJsonPath('order.payment_strategy_label', '50% карта + 50% депозит')
            ->assertJsonPath('order.card_amount', '2001.00')
            ->assertJsonPath('order.deposit_amount', '2000.00');

        $order = Order::query()->firstOrFail();

        $this->assertSame('4001.00', $order->total_amount);
        $this->assertSame('2001.00', $order->card_amount);
        $this->assertSame('2000.00', $order->deposit_amount);
        $this->assertSame('3000.00', $this->walletFor($user, 'deposit')->balance);
    }

    public function test_checkout_rejects_mixed_regular_and_deposit_products(): void
    {
        $user = User::factory()->create();
        $regular = $this->product('Regular', 1000);
        $deposit = $this->product('Deposit', 1000, true);
        $this->wallet($user, 'deposit', 5000);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            ...$this->basePayload(),
            'payment_strategy' => Order::PAYMENT_STRATEGY_CARD_100,
            'items' => [
                ['product_id' => $regular->id, 'quantity' => 1],
                ['product_id' => $deposit->id, 'quantity' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $regular->refresh()->stock_quantity);
        $this->assertSame(10, $deposit->refresh()->stock_quantity);
    }

    public function test_deposit_product_requires_deposit_100_strategy(): void
    {
        $user = User::factory()->create();
        $product = $this->product('Deposit Only', 1000, true);
        $this->wallet($user, 'deposit', 5000);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->orderPayload($product, [
            'payment_strategy' => Order::PAYMENT_STRATEGY_CARD_100,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_strategy', 'items']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_deposit_product_checkout_requires_enough_deposit_balance(): void
    {
        $user = User::factory()->create();
        $product = $this->product('Deposit Low Balance', 5000, true);
        $this->wallet($user, 'deposit', 4999);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->orderPayload($product, [
            'payment_strategy' => Order::PAYMENT_STRATEGY_DEPOSIT_100,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('4999.00', $this->walletFor($user, 'deposit')->balance);
    }

    private function product(string $name, int $price, bool $depositOnly = false): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => 'CART-'.uniqid(),
            'price' => $price,
            'pv' => 2,
            'stock_quantity' => 10,
            'status' => 'active',
            'is_deposit_product' => $depositOnly,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function orderPayload(Product $product, array $extra = []): array
    {
        return [
            ...$this->basePayload(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            ...$extra,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        return [
            'recipient_name' => 'Safi Client',
            'phone' => '+77010000000',
            'city' => 'Алматы',
            'delivery_address' => 'Абая 10',
        ];
    }

    private function wallet(User $user, string $type, int $balance): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'currency' => 'KZT',
            'balance' => $balance,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
    }

    private function walletFor(User $user, string $type): Wallet
    {
        return $user->wallets()->where('type', $type)->firstOrFail()->refresh();
    }
}

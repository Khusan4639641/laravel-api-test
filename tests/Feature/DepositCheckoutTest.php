<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepositCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_100_checkout_debits_deposit_and_cashbacks_to_main_wallet(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Deposit Checkout',
            'sku' => 'DEP-CHECKOUT',
            'price' => 5000,
            'pv' => 50,
            'stock_quantity' => 3,
            'status' => 'active',
            'is_deposit_product' => true,
        ]);
        $this->wallet($user, 'deposit', 10000);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'payment_strategy' => Order::PAYMENT_STRATEGY_DEPOSIT_100,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'recipient_name' => 'Safi Client',
            'phone' => '+77010000000',
            'city' => 'Алматы',
            'delivery_address' => 'Абая 10',
        ])
            ->assertCreated()
            ->assertJsonPath('order.status', 'paid')
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.payment_strategy', Order::PAYMENT_STRATEGY_DEPOSIT_100)
            ->assertJsonPath('order.payment_strategy_label', '100% депозит')
            ->assertJsonPath('order.card_amount', '0.00')
            ->assertJsonPath('order.deposit_amount', '5000.00')
            ->assertJsonPath('order.total_pv', '0.00');

        $order = Order::query()->firstOrFail();

        $this->assertSame('5000.00', $order->total_amount);
        $this->assertSame('5000.00', $this->walletFor($user, 'deposit')->balance);
        $this->assertSame('1000.00', $this->walletFor($user, 'main')->balance);
        $this->assertSame(2, $product->refresh()->stock_quantity);
        $this->assertSame(0, Payment::query()->count());
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'deposit_product_purchase',
            'direction' => 'debit',
            'amount' => '5000.00',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'deposit_purchase_cashback',
            'direction' => 'credit',
            'amount' => '1000.00',
        ]);
        $this->assertSame('1000.00', BonusTransaction::query()->where('bonus_type', 'cashback')->firstOrFail()->amount);
        $this->assertSame(2, WalletTransaction::query()->count());
    }

    public function test_regular_product_cannot_use_deposit_100_strategy(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Regular Checkout',
            'sku' => 'REG-CHECKOUT',
            'price' => 5000,
            'pv' => 50,
            'stock_quantity' => 3,
            'status' => 'active',
        ]);
        $this->wallet($user, 'deposit', 10000);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'payment_strategy' => Order::PAYMENT_STRATEGY_DEPOSIT_100,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'recipient_name' => 'Safi Client',
            'phone' => '+77010000000',
            'city' => 'Алматы',
            'delivery_address' => 'Абая 10',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_strategy');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('10000.00', $this->walletFor($user, 'deposit')->balance);
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

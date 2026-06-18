<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutOrderService
{
    public function __construct(
        private readonly PvService $pvService,
        private readonly WalletService $walletService,
        private readonly BonusService $bonusService,
        private readonly OrderPaymentSplitService $paymentSplitService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(User $user, array $validated): Order
    {
        return DB::transaction(function () use ($user, $validated): Order {
            $requestedQuantities = collect($validated['items'])
                ->groupBy('product_id')
                ->map(fn ($items): int => $items->sum(fn (array $item): int => (int) $item['quantity']));

            $products = Product::query()
                ->whereIn('id', $requestedQuantities->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $hasDepositProducts = false;
            $hasRegularProducts = false;

            foreach ($requestedQuantities as $productId => $quantity) {
                /** @var Product|null $product */
                $product = $products->get($productId);

                if (! $product || $product->status !== 'active') {
                    throw ValidationException::withMessages([
                        'items' => 'Товар недоступен для заказа',
                    ]);
                }

                $hasDepositProducts = $hasDepositProducts || (bool) $product->is_deposit_product;
                $hasRegularProducts = $hasRegularProducts || ! (bool) $product->is_deposit_product;

                if ((int) $product->stock_quantity <= 0 || $quantity > (int) $product->stock_quantity) {
                    throw ValidationException::withMessages([
                        'items' => 'Недостаточно товара на складе',
                    ]);
                }
            }

            if ($hasDepositProducts && $hasRegularProducts) {
                throw ValidationException::withMessages([
                    'items' => 'Нельзя смешивать депозитные и обычные товары в одной корзине. Очистите корзину.',
                ]);
            }

            $subtotal = '0.00';
            $totalPv = '0.00';
            $preparedItems = [];

            foreach ($validated['items'] as $item) {
                /** @var Product|null $product */
                $product = $products->get($item['product_id']);

                $quantity = (int) $item['quantity'];
                $isDepositProduct = (bool) $product->is_deposit_product;
                $unitPv = $isDepositProduct ? '0.00' : $product->turnoverPv();
                $totalPrice = bcmul((string) $product->price, (string) $quantity, 2);
                $itemPv = bcmul($unitPv, (string) $quantity, 2);
                $subtotal = bcadd($subtotal, $totalPrice, 2);
                $totalPv = bcadd($totalPv, $itemPv, 2);

                $preparedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'total_price' => $totalPrice,
                    'unit_pv' => $unitPv,
                    'total_pv' => $itemPv,
                    'is_deposit_product' => $isDepositProduct,
                ];
            }

            $strategy = (string) ($validated['payment_strategy'] ?? Order::PAYMENT_STRATEGY_CARD_100);
            $split = $this->paymentSplitService->split($subtotal, $strategy);

            if ($hasDepositProducts && $strategy !== Order::PAYMENT_STRATEGY_DEPOSIT_100) {
                throw ValidationException::withMessages([
                    'payment_strategy' => 'Депозитные товары можно оплатить только с депозитного баланса.',
                    'items' => 'Этот товар доступен только за депозит',
                ]);
            }

            if ($hasRegularProducts && $strategy === Order::PAYMENT_STRATEGY_DEPOSIT_100) {
                throw ValidationException::withMessages([
                    'payment_strategy' => 'Обычные товары нельзя оплатить только депозитом.',
                ]);
            }

            if (bccomp($split['deposit_amount'], '0.00', 2) > 0) {
                $this->assertDepositBalanceIsEnough($user, $split['deposit_amount']);
            }

            $shippingAddress = $this->shippingAddress($validated);
            $isDepositOnlyOrder = $strategy === Order::PAYMENT_STRATEGY_DEPOSIT_100;

            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $this->makeOrderNumber($isDepositOnlyOrder ? 'DEP' : 'ORD'),
                'status' => $isDepositOnlyOrder ? 'paid' : 'pending',
                'payment_status' => $isDepositOnlyOrder ? 'paid' : 'unpaid',
                'payment_strategy' => $strategy,
                'payment_provider' => $isDepositOnlyOrder ? Order::PAYMENT_PROVIDER_DEPOSIT : null,
                'paid_at' => $isDepositOnlyOrder ? now() : null,
                'subtotal_amount' => $subtotal,
                'discount_amount' => 0,
                'total_amount' => $split['total_amount'],
                'card_amount' => $split['card_amount'],
                'deposit_amount' => $split['deposit_amount'],
                'total_pv' => $isDepositOnlyOrder ? '0.00' : $totalPv,
                'shipping_address' => $shippingAddress,
                'recipient_name' => $validated['recipient_name'] ?? null,
                'phone' => $validated['phone'],
                'city' => $validated['city'] ?? null,
                'delivery_address' => $validated['delivery_address'],
                'comment' => $validated['comment'] ?? null,
                'payment_meta' => [
                    'payment_strategy' => $strategy,
                    'payment_strategy_label' => Order::paymentStrategyLabel($strategy),
                    'total_amount' => $split['total_amount'],
                    'card_amount' => $split['card_amount'],
                    'deposit_amount' => $split['deposit_amount'],
                    ...($isDepositOnlyOrder ? [
                        'provider' => Order::PAYMENT_PROVIDER_DEPOSIT,
                        'wallet' => 'deposit',
                        'paid_internally' => true,
                    ] : []),
                ],
                'metadata' => [
                    'delivery_snapshot' => $shippingAddress,
                    'payment_strategy' => $strategy,
                    'payment_strategy_label' => Order::paymentStrategyLabel($strategy),
                    ...($isDepositOnlyOrder ? [
                        'source' => Order::SOURCE_DEPOSIT_PURCHASE,
                        'payment_method' => Order::PAYMENT_PROVIDER_DEPOSIT,
                        'payment_wallet' => 'deposit',
                        'cashback_percent' => '20',
                        'mlm_excluded' => true,
                        'turnover_excluded' => true,
                        'pv_excluded' => true,
                    ] : []),
                ],
            ]);

            foreach ($preparedItems as $preparedItem) {
                /** @var Product $product */
                $product = $preparedItem['product'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $preparedItem['quantity'],
                    'unit_price' => $preparedItem['unit_price'],
                    'total_price' => $preparedItem['total_price'],
                    'unit_pv' => $preparedItem['unit_pv'],
                    'total_pv' => $preparedItem['total_pv'],
                    'item_snapshot' => [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => (string) $product->price,
                        'pv' => $preparedItem['unit_pv'],
                        'pv_money_rate' => Product::PV_MONEY_RATE,
                        'image_path' => $product->image_path,
                        'image_url' => $this->productImageUrl($product),
                        'is_deposit_product' => $preparedItem['is_deposit_product'],
                    ],
                ]);
            }

            foreach ($requestedQuantities as $productId => $quantity) {
                /** @var Product $product */
                $product = $products->get($productId);
                $product->decrement('stock_quantity', $quantity);
            }

            if ($isDepositOnlyOrder) {
                $depositTransaction = $this->debitDepositForPaidOrder($user, $order, $split['deposit_amount']);
                $this->bonusService->accrueDepositPurchaseCashback($user, $split['total_amount'], $depositTransaction);

                return $order->refresh()->load('items.product');
            }

            if (bccomp($totalPv, '0', 2) > 0) {
                $this->pvService->accrueTurnoverToUplines(
                    $user,
                    $totalPv,
                    'product_order',
                    [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'pv_money_rate' => Product::PV_MONEY_RATE,
                        'turnover_amount' => bcmul($totalPv, (string) Product::PV_MONEY_RATE, 2),
                        'payment_strategy' => $strategy,
                    ],
                    $order,
                );
            }

            return $order->load('items.product');
        });
    }

    private function assertDepositBalanceIsEnough(User $user, string $amount): void
    {
        $this->walletService->createUserWallets($user);

        /** @var Wallet $depositWallet */
        $depositWallet = $user->wallets()
            ->where('type', 'deposit')
            ->lockForUpdate()
            ->firstOrFail();

        if (bccomp((string) $depositWallet->balance, $amount, 2) < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Недостаточно средств на депозитном балансе.',
            ]);
        }
    }

    private function debitDepositForPaidOrder(User $user, Order $order, string $amount): WalletTransaction
    {
        /** @var Wallet $depositWallet */
        $depositWallet = $user->wallets()
            ->where('type', 'deposit')
            ->lockForUpdate()
            ->firstOrFail();

        $transaction = $this->walletService->debit(
            $depositWallet,
            $amount,
            'deposit_product_purchase',
            $order,
            [
                'payment_strategy' => Order::PAYMENT_STRATEGY_DEPOSIT_100,
                'payment_strategy_label' => Order::paymentStrategyLabel(Order::PAYMENT_STRATEGY_DEPOSIT_100),
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'cashback_percent' => '20',
            ],
            'Оплата заказа с депозитного баланса',
        );

        $paymentMeta = is_array($order->payment_meta) ? $order->payment_meta : [];
        $paymentMeta['deposit_debit_transaction_id'] = $transaction->id;
        $paymentMeta['deposit_debit_status'] = 'debited';
        $paymentMeta['deposit_debited_at'] = now()->toISOString();
        $order->forceFill(['payment_meta' => $paymentMeta])->save();

        return $transaction;
    }

    private function makeOrderNumber(string $prefix): string
    {
        do {
            $number = $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function shippingAddress(array $validated): array
    {
        $shippingAddress = is_array($validated['shipping_address'] ?? null)
            ? $validated['shipping_address']
            : [];

        return array_filter(array_merge($shippingAddress, [
            'recipient_name' => $validated['recipient_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'city' => $validated['city'] ?? null,
            'delivery_address' => $validated['delivery_address'] ?? null,
            'address' => $validated['delivery_address'] ?? null,
            'comment' => $validated['comment'] ?? null,
        ]), fn ($value): bool => $value !== null && $value !== '');
    }

    private function productImageUrl(Product $product): ?string
    {
        $metadata = is_array($product->metadata) ? $product->metadata : [];
        $path = $product->image_path ?: ($metadata['image_url'] ?? $metadata['image'] ?? null);

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return asset(Storage::url($path));
    }
}

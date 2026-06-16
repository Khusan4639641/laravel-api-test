<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DepositPurchaseService
{
    public function __construct(
        private readonly BonusService $bonusService,
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * @return array{deposit_transaction: WalletTransaction, cashback_bonus: ?BonusTransaction, order: ?Order}
     */
    public function purchase(User $user, float|string $amount): array
    {
        return DB::transaction(function () use ($user, $amount): array {
            $amount = (string) $amount;

            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Deposit purchase amount must be greater than zero.',
                ]);
            }

            $this->walletService->createUserWallets($user);

            /** @var Wallet $depositWallet */
            $depositWallet = $user->wallets()
                ->where('type', 'deposit')
                ->lockForUpdate()
                ->firstOrFail();

            if (bccomp((string) $depositWallet->balance, $amount, 2) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient deposit wallet balance.',
                ]);
            }

            $depositTransaction = $this->walletService->debit(
                $depositWallet,
                $amount,
                'deposit_purchase',
                null,
                ['cashback_percent' => '20']
            );

            $cashbackBonus = $this->bonusService->accrueDepositPurchaseCashback(
                $user,
                $amount,
                $depositTransaction
            );

            return [
                'deposit_transaction' => $depositTransaction->refresh(),
                'cashback_bonus' => $cashbackBonus?->refresh(),
                'order' => null,
            ];
        });
    }

    /**
     * @return array{deposit_transaction: WalletTransaction, cashback_bonus: ?BonusTransaction, order: Order}
     */
    public function purchaseProduct(User $user, Product $product, int $quantity = 1): array
    {
        return DB::transaction(function () use ($user, $product, $quantity): array {
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Количество должно быть больше нуля.',
                ]);
            }

            /** @var Product $product */
            $product = Product::query()
                ->lockForUpdate()
                ->findOrFail($product->id);

            if ($product->status !== 'active' || ! $product->is_deposit_product) {
                throw ValidationException::withMessages([
                    'product_id' => 'Этот товар нельзя купить за депозит',
                ]);
            }

            if ((int) $product->stock_quantity <= 0 || $quantity > (int) $product->stock_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Недостаточно товара на складе',
                ]);
            }

            $amount = bcmul((string) $product->price, (string) $quantity, 2);
            $unitPv = '0.00';
            $totalPv = '0.00';

            $this->walletService->createUserWallets($user);

            /** @var Wallet $depositWallet */
            $depositWallet = $user->wallets()
                ->where('type', 'deposit')
                ->lockForUpdate()
                ->firstOrFail();

            if (bccomp((string) $depositWallet->balance, $amount, 2) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Недостаточно средств на депозитном счёте',
                ]);
            }

            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $this->makeOrderNumber(),
                'status' => 'paid',
                'payment_status' => 'paid',
                'payment_provider' => Order::PAYMENT_PROVIDER_DEPOSIT,
                'paid_at' => now(),
                'subtotal_amount' => $amount,
                'discount_amount' => 0,
                'total_amount' => $amount,
                'total_pv' => $totalPv,
                'payment_meta' => [
                    'provider' => Order::PAYMENT_PROVIDER_DEPOSIT,
                    'wallet' => 'deposit',
                    'paid_internally' => true,
                ],
                'metadata' => [
                    'source' => Order::SOURCE_DEPOSIT_PURCHASE,
                    'payment_method' => Order::PAYMENT_PROVIDER_DEPOSIT,
                    'payment_wallet' => 'deposit',
                    'cashback_percent' => '20',
                    'mlm_excluded' => true,
                    'turnover_excluded' => true,
                    'pv_excluded' => true,
                ],
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'total_price' => $amount,
                'unit_pv' => $unitPv,
                'total_pv' => $totalPv,
                'item_snapshot' => [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => (string) $product->price,
                    'pv' => '0.00',
                    'original_product_pv' => (string) $product->pv,
                    'is_deposit_product' => true,
                ],
            ]);

            $depositTransaction = $this->walletService->debit(
                $depositWallet,
                $amount,
                'deposit_product_purchase',
                $order,
                [
                    'cashback_percent' => '20',
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'order_id' => $order->id,
                ],
                'Покупка депозитного товара',
            );

            $product->decrement('stock_quantity', $quantity);

            $cashbackBonus = $this->bonusService->accrueDepositPurchaseCashback(
                $user,
                $amount,
                $depositTransaction
            );

            return [
                'deposit_transaction' => $depositTransaction->refresh(),
                'cashback_bonus' => $cashbackBonus?->refresh(),
                'order' => $order->refresh()->load('items.product'),
            ];
        });
    }

    private function makeOrderNumber(): string
    {
        do {
            $number = 'DEP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}

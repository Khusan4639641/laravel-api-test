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
                    'quantity' => 'Deposit purchase quantity must be greater than zero.',
                ]);
            }

            /** @var Product $product */
            $product = Product::query()
                ->lockForUpdate()
                ->findOrFail($product->id);

            if ($product->status !== 'active' || ! $product->is_deposit_product) {
                throw ValidationException::withMessages([
                    'product_id' => 'Product is not available in the deposit catalog.',
                ]);
            }

            if ((int) $product->stock_quantity <= 0 || $quantity > (int) $product->stock_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient product stock.',
                ]);
            }

            $amount = bcmul((string) $product->price, (string) $quantity, 2);
            $unitPv = $product->turnoverPv();
            $totalPv = bcmul($unitPv, (string) $quantity, 2);

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

            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $this->makeOrderNumber(),
                'status' => 'paid',
                'payment_status' => 'paid',
                'subtotal_amount' => $amount,
                'discount_amount' => 0,
                'total_amount' => $amount,
                'total_pv' => $totalPv,
                'metadata' => [
                    'source' => 'deposit_purchase',
                    'payment_wallet' => 'deposit',
                    'cashback_percent' => '20',
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
                    'pv' => $unitPv,
                    'pv_money_rate' => Product::PV_MONEY_RATE,
                    'is_deposit_product' => true,
                ],
            ]);

            $depositTransaction = $this->walletService->debit(
                $depositWallet,
                $amount,
                'deposit_purchase',
                $order,
                [
                    'cashback_percent' => '20',
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'order_id' => $order->id,
                ]
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

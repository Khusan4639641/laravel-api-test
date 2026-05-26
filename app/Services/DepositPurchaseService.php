<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepositPurchaseService
{
    public function __construct(
        private readonly BonusService $bonusService,
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * @return array{deposit_transaction: WalletTransaction, cashback_bonus: ?BonusTransaction}
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
            ];
        });
    }
}

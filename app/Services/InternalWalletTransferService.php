<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InternalWalletTransferService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * @return array{
     *     debit_transaction: WalletTransaction,
     *     credit_transaction: WalletTransaction,
     *     main_wallet: Wallet,
     *     deposit_wallet: Wallet,
     *     transfer_uuid: string
     * }
     */
    public function transfer(
        User $user,
        string $from,
        string $to,
        float|string $amount,
        ?string $comment = null,
    ): array {
        $from = $this->normalizeWalletType($from);
        $to = $this->normalizeWalletType($to);
        $amount = $this->decimal($amount);
        $comment = $this->cleanComment($comment);

        $this->validateTransfer($from, $to, $amount);

        return DB::transaction(function () use ($user, $from, $to, $amount, $comment): array {
            /** @var User $lockedUser */
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->where('account_status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $this->walletService->createUserWallets($lockedUser);

            $wallets = Wallet::query()
                ->where('user_id', $lockedUser->id)
                ->whereIn('type', ['main', 'deposit'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('type');

            /** @var Wallet|null $mainWallet */
            $mainWallet = $wallets->get('main');
            /** @var Wallet|null $depositWallet */
            $depositWallet = $wallets->get('deposit');

            if (! $mainWallet || ! $depositWallet) {
                throw ValidationException::withMessages([
                    'wallet' => 'Кошелек недоступен',
                ]);
            }

            if (bccomp((string) $mainWallet->balance, $amount, 2) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Недостаточно средств на основном балансе',
                ]);
            }

            $transferUuid = (string) Str::uuid();
            $metadata = [
                'transfer_uuid' => $transferUuid,
                'from' => $from,
                'to' => $to,
                'comment' => $comment,
            ];

            $debitTransaction = $this->walletService->debit(
                $mainWallet,
                $amount,
                'main_to_deposit_debit',
                null,
                $metadata,
                'Перевод между счетами: основной -> депозит',
            );

            $this->afterMainDebit($debitTransaction);

            $creditTransaction = $this->walletService->credit(
                $depositWallet,
                $amount,
                'main_to_deposit_credit',
                null,
                [
                    ...$metadata,
                    'debit_transaction_id' => $debitTransaction->id,
                ],
                'Перевод между счетами: пополнение депозита',
            );

            $debitTransaction->forceFill([
                'metadata' => [
                    ...($debitTransaction->metadata ?? []),
                    'credit_transaction_id' => $creditTransaction->id,
                ],
            ])->save();

            return [
                'debit_transaction' => $debitTransaction->refresh(),
                'credit_transaction' => $creditTransaction->refresh(),
                'main_wallet' => $mainWallet->refresh(),
                'deposit_wallet' => $depositWallet->refresh(),
                'transfer_uuid' => $transferUuid,
            ];
        });
    }

    protected function afterMainDebit(WalletTransaction $debitTransaction): void
    {
    }

    private function validateTransfer(string $from, string $to, string $amount): void
    {
        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Сумма перевода должна быть больше нуля',
            ]);
        }

        if ($from === $to) {
            throw ValidationException::withMessages([
                'to' => 'Счета перевода не должны совпадать',
            ]);
        }

        if ($from === 'cashback' || $to === 'cashback') {
            throw ValidationException::withMessages([
                'from' => 'Cashback не является отдельным счётом',
            ]);
        }

        if ($from === 'deposit' && $to === 'main') {
            throw ValidationException::withMessages([
                'from' => 'Перевод с депозитного баланса на основной недоступен',
            ]);
        }

        if ($from !== 'main') {
            throw ValidationException::withMessages([
                'from' => 'Перевод доступен только с основного баланса',
            ]);
        }

        if ($to !== 'deposit') {
            throw ValidationException::withMessages([
                'to' => 'Перевод доступен только на депозитный баланс',
            ]);
        }
    }

    private function normalizeWalletType(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return match ($value) {
            'available', 'wallet', 'main_wallet', 'main balance', 'основной' => 'main',
            'deposit_wallet', 'deposit balance', 'депозит' => 'deposit',
            default => $value,
        };
    }

    private function cleanComment(?string $comment): ?string
    {
        $comment = trim((string) $comment);

        return $comment === '' ? null : mb_substr($comment, 0, 1000);
    }

    private function decimal(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}

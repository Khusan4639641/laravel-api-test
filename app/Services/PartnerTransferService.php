<?php

namespace App\Services;

use App\Models\PartnerTransfer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartnerTransferService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    public function transfer(
        User $sender,
        int $recipientUserId,
        float|string $amount,
        ?string $comment = null,
        ?string $idempotencyKey = null,
    ): PartnerTransfer {
        $amount = $this->decimal($amount);
        $comment = $this->cleanComment($comment);
        $idempotencyKey = $this->cleanIdempotencyKey($idempotencyKey);

        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Сумма перевода должна быть больше нуля',
            ]);
        }

        if ((int) $sender->id === $recipientUserId) {
            throw ValidationException::withMessages([
                'recipient_user_id' => 'Нельзя переводить средства самому себе',
            ]);
        }

        return DB::transaction(function () use ($sender, $recipientUserId, $amount, $comment, $idempotencyKey): PartnerTransfer {
            if ($idempotencyKey !== null) {
                $existingTransfer = PartnerTransfer::query()
                    ->where('sender_user_id', $sender->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingTransfer) {
                    return $existingTransfer->load(['sender', 'recipient', 'senderTransaction', 'recipientTransaction']);
                }
            }

            /** @var User $lockedSender */
            $lockedSender = User::query()
                ->whereKey($sender->id)
                ->where('account_status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $recipient = $this->recipientForTransfer($recipientUserId);

            $this->walletService->createUserWallets($lockedSender);
            $this->walletService->createUserWallets($recipient);

            $walletIds = Wallet::query()
                ->whereIn('user_id', [$lockedSender->id, $recipient->id])
                ->where('type', 'main')
                ->pluck('id')
                ->all();

            $wallets = Wallet::query()
                ->whereIn('id', $walletIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            /** @var Wallet $senderWallet */
            $senderWallet = $wallets->get($lockedSender->id);
            /** @var Wallet $recipientWallet */
            $recipientWallet = $wallets->get($recipient->id);

            if (! $senderWallet || ! $recipientWallet) {
                throw ValidationException::withMessages([
                    'wallet' => 'Кошелек недоступен',
                ]);
            }

            if (bccomp((string) $senderWallet->balance, $amount, 2) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Недостаточно средств',
                ]);
            }

            $transfer = PartnerTransfer::query()->create([
                'uuid' => (string) Str::uuid(),
                'sender_user_id' => $lockedSender->id,
                'recipient_user_id' => $recipient->id,
                'amount' => $amount,
                'currency' => $senderWallet->currency ?: 'KZT',
                'status' => 'completed',
                'comment' => $comment,
                'idempotency_key' => $idempotencyKey,
                'meta' => [
                    'sender_name' => $lockedSender->name,
                    'recipient_name' => $recipient->name,
                ],
            ]);

            $senderTransaction = $this->walletService->debit(
                $senderWallet,
                $amount,
                'partner_transfer_out',
                $transfer,
                [
                    'transfer_id' => $transfer->id,
                    'transfer_uuid' => $transfer->uuid,
                    'counterparty_user_id' => $recipient->id,
                    'counterparty_name' => $recipient->name,
                    'comment' => $comment,
                ],
                "Перевод партнёру {$recipient->name}",
            );

            $this->afterSenderDebit($transfer, $senderTransaction);

            $recipientTransaction = $this->walletService->credit(
                $recipientWallet,
                $amount,
                'partner_transfer_in',
                $transfer,
                [
                    'transfer_id' => $transfer->id,
                    'transfer_uuid' => $transfer->uuid,
                    'counterparty_user_id' => $lockedSender->id,
                    'counterparty_name' => $lockedSender->name,
                    'comment' => $comment,
                ],
                "Перевод от партнёра {$lockedSender->name}",
            );

            $transfer->forceFill([
                'sender_transaction_id' => $senderTransaction->id,
                'recipient_transaction_id' => $recipientTransaction->id,
            ])->save();

            return $transfer->refresh()->load(['sender', 'recipient', 'senderTransaction', 'recipientTransaction']);
        });
    }

    protected function afterSenderDebit(PartnerTransfer $transfer, WalletTransaction $senderTransaction): void
    {
    }

    private function recipientForTransfer(int $recipientUserId): User
    {
        /** @var User|null $recipient */
        $recipient = User::withTrashed()
            ->whereKey($recipientUserId)
            ->lockForUpdate()
            ->first();

        if (! $recipient) {
            throw ValidationException::withMessages([
                'recipient_user_id' => 'Получатель не найден',
            ]);
        }

        if ($recipient->trashed() || $recipient->account_status !== 'active' || $recipient->role !== User::ROLE_USER) {
            throw ValidationException::withMessages([
                'recipient_user_id' => 'Получатель недоступен для перевода',
            ]);
        }

        return $recipient;
    }

    private function cleanComment(?string $comment): ?string
    {
        $comment = trim((string) $comment);

        return $comment === '' ? null : mb_substr($comment, 0, 1000);
    }

    private function cleanIdempotencyKey(?string $idempotencyKey): ?string
    {
        $idempotencyKey = trim((string) $idempotencyKey);

        return $idempotencyKey === '' ? null : mb_substr($idempotencyKey, 0, 120);
    }

    private function decimal(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}

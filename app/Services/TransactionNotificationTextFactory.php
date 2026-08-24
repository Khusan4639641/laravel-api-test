<?php

namespace App\Services;

use App\Models\BonusTransaction;
use App\Models\WalletTransaction;

class TransactionNotificationTextFactory
{
    /**
     * @return array<string, mixed>
     */
    public function makeForBonusTransaction(BonusTransaction $bonusTransaction): array
    {
        $type = $this->notificationTypeFromBonusType($bonusTransaction->bonus_type);
        $walletTransactionId = $bonusTransaction->wallet_transaction_id;

        return [
            'type' => $type,
            'title' => $this->localizedTitle($type),
            'message' => $this->localizedMessage($type, $this->formattedAmount($bonusTransaction->amount)),
            'bonus_transaction_id' => $bonusTransaction->id,
            'bonus_type' => $bonusTransaction->bonus_type,
            'amount' => $this->decimal($bonusTransaction->amount),
            'currency' => 'KZT',
            'source_user_id' => $bonusTransaction->source_user_id,
            'transaction_id' => $walletTransactionId,
            'wallet_transaction_id' => $walletTransactionId,
        ];
    }

    /**
     * @param  array<string, mixed>  $existingData
     * @return array<string, mixed>
     */
    public function makeForWalletTransaction(WalletTransaction $transaction, array $existingData = []): array
    {
        $type = (string) ($existingData['type'] ?? $this->notificationTypeFromWalletTransaction($transaction));
        $message = $this->isKnownBonusNotificationType($type)
            ? $this->localizedMessage($type, $this->formattedAmount($transaction->amount))
            : $this->localizedGenericTransactionMessage($transaction);

        return [
            ...$existingData,
            'type' => $type,
            'title' => $this->localizedTitle($type),
            'message' => $message,
            'amount' => $this->decimal($transaction->amount),
            'currency' => 'KZT',
            'direction' => $transaction->direction,
            'transaction_id' => $transaction->id,
            'wallet_transaction_id' => $transaction->id,
            'wallet_id' => $transaction->wallet_id,
            'transaction_type' => $transaction->type,
        ];
    }

    public function notificationTypeFromBonusType(string $bonusType): string
    {
        return match ($bonusType) {
            'referral' => 'referral_bonus',
            'binary' => 'binary_bonus',
            'status', 'status_bonus' => 'status_bonus',
            'cashback' => 'cashback',
            default => 'bonus_accrued',
        };
    }

    private function notificationTypeFromWalletTransaction(WalletTransaction $transaction): string
    {
        return match ($transaction->type) {
            'referral_bonus' => 'referral_bonus',
            'binary_bonus_main', 'binary_bonus_deposit' => 'binary_bonus',
            'status_bonus' => 'status_bonus',
            'x2_bonus', 'bonus_x2' => 'x2_bonus',
            'cashback', 'deposit_purchase_cashback' => 'cashback',
            default => 'transaction',
        };
    }

    private function isKnownBonusNotificationType(string $type): bool
    {
        return in_array($type, ['referral_bonus', 'binary_bonus', 'status_bonus', 'cashback', 'bonus_accrued'], true);
    }

    /**
     * @return array{ru: string, kk: string, ky: string, en: string, mn: string}
     */
    private function localizedTitle(string $type): array
    {
        return match ($type) {
            'referral_bonus' => [
                'ru' => 'Реферальный бонус',
                'kk' => 'Рефералдық бонус',
                'ky' => 'Рефералдык бонус',
                'en' => 'Referral bonus',
                'mn' => 'Урилгын бонус',
            ],
            'binary_bonus' => [
                'ru' => 'Бинарный бонус',
                'kk' => 'Бинарлық бонус',
                'ky' => 'Бинардык бонус',
                'en' => 'Binary bonus',
                'mn' => 'Хоёртын бонус',
            ],
            'status_bonus' => [
                'ru' => 'Статусный бонус',
                'kk' => 'Статустық бонус',
                'ky' => 'Статустук бонус',
                'en' => 'Status bonus',
                'mn' => 'Статусын бонус',
            ],
            'cashback' => [
                'ru' => 'Кэшбэк',
                'kk' => 'Кэшбэк',
                'ky' => 'Кэшбэк',
                'en' => 'Cashback',
                'mn' => 'Кэшбэк',
            ],
            'bonus_accrued' => [
                'ru' => 'Бонус начислен',
                'kk' => 'Бонус есептелді',
                'ky' => 'Бонус эсептелди',
                'en' => 'Bonus accrued',
                'mn' => 'Бонус нэмэгдлээ',
            ],
            default => [
                'ru' => 'Транзакция',
                'kk' => 'Транзакция',
                'ky' => 'Транзакция',
                'en' => 'Transaction',
                'mn' => 'Гүйлгээ',
            ],
        };
    }

    /**
     * @return array{ru: string, kk: string, ky: string, en: string, mn: string}
     */
    private function localizedMessage(string $type, string $amount): array
    {
        return match ($type) {
            'referral_bonus' => [
                'ru' => "Начислен реферальный бонус: {$amount} ₸.",
                'kk' => "Рефералдық бонус есептелді: {$amount} ₸.",
                'ky' => "Рефералдык бонус эсептелди: {$amount} ₸.",
                'en' => "Referral bonus accrued: {$amount} ₸.",
                'mn' => "Урилгын бонус нэмэгдлээ: {$amount} ₸.",
            ],
            'binary_bonus' => [
                'ru' => "Начислен бинарный бонус: {$amount} ₸.",
                'kk' => "Бинарлық бонус есептелді: {$amount} ₸.",
                'ky' => "Бинардык бонус эсептелди: {$amount} ₸.",
                'en' => "Binary bonus accrued: {$amount} ₸.",
                'mn' => "Хоёртын бонус нэмэгдлээ: {$amount} ₸.",
            ],
            'status_bonus' => [
                'ru' => "Начислен статусный бонус: {$amount} ₸.",
                'kk' => "Статустық бонус есептелді: {$amount} ₸.",
                'ky' => "Статустук бонус эсептелди: {$amount} ₸.",
                'en' => "Status bonus accrued: {$amount} ₸.",
                'mn' => "Статусын бонус нэмэгдлээ: {$amount} ₸.",
            ],
            'cashback' => [
                'ru' => "Начислен кэшбэк: {$amount} ₸.",
                'kk' => "Кэшбэк есептелді: {$amount} ₸.",
                'ky' => "Кэшбэк эсептелди: {$amount} ₸.",
                'en' => "Cashback accrued: {$amount} ₸.",
                'mn' => "Кэшбэк нэмэгдлээ: {$amount} ₸.",
            ],
            default => [
                'ru' => "Начислен бонус: {$amount} ₸.",
                'kk' => "Бонус есептелді: {$amount} ₸.",
                'ky' => "Бонус эсептелди: {$amount} ₸.",
                'en' => "Bonus accrued: {$amount} ₸.",
                'mn' => "Бонус нэмэгдлээ: {$amount} ₸.",
            ],
        };
    }

    /**
     * @return array{ru: string, kk: string, ky: string, en: string, mn: string}
     */
    private function localizedGenericTransactionMessage(WalletTransaction $transaction): array
    {
        $amount = $this->signedFormattedAmount($transaction);

        return [
            'ru' => "Транзакция скорректирована: {$amount} ₸.",
            'kk' => "Транзакция түзетілді: {$amount} ₸.",
            'ky' => "Транзакция оңдолду: {$amount} ₸.",
            'en' => "Transaction adjusted: {$amount} ₸.",
            'mn' => "Гүйлгээг засварлав: {$amount} ₸.",
        ];
    }

    private function formattedAmount(string|int|float $amount): string
    {
        return number_format((float) $amount, 0, '.', ' ');
    }

    private function signedFormattedAmount(WalletTransaction $transaction): string
    {
        $sign = match ($transaction->direction) {
            'credit' => '+',
            'debit' => '-',
            default => '',
        };

        return $sign.$this->formattedAmount($transaction->amount);
    }

    private function decimal(string|int|float|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}

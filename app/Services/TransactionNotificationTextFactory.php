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
            'status' => 'status_bonus',
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
     * @return array{ru: string, kz: string, kg: string, en: string, mn: string}
     */
    private function localizedTitle(string $type): array
    {
        return match ($type) {
            'referral_bonus' => [
                'ru' => 'Реферальный бонус',
                'kz' => 'Рефералдық бонус',
                'kg' => 'Рефералдык бонус',
                'en' => 'Referral bonus',
                'mn' => 'Урилгын бонус',
            ],
            'binary_bonus' => [
                'ru' => 'Бинарный бонус',
                'kz' => 'Бинарлық бонус',
                'kg' => 'Бинардык бонус',
                'en' => 'Binary bonus',
                'mn' => 'Хоёртын бонус',
            ],
            'status_bonus' => [
                'ru' => 'Статусный бонус',
                'kz' => 'Статустық бонус',
                'kg' => 'Статустук бонус',
                'en' => 'Status bonus',
                'mn' => 'Статусын бонус',
            ],
            'cashback' => [
                'ru' => 'Кэшбэк',
                'kz' => 'Кэшбэк',
                'kg' => 'Кэшбэк',
                'en' => 'Cashback',
                'mn' => 'Кэшбэк',
            ],
            'bonus_accrued' => [
                'ru' => 'Бонус начислен',
                'kz' => 'Бонус есептелді',
                'kg' => 'Бонус эсептелди',
                'en' => 'Bonus accrued',
                'mn' => 'Бонус нэмэгдлээ',
            ],
            default => [
                'ru' => 'Транзакция',
                'kz' => 'Транзакция',
                'kg' => 'Транзакция',
                'en' => 'Transaction',
                'mn' => 'Гүйлгээ',
            ],
        };
    }

    /**
     * @return array{ru: string, kz: string, kg: string, en: string, mn: string}
     */
    private function localizedMessage(string $type, string $amount): array
    {
        return match ($type) {
            'referral_bonus' => [
                'ru' => "Начислен реферальный бонус: {$amount} ₸.",
                'kz' => "Рефералдық бонус есептелді: {$amount} ₸.",
                'kg' => "Рефералдык бонус эсептелди: {$amount} ₸.",
                'en' => "Referral bonus accrued: {$amount} ₸.",
                'mn' => "Урилгын бонус нэмэгдлээ: {$amount} ₸.",
            ],
            'binary_bonus' => [
                'ru' => "Начислен бинарный бонус: {$amount} ₸.",
                'kz' => "Бинарлық бонус есептелді: {$amount} ₸.",
                'kg' => "Бинардык бонус эсептелди: {$amount} ₸.",
                'en' => "Binary bonus accrued: {$amount} ₸.",
                'mn' => "Хоёртын бонус нэмэгдлээ: {$amount} ₸.",
            ],
            'status_bonus' => [
                'ru' => "Начислен статусный бонус: {$amount} ₸.",
                'kz' => "Статустық бонус есептелді: {$amount} ₸.",
                'kg' => "Статустук бонус эсептелди: {$amount} ₸.",
                'en' => "Status bonus accrued: {$amount} ₸.",
                'mn' => "Статусын бонус нэмэгдлээ: {$amount} ₸.",
            ],
            'cashback' => [
                'ru' => "Начислен кэшбэк: {$amount} ₸.",
                'kz' => "Кэшбэк есептелді: {$amount} ₸.",
                'kg' => "Кэшбэк эсептелди: {$amount} ₸.",
                'en' => "Cashback accrued: {$amount} ₸.",
                'mn' => "Кэшбэк нэмэгдлээ: {$amount} ₸.",
            ],
            default => [
                'ru' => "Начислен бонус: {$amount} ₸.",
                'kz' => "Бонус есептелді: {$amount} ₸.",
                'kg' => "Бонус эсептелди: {$amount} ₸.",
                'en' => "Bonus accrued: {$amount} ₸.",
                'mn' => "Бонус нэмэгдлээ: {$amount} ₸.",
            ],
        };
    }

    /**
     * @return array{ru: string, kz: string, kg: string, en: string, mn: string}
     */
    private function localizedGenericTransactionMessage(WalletTransaction $transaction): array
    {
        $amount = $this->signedFormattedAmount($transaction);

        return [
            'ru' => "Транзакция скорректирована: {$amount} ₸.",
            'kz' => "Транзакция түзетілді: {$amount} ₸.",
            'kg' => "Транзакция оңдолду: {$amount} ₸.",
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

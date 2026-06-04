<?php

namespace App\Notifications;

use App\Models\BonusTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BonusAccruedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly BonusTransaction $bonusTransaction,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $type = $this->notificationType();
        $title = $this->localizedTitle($type)['en'];
        $message = $this->localizedMessage($type, $this->formattedAmount())['en'];

        return (new MailMessage)
            ->subject($title)
            ->greeting('Hello, '.$notifiable->name)
            ->line($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $type = $this->notificationType();
        $amount = (string) $this->bonusTransaction->amount;

        return [
            'type' => $type,
            'title' => $this->localizedTitle($type),
            'message' => $this->localizedMessage($type, $this->formattedAmount()),
            'bonus_transaction_id' => $this->bonusTransaction->id,
            'bonus_type' => $this->bonusTransaction->bonus_type,
            'amount' => $amount,
            'currency' => 'KZT',
            'source_user_id' => $this->bonusTransaction->source_user_id,
            'wallet_transaction_id' => $this->bonusTransaction->wallet_transaction_id,
        ];
    }

    private function notificationType(): string
    {
        return match ($this->bonusTransaction->bonus_type) {
            'referral' => 'referral_bonus',
            'binary' => 'binary_bonus',
            'status' => 'status_bonus',
            'cashback' => 'cashback',
            default => 'bonus_accrued',
        };
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
            default => [
                'ru' => 'Бонус начислен',
                'kz' => 'Бонус есептелді',
                'kg' => 'Бонус эсептелди',
                'en' => 'Bonus accrued',
                'mn' => 'Бонус нэмэгдлээ',
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

    private function formattedAmount(): string
    {
        return number_format((float) $this->bonusTransaction->amount, 0, '.', ' ');
    }
}

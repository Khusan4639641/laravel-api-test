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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bonus accrued')
            ->greeting('Hello, '.$notifiable->name)
            ->line('A '.$this->bonusTransaction->bonus_type.' bonus has been accrued.')
            ->line('Amount: '.$this->bonusTransaction->amount);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bonus_accrued',
            'bonus_transaction_id' => $this->bonusTransaction->id,
            'bonus_type' => $this->bonusTransaction->bonus_type,
            'amount' => $this->bonusTransaction->amount,
        ];
    }
}

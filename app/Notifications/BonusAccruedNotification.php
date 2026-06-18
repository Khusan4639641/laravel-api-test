<?php

namespace App\Notifications;

use App\Models\BonusTransaction;
use App\Services\TransactionNotificationTextFactory;
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
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();

        return (new MailMessage)
            ->subject($payload['title']['en'])
            ->greeting('Hello, '.$notifiable->name)
            ->line($payload['message']['en']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    /**
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
        return app(TransactionNotificationTextFactory::class)
            ->makeForBonusTransaction($this->bonusTransaction);
    }
}

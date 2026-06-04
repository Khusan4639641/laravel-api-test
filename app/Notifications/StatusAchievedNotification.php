<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StatusAchievedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $status,
        public readonly ?string $previousStatus,
        public readonly string $weakLegPv,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New MLM status achieved')
            ->greeting('Hello, '.$notifiable->name)
            ->line('Your MLM status has been updated to '.$this->status.'.')
            ->line('Weak leg PV: '.$this->weakLegPv);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'status_achieved',
            'status' => $this->status,
            'previous_status' => $this->previousStatus,
            'weak_leg_pv' => $this->weakLegPv,
        ];
    }
}

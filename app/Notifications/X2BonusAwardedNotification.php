<?php

namespace App\Notifications;

use App\Models\UserX2Bonus;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class X2BonusAwardedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly UserX2Bonus $x2Bonus,
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
            ->subject('X2 bonus awarded')
            ->greeting('Hello, '.$notifiable->name)
            ->line('Your X2 bonus has been awarded: '.$this->x2Bonus->code.'.')
            ->line('Reward: '.$this->x2Bonus->reward_text);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'x2_bonus_awarded',
            'user_x2_bonus_id' => $this->x2Bonus->id,
            'code' => $this->x2Bonus->code,
            'qualified_count' => $this->x2Bonus->qualified_count,
            'amount' => $this->x2Bonus->amount,
        ];
    }
}

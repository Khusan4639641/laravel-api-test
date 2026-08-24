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
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->statusLabel();

        return (new MailMessage)
            ->subject('New MLM status achieved')
            ->greeting('Hello, '.$notifiable->name)
            ->line('Congratulations! You reached '.$status.' status.')
            ->line('Weak leg PV: '.$this->weakLegPv);
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
        $status = $this->statusLabel();

        return [
            'type' => 'status_achieved',
            'title' => [
                'ru' => 'Новый статус',
                'kk' => 'Жаңа мәртебе',
                'ky' => 'Жаңы статус',
                'en' => 'New status',
                'mn' => 'Шинэ статус',
            ],
            'message' => [
                'ru' => "Поздравляем! Вы достигли статуса {$status}.",
                'kk' => "Құттықтаймыз! Сіз {$status} мәртебесіне жеттіңіз.",
                'ky' => "Куттуктайбыз! Сиз {$status} статусуна жеттиңиз.",
                'en' => "Congratulations! You reached {$status} status.",
                'mn' => "Баяр хүргэе! Та {$status} статуст хүрлээ.",
            ],
            'status' => $this->status,
            'status_label' => $status,
            'previous_status' => $this->previousStatus,
            'weak_leg_pv' => $this->weakLegPv,
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            'manager' => 'Manager',
            'leader' => 'Leader',
            'director' => 'Director',
            'bronze_director' => 'Bronze Director',
            'silver_director' => 'Silver Director',
            'gold_director' => 'Gold Director',
            'platinum_director' => 'Platinum Director',
            'emerald_director' => 'Emerald Director',
            'diamond_director' => 'Diamond Director',
            default => str($this->status)->replace('_', ' ')->title()->toString(),
        };
    }
}

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
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->localizedMessage($this->formattedAmount())['en'];

        return (new MailMessage)
            ->subject('X2 bonus awarded')
            ->greeting('Hello, '.$notifiable->name)
            ->line($message)
            ->line('Reward: '.$this->x2Bonus->reward_text);
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
        $amount = (string) $this->x2Bonus->amount;

        return [
            'type' => 'x2_bonus',
            'title' => [
                'ru' => 'X2 бонус',
                'kz' => 'X2 бонус',
                'kg' => 'X2 бонус',
                'en' => 'X2 bonus',
                'mn' => 'X2 бонус',
            ],
            'message' => $this->localizedMessage($this->formattedAmount()),
            'user_x2_bonus_id' => $this->x2Bonus->id,
            'code' => $this->x2Bonus->code,
            'qualified_count' => $this->x2Bonus->qualified_count,
            'amount' => $amount,
            'currency' => $this->x2Bonus->currency,
            'reward_text' => $this->x2Bonus->reward_text,
        ];
    }

    /**
     * @return array{ru: string, kz: string, kg: string, en: string, mn: string}
     */
    private function localizedMessage(string $amount): array
    {
        if ((float) $this->x2Bonus->amount > 0) {
            return [
                'ru' => "Начислен X2 бонус: {$amount} ₸.",
                'kz' => "X2 бонус есептелді: {$amount} ₸.",
                'kg' => "X2 бонус эсептелди: {$amount} ₸.",
                'en' => "X2 bonus awarded: {$amount} ₸.",
                'mn' => "X2 бонус нэмэгдлээ: {$amount} ₸.",
            ];
        }

        return [
            'ru' => 'Поздравляем! Вы выполнили условие X2 бонуса.',
            'kz' => 'Құттықтаймыз! Сіз X2 бонус шартын орындадыңыз.',
            'kg' => 'Куттуктайбыз! Сиз X2 бонус шартын аткардыңыз.',
            'en' => 'Congratulations! You qualified for an X2 bonus.',
            'mn' => 'Баяр хүргэе! Та X2 бонусын нөхцөлийг хангалаа.',
        ];
    }

    private function formattedAmount(): string
    {
        return number_format((float) $this->x2Bonus->amount, 0, '.', ' ');
    }
}

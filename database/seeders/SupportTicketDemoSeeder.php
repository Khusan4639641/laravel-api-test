<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupportTicketDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('login', 'aidar')->first();

        if (! $user) {
            return;
        }

        $tickets = [
            ['subject' => 'Где моя посылка?', 'category' => 'Другое', 'status' => 'open', 'message' => 'Подскажите статус доставки последнего заказа.'],
            ['subject' => 'Вопрос по статусному бонусу', 'category' => 'Вопрос по бонусам', 'status' => 'closed', 'message' => 'Когда начисляется статусный бонус?', 'reply' => 'После подтверждения условий маркетинг-плана.'],
            ['subject' => 'Не могу добавить карту', 'category' => 'Вопрос по выводу', 'status' => 'answered', 'message' => 'Форма не принимает реквизиты карты.', 'reply' => 'Проверьте формат и повторите отправку.'],
        ];

        foreach ($tickets as $ticket) {
            SupportTicket::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'subject' => $ticket['subject'],
                ],
                [
                    'category' => $ticket['category'],
                    'message' => $ticket['message'],
                    'status' => $ticket['status'],
                    'priority' => 'normal',
                    'admin_reply' => $ticket['reply'] ?? null,
                    'replied_at' => isset($ticket['reply']) ? now()->subDay() : null,
                    'closed_at' => $ticket['status'] === 'closed' ? now() : null,
                    'last_reply_at' => isset($ticket['reply']) ? now()->subDay() : null,
                ]
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupportTicketDemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->whereIn('login', ['aidar', 'erlan', 'alisa', 'gulnara', 'alexey'])
            ->get()
            ->keyBy('login');

        $support = User::query()->where('login', 'support')->first();

        if ($users->isEmpty() || ! $support) {
            return;
        }

        $tickets = [
            [
                'login' => 'aidar',
                'subject' => 'Где моя посылка?',
                'category' => 'Доставка',
                'status' => SupportTicket::STATUS_OPEN,
                'priority' => 'normal',
                'message' => 'Подскажите статус доставки последнего заказа ORD-DEMO-0001.',
                'messages' => [
                    ['author' => 'user', 'message' => 'Подскажите статус доставки последнего заказа ORD-DEMO-0001.'],
                ],
            ],
            [
                'login' => 'aidar',
                'subject' => 'Вопрос по статусному бонусу',
                'category' => 'Вопрос по бонусам',
                'status' => SupportTicket::STATUS_ANSWERED,
                'priority' => 'high',
                'message' => 'Когда начисляется статусный бонус за новый уровень?',
                'reply' => 'Статусный бонус начисляется после подтверждения PV и закрытия расчетного периода.',
                'messages' => [
                    ['author' => 'user', 'message' => 'Когда начисляется статусный бонус за новый уровень?'],
                    ['author' => 'support', 'message' => 'Статусный бонус начисляется после подтверждения PV и закрытия расчетного периода.'],
                ],
            ],
            [
                'login' => 'aidar',
                'subject' => 'Нужно обновить реквизиты',
                'category' => 'Вопрос по выводу',
                'status' => SupportTicket::STATUS_CLOSED,
                'priority' => 'normal',
                'message' => 'Хочу заменить карту для следующего вывода.',
                'reply' => 'Реквизиты обновлены. Следующая заявка будет обработана по новой карте.',
                'messages' => [
                    ['author' => 'user', 'message' => 'Хочу заменить карту для следующего вывода.'],
                    ['author' => 'support', 'message' => 'Реквизиты обновлены. Следующая заявка будет обработана по новой карте.'],
                    ['author' => 'user', 'message' => 'Спасибо, вопрос закрыт.'],
                ],
            ],
            [
                'login' => 'erlan',
                'subject' => 'Не могу добавить карту',
                'category' => 'Вопрос по выводу',
                'status' => SupportTicket::STATUS_IN_PROGRESS,
                'priority' => 'high',
                'message' => 'Форма не принимает реквизиты карты, появляется ошибка валидации.',
                'reply' => 'Мы проверяем формат реквизитов. Пришлите последние 4 цифры карты без полного номера.',
                'messages' => [
                    ['author' => 'user', 'message' => 'Форма не принимает реквизиты карты, появляется ошибка валидации.'],
                    ['author' => 'support', 'message' => 'Мы проверяем формат реквизитов. Пришлите последние 4 цифры карты без полного номера.'],
                ],
            ],
            [
                'login' => 'alisa',
                'subject' => 'Нужна консультация по апгрейду',
                'category' => 'Вопрос по пакету',
                'status' => SupportTicket::STATUS_OPEN,
                'priority' => 'normal',
                'message' => 'Хочу перейти с BUSINESS на VIP, подскажите сумму доплаты.',
                'messages' => [
                    ['author' => 'user', 'message' => 'Хочу перейти с BUSINESS на VIP, подскажите сумму доплаты.'],
                ],
            ],
            [
                'login' => 'gulnara',
                'subject' => 'Проверить начисление бинарного бонуса',
                'category' => 'Вопрос по бонусам',
                'status' => SupportTicket::STATUS_ANSWERED,
                'priority' => 'normal',
                'message' => 'За прошлую неделю вижу PV в ветках, но бонус ниже ожидаемого.',
                'reply' => 'Расчет выполнен по меньшей ветке с учетом уже использованного PV.',
                'messages' => [
                    ['author' => 'user', 'message' => 'За прошлую неделю вижу PV в ветках, но бонус ниже ожидаемого.'],
                    ['author' => 'support', 'message' => 'Расчет выполнен по меньшей ветке с учетом уже использованного PV.'],
                ],
            ],
        ];

        foreach ($tickets as $index => $ticket) {
            $user = $users->get($ticket['login']);

            if (! $user) {
                continue;
            }

            $supportTicket = SupportTicket::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'subject' => $ticket['subject'],
                ],
                [
                    'assigned_to' => in_array($ticket['status'], [SupportTicket::STATUS_IN_PROGRESS, SupportTicket::STATUS_ANSWERED, SupportTicket::STATUS_CLOSED], true) ? $support->id : null,
                    'category' => $ticket['category'],
                    'message' => $ticket['message'],
                    'status' => $ticket['status'],
                    'priority' => $ticket['priority'],
                    'admin_reply' => $ticket['reply'] ?? null,
                    'replied_at' => isset($ticket['reply']) ? now()->subDays(max(1, 6 - $index)) : null,
                    'closed_at' => $ticket['status'] === SupportTicket::STATUS_CLOSED ? now()->subDay() : null,
                    'last_reply_at' => now()->subDays(max(0, 5 - $index)),
                ]
            );

            foreach ($ticket['messages'] as $message) {
                $author = $message['author'] === 'support' ? $support : $user;
                $isStaff = $message['author'] === 'support';

                $supportTicket->messages()->firstOrCreate(
                    [
                        'user_id' => $author->id,
                        'message' => $message['message'],
                        'is_staff' => $isStaff,
                    ]
                );
            }
        }
    }
}

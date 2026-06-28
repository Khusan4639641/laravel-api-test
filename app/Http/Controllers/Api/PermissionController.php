<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\LocalizedValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $role = $request->user()?->role ?: 'user';
        $permissions = config("role_permissions.roles.{$role}");

        if (! is_array($permissions)) {
            $role = 'user';
            $permissions = config('role_permissions.roles.user');
        }

        return response()->json([
            'role' => $role,
            'label' => $this->translateLabel($permissions['label'] ?? 'Partner'),
            'redirect_after_login' => $permissions['redirect_after_login'] ?? '/dashboard',
            'allowed_routes' => $permissions['allowed_routes'] ?? [],
            'menu' => $this->translateMenu($permissions['menu'] ?? []),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $menu
     * @return array<int, array<string, mixed>>
     */
    private function translateMenu(array $menu): array
    {
        return collect($menu)
            ->map(function (array $item): array {
                if (isset($item['label']) && is_string($item['label'])) {
                    $item['label'] = $this->translateLabel($item['label']);
                }

                return $item;
            })
            ->all();
    }

    private function translateLabel(string $label): string
    {
        if (! request()->headers->has('Accept-Language')) {
            return $label;
        }

        $translations = [
            'Partner' => ['ru' => 'Партнёр', 'kz' => 'Серіктес', 'kg' => 'Өнөктөш', 'en' => 'Partner', 'mn' => 'Түнш'],
            'Support' => ['ru' => 'Поддержка', 'kz' => 'Қолдау', 'kg' => 'Колдоо', 'en' => 'Support', 'mn' => 'Дэмжлэг'],
            'Admin' => ['ru' => 'Admin', 'kz' => 'Admin', 'kg' => 'Admin', 'en' => 'Admin', 'mn' => 'Admin'],
            'Super Admin' => ['ru' => 'Super Admin', 'kz' => 'Super Admin', 'kg' => 'Super Admin', 'en' => 'Super Admin', 'mn' => 'Super Admin'],
            'Бухгалтер' => ['ru' => 'Бухгалтер', 'kz' => 'Бухгалтер', 'kg' => 'Бухгалтер', 'en' => 'Accountant', 'mn' => 'Нягтлан'],
            'Обзор' => ['ru' => 'Обзор', 'kz' => 'Шолу', 'kg' => 'Кыскача маалымат', 'en' => 'Overview', 'mn' => 'Тойм'],
            'Моя структура' => ['ru' => 'Моя структура', 'kz' => 'Менің құрылымым', 'kg' => 'Менин түзүмүм', 'en' => 'My structure', 'mn' => 'Миний бүтэц'],
            'Транзакции' => ['ru' => 'Транзакции', 'kz' => 'Транзакциялар', 'kg' => 'Транзакциялар', 'en' => 'Transactions', 'mn' => 'Гүйлгээ'],
            'Бонусы и вывод' => ['ru' => 'Бонусы и вывод', 'kz' => 'Бонустар және шығару', 'kg' => 'Бонустар жана чыгаруу', 'en' => 'Bonuses and withdrawals', 'mn' => 'Урамшуулал ба таталт'],
            'Пакет и статус' => ['ru' => 'Пакет и статус', 'kz' => 'Пакет және статус', 'kg' => 'Пакет жана статус', 'en' => 'Package and status', 'mn' => 'Багц ба статус'],
            'Продукты' => ['ru' => 'Продукты', 'kz' => 'Өнімдер', 'kg' => 'Өнүмдөр', 'en' => 'Products', 'mn' => 'Бүтээгдэхүүн'],
            'Мои заказы' => ['ru' => 'Мои заказы', 'kz' => 'Менің тапсырыстарым', 'kg' => 'Менин буйрутмаларым', 'en' => 'My Orders', 'mn' => 'Миний захиалгууд'],
            'Новости' => ['ru' => 'Новости', 'kz' => 'Жаңалықтар', 'kg' => 'Жаңылыктар', 'en' => 'News', 'mn' => 'Мэдээ'],
            'Профиль' => ['ru' => 'Профиль', 'kz' => 'Профиль', 'kg' => 'Профиль', 'en' => 'Profile', 'mn' => 'Профайл'],
            'Поддержка' => ['ru' => 'Поддержка', 'kz' => 'Қолдау', 'kg' => 'Колдоо', 'en' => 'Support', 'mn' => 'Дэмжлэг'],
            'Партнёры' => ['ru' => 'Партнёры', 'kz' => 'Серіктестер', 'kg' => 'Өнөктөштөр', 'en' => 'Partners', 'mn' => 'Түншүүд'],
            'Забыли пароль' => ['ru' => 'Забыли пароль', 'kz' => 'Құпиясөзді ұмытты', 'kg' => 'Сырсөздү унутту', 'en' => 'Forgot password', 'mn' => 'Нууц үг мартсан'],
            'Структура' => ['ru' => 'Структура', 'kz' => 'Құрылым', 'kg' => 'Түзүм', 'en' => 'Structure', 'mn' => 'Бүтэц'],
            'Заявки на вывод' => ['ru' => 'Заявки на вывод', 'kz' => 'Шығаруға өтінімдер', 'kg' => 'Чыгаруу өтүнмөлөрү', 'en' => 'Withdrawal requests', 'mn' => 'Татан авалтын хүсэлтүүд'],
            'Заказы' => ['ru' => 'Заказы', 'kz' => 'Тапсырыстар', 'kg' => 'Буйрутмалар', 'en' => 'Orders', 'mn' => 'Захиалгууд'],
            'Бонусы' => ['ru' => 'Бонусы', 'kz' => 'Бонустар', 'kg' => 'Бонустар', 'en' => 'Bonuses', 'mn' => 'Урамшуулал'],
            'Пакеты' => ['ru' => 'Пакеты', 'kz' => 'Пакеттер', 'kg' => 'Пакеттер', 'en' => 'Packages', 'mn' => 'Багцууд'],
            'Статусы' => ['ru' => 'Статусы', 'kz' => 'Статустар', 'kg' => 'Статустар', 'en' => 'Statuses', 'mn' => 'Статусууд'],
            'Отчёты' => ['ru' => 'Отчёты', 'kz' => 'Есептер', 'kg' => 'Отчёттор', 'en' => 'Reports', 'mn' => 'Тайлан'],
            'Настройки' => ['ru' => 'Настройки', 'kz' => 'Баптаулар', 'kg' => 'Жөндөөлөр', 'en' => 'Settings', 'mn' => 'Тохиргоо'],
        ];

        return LocalizedValue::get($translations[$label] ?? null, $label);
    }
}

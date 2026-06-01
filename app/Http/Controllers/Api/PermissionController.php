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
            'Partner' => ['ru' => 'Партнёр', 'kk' => 'Серіктес', 'en' => 'Partner', 'mn' => 'Түнш'],
            'Support' => ['ru' => 'Поддержка', 'kk' => 'Қолдау', 'en' => 'Support', 'mn' => 'Дэмжлэг'],
            'Admin' => ['ru' => 'Admin', 'kk' => 'Admin', 'en' => 'Admin', 'mn' => 'Admin'],
            'Super Admin' => ['ru' => 'Super Admin', 'kk' => 'Super Admin', 'en' => 'Super Admin', 'mn' => 'Super Admin'],
            'Бухгалтер' => ['ru' => 'Бухгалтер', 'kk' => 'Бухгалтер', 'en' => 'Accountant', 'mn' => 'Нягтлан'],
            'Обзор' => ['ru' => 'Обзор', 'kk' => 'Шолу', 'en' => 'Overview', 'mn' => 'Тойм'],
            'Моя структура' => ['ru' => 'Моя структура', 'kk' => 'Менің құрылымым', 'en' => 'My structure', 'mn' => 'Миний бүтэц'],
            'Транзакции' => ['ru' => 'Транзакции', 'kk' => 'Транзакциялар', 'en' => 'Transactions', 'mn' => 'Гүйлгээ'],
            'Бонусы и вывод' => ['ru' => 'Бонусы и вывод', 'kk' => 'Бонустар және шығару', 'en' => 'Bonuses and withdrawals', 'mn' => 'Урамшуулал ба таталт'],
            'Пакет и статус' => ['ru' => 'Пакет и статус', 'kk' => 'Пакет және статус', 'en' => 'Package and status', 'mn' => 'Багц ба статус'],
            'Продукты' => ['ru' => 'Продукты', 'kk' => 'Өнімдер', 'en' => 'Products', 'mn' => 'Бүтээгдэхүүн'],
            'Новости' => ['ru' => 'Новости', 'kk' => 'Жаңалықтар', 'en' => 'News', 'mn' => 'Мэдээ'],
            'Профиль' => ['ru' => 'Профиль', 'kk' => 'Профиль', 'en' => 'Profile', 'mn' => 'Профайл'],
            'Поддержка' => ['ru' => 'Поддержка', 'kk' => 'Қолдау', 'en' => 'Support', 'mn' => 'Дэмжлэг'],
            'Партнёры' => ['ru' => 'Партнёры', 'kk' => 'Серіктестер', 'en' => 'Partners', 'mn' => 'Түншүүд'],
            'Структура' => ['ru' => 'Структура', 'kk' => 'Құрылым', 'en' => 'Structure', 'mn' => 'Бүтэц'],
            'Заявки на вывод' => ['ru' => 'Заявки на вывод', 'kk' => 'Шығаруға өтінімдер', 'en' => 'Withdrawal requests', 'mn' => 'Татан авалтын хүсэлтүүд'],
            'Бонусы' => ['ru' => 'Бонусы', 'kk' => 'Бонустар', 'en' => 'Bonuses', 'mn' => 'Урамшуулал'],
            'Пакеты' => ['ru' => 'Пакеты', 'kk' => 'Пакеттер', 'en' => 'Packages', 'mn' => 'Багцууд'],
            'Статусы' => ['ru' => 'Статусы', 'kk' => 'Статустар', 'en' => 'Statuses', 'mn' => 'Статусууд'],
            'Отчёты' => ['ru' => 'Отчёты', 'kk' => 'Есептер', 'en' => 'Reports', 'mn' => 'Тайлан'],
            'Настройки' => ['ru' => 'Настройки', 'kk' => 'Баптаулар', 'en' => 'Settings', 'mn' => 'Тохиргоо'],
        ];

        return LocalizedValue::get($translations[$label] ?? null, $label);
    }
}

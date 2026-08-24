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
            'Partner' => ['ru' => 'Партнёр', 'kk' => 'Серіктес', 'ky' => 'Өнөктөш', 'en' => 'Partner', 'mn' => 'Түнш'],
            'Support' => ['ru' => 'Поддержка', 'kk' => 'Қолдау', 'ky' => 'Колдоо', 'en' => 'Support', 'mn' => 'Дэмжлэг'],
            'Admin' => ['ru' => 'Admin', 'kk' => 'Admin', 'ky' => 'Admin', 'en' => 'Admin', 'mn' => 'Admin'],
            'Super Admin' => ['ru' => 'Super Admin', 'kk' => 'Super Admin', 'ky' => 'Super Admin', 'en' => 'Super Admin', 'mn' => 'Super Admin'],
            'Бухгалтер' => ['ru' => 'Бухгалтер', 'kk' => 'Бухгалтер', 'ky' => 'Бухгалтер', 'en' => 'Accountant', 'mn' => 'Нягтлан'],
            'Обзор' => ['ru' => 'Обзор', 'kk' => 'Шолу', 'ky' => 'Кыскача маалымат', 'en' => 'Overview', 'mn' => 'Тойм'],
            'Моя структура' => ['ru' => 'Моя структура', 'kk' => 'Менің құрылымым', 'ky' => 'Менин түзүмүм', 'en' => 'My structure', 'mn' => 'Миний бүтэц'],
            'Транзакции' => ['ru' => 'Транзакции', 'kk' => 'Транзакциялар', 'ky' => 'Транзакциялар', 'en' => 'Transactions', 'mn' => 'Гүйлгээ'],
            'Бонусы и вывод' => ['ru' => 'Бонусы и вывод', 'kk' => 'Бонустар және шығару', 'ky' => 'Бонустар жана чыгаруу', 'en' => 'Bonuses and withdrawals', 'mn' => 'Урамшуулал ба таталт'],
            'Пакет и статус' => ['ru' => 'Пакет и статус', 'kk' => 'Пакет және статус', 'ky' => 'Пакет жана статус', 'en' => 'Package and status', 'mn' => 'Багц ба статус'],
            'Продукты' => ['ru' => 'Продукты', 'kk' => 'Өнімдер', 'ky' => 'Өнүмдөр', 'en' => 'Products', 'mn' => 'Бүтээгдэхүүн'],
            'Мои заказы' => ['ru' => 'Мои заказы', 'kk' => 'Менің тапсырыстарым', 'ky' => 'Менин буйрутмаларым', 'en' => 'My Orders', 'mn' => 'Миний захиалгууд'],
            'Новости' => ['ru' => 'Новости', 'kk' => 'Жаңалықтар', 'ky' => 'Жаңылыктар', 'en' => 'News', 'mn' => 'Мэдээ'],
            'Профиль' => ['ru' => 'Профиль', 'kk' => 'Профиль', 'ky' => 'Профиль', 'en' => 'Profile', 'mn' => 'Профайл'],
            'Поддержка' => ['ru' => 'Поддержка', 'kk' => 'Қолдау', 'ky' => 'Колдоо', 'en' => 'Support', 'mn' => 'Дэмжлэг'],
            'Партнёры' => ['ru' => 'Партнёры', 'kk' => 'Серіктестер', 'ky' => 'Өнөктөштөр', 'en' => 'Partners', 'mn' => 'Түншүүд'],
            'Забыли пароль' => ['ru' => 'Забыли пароль', 'kk' => 'Құпиясөзді ұмытты', 'ky' => 'Сырсөздү унутту', 'en' => 'Forgot password', 'mn' => 'Нууц үг мартсан'],
            'Структура' => ['ru' => 'Структура', 'kk' => 'Құрылым', 'ky' => 'Түзүм', 'en' => 'Structure', 'mn' => 'Бүтэц'],
            'Заявки на вывод' => ['ru' => 'Заявки на вывод', 'kk' => 'Шығаруға өтінімдер', 'ky' => 'Чыгаруу өтүнмөлөрү', 'en' => 'Withdrawal requests', 'mn' => 'Татан авалтын хүсэлтүүд'],
            'Заказы' => ['ru' => 'Заказы', 'kk' => 'Тапсырыстар', 'ky' => 'Буйрутмалар', 'en' => 'Orders', 'mn' => 'Захиалгууд'],
            'Бонусы' => ['ru' => 'Бонусы', 'kk' => 'Бонустар', 'ky' => 'Бонустар', 'en' => 'Bonuses', 'mn' => 'Урамшуулал'],
            'Пакеты' => ['ru' => 'Пакеты', 'kk' => 'Пакеттер', 'ky' => 'Пакеттер', 'en' => 'Packages', 'mn' => 'Багцууд'],
            'Статусы' => ['ru' => 'Статусы', 'kk' => 'Статустар', 'ky' => 'Статустар', 'en' => 'Statuses', 'mn' => 'Статусууд'],
            'Отчёты' => ['ru' => 'Отчёты', 'kk' => 'Есептер', 'ky' => 'Отчёттор', 'en' => 'Reports', 'mn' => 'Тайлан'],
            'Настройки' => ['ru' => 'Настройки', 'kk' => 'Баптаулар', 'ky' => 'Жөндөөлөр', 'en' => 'Settings', 'mn' => 'Тохиргоо'],
        ];

        return LocalizedValue::get($translations[$label] ?? null, $label);
    }
}

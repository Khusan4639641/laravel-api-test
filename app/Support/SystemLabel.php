<?php

namespace App\Support;

final class SystemLabel
{
    /**
     * @var array<string, array<string, array<string, string>>>
     */
    private const LABELS = [
        'packages' => [
            'start' => ['ru' => 'Старт', 'kz' => 'Старт', 'kg' => 'Старт', 'en' => 'Start', 'mn' => 'Старт'],
            'vip' => ['ru' => 'VIP', 'kz' => 'VIP', 'kg' => 'VIP', 'en' => 'VIP', 'mn' => 'VIP'],
            'elite' => ['ru' => 'Элит', 'kz' => 'Элит', 'kg' => 'Элит', 'en' => 'Elite', 'mn' => 'Элит'],
        ],
        'mlm_statuses' => [
            'user' => ['ru' => 'Партнёр', 'kz' => 'Серіктес', 'kg' => 'Өнөктөш', 'en' => 'Partner', 'mn' => 'Түнш'],
            'manager' => ['ru' => 'Менеджер', 'kz' => 'Менеджер', 'kg' => 'Менеджер', 'en' => 'Manager', 'mn' => 'Менежер'],
            'leader' => ['ru' => 'Лидер', 'kz' => 'Лидер', 'kg' => 'Лидер', 'en' => 'Leader', 'mn' => 'Лидер'],
            'director' => ['ru' => 'Директор', 'kz' => 'Директор', 'kg' => 'Директор', 'en' => 'Director', 'mn' => 'Директор'],
            'bronze_director' => ['ru' => 'Бронзовый директор', 'kz' => 'Қола директор', 'kg' => 'Коло директор', 'en' => 'Bronze Director', 'mn' => 'Хүрэл директор'],
            'silver_director' => ['ru' => 'Серебряный директор', 'kz' => 'Күміс директор', 'kg' => 'Күмүш директор', 'en' => 'Silver Director', 'mn' => 'Мөнгөн директор'],
            'gold_director' => ['ru' => 'Золотой директор', 'kz' => 'Алтын директор', 'kg' => 'Алтын директор', 'en' => 'Gold Director', 'mn' => 'Алтан директор'],
            'platinum_director' => ['ru' => 'Платиновый директор', 'kz' => 'Платина директор', 'kg' => 'Платина директор', 'en' => 'Platinum Director', 'mn' => 'Платинум директор'],
            'emerald_director' => ['ru' => 'Изумрудный директор', 'kz' => 'Изумруд директор', 'kg' => 'Изумруд директор', 'en' => 'Emerald Director', 'mn' => 'Маргад директор'],
            'diamond_director' => ['ru' => 'Бриллиантовый директор', 'kz' => 'Бриллиант директор', 'kg' => 'Бриллиант директор', 'en' => 'Diamond Director', 'mn' => 'Очир директор'],
        ],
        'account_statuses' => [
            'active' => ['ru' => 'Активен', 'kz' => 'Белсенді', 'kg' => 'Активдүү', 'en' => 'Active', 'mn' => 'Идэвхтэй'],
            'inactive' => ['ru' => 'Неактивно', 'kz' => 'Белсенді емес', 'kg' => 'Активдүү эмес', 'en' => 'Inactive', 'mn' => 'Идэвхгүй'],
            'blocked' => ['ru' => 'Заблокирован', 'kz' => 'Бұғатталған', 'kg' => 'Бөгөттөлгөн', 'en' => 'Blocked', 'mn' => 'Хаагдсан'],
        ],
        'order_statuses' => [
            'pending' => ['ru' => 'Ожидает подтверждения', 'kz' => 'Растауды күтуде', 'kg' => 'Ырастоону күтөт', 'en' => 'Pending confirmation', 'mn' => 'Баталгаажуулалт хүлээгдэж байна'],
            'confirmed' => ['ru' => 'Подтверждено', 'kz' => 'Расталды', 'kg' => 'Ырасталды', 'en' => 'Confirmed', 'mn' => 'Баталгаажсан'],
            'shipped' => ['ru' => 'Отправлен', 'kz' => 'Жіберілді', 'kg' => 'Жөнөтүлдү', 'en' => 'Shipped', 'mn' => 'Илгээгдсэн'],
            'completed' => ['ru' => 'Выполнен', 'kz' => 'Орындалды', 'kg' => 'Аткарылды', 'en' => 'Completed', 'mn' => 'Дууссан'],
            'cancelled' => ['ru' => 'Отменён', 'kz' => 'Болдырылды', 'kg' => 'Жокко чыгарылды', 'en' => 'Cancelled', 'mn' => 'Цуцлагдсан'],
        ],
        'payment_statuses' => [
            'unpaid' => ['ru' => 'Не оплачено', 'kz' => 'Төленбеген', 'kg' => 'Төлөнгөн эмес', 'en' => 'Unpaid', 'mn' => 'Төлөгдөөгүй'],
            'pending' => ['ru' => 'Ожидает оплаты', 'kz' => 'Төлемді күтуде', 'kg' => 'Төлөмдү күтөт', 'en' => 'Payment pending', 'mn' => 'Төлбөр хүлээгдэж байна'],
            'paid' => ['ru' => 'Оплачено', 'kz' => 'Төленді', 'kg' => 'Төлөндү', 'en' => 'Paid', 'mn' => 'Төлөгдсөн'],
            'failed' => ['ru' => 'Ошибка оплаты', 'kz' => 'Төлем қатесі', 'kg' => 'Төлөм катасы', 'en' => 'Payment failed', 'mn' => 'Төлбөрийн алдаа'],
            'refunded' => ['ru' => 'Возвращено', 'kz' => 'Қайтарылды', 'kg' => 'Кайтарылды', 'en' => 'Refunded', 'mn' => 'Буцаагдсан'],
            'cancelled' => ['ru' => 'Платеж отменен', 'kz' => 'Төлем болдырылмады', 'kg' => 'Төлөм жокко чыгарылды', 'en' => 'Payment cancelled', 'mn' => 'Төлбөр цуцлагдсан'],
        ],
        'transaction_statuses' => [
            'new' => ['ru' => 'Новая', 'kz' => 'Жаңа', 'kg' => 'Жаңы', 'en' => 'New', 'mn' => 'Шинэ'],
            'pending' => ['ru' => 'Ожидает', 'kz' => 'Күтуде', 'kg' => 'Күтүүдө', 'en' => 'Pending', 'mn' => 'Хүлээгдэж байна'],
            'processing' => ['ru' => 'В обработке', 'kz' => 'Өңделуде', 'kg' => 'Иштетилүүдө', 'en' => 'Processing', 'mn' => 'Боловсруулж байна'],
            'completed' => ['ru' => 'Завершено', 'kz' => 'Аяқталды', 'kg' => 'Аяктады', 'en' => 'Completed', 'mn' => 'Дууссан'],
            'approved' => ['ru' => 'Подтверждено', 'kz' => 'Расталды', 'kg' => 'Ырасталды', 'en' => 'Approved', 'mn' => 'Баталгаажсан'],
            'paid' => ['ru' => 'Выплачено', 'kz' => 'Төленді', 'kg' => 'Төлөндү', 'en' => 'Paid', 'mn' => 'Төлөгдсөн'],
            'rejected' => ['ru' => 'Отклонено', 'kz' => 'Қабылданбады', 'kg' => 'Четке кагылды', 'en' => 'Rejected', 'mn' => 'Татгалзсан'],
            'declined' => ['ru' => 'Отклонено', 'kz' => 'Қабылданбады', 'kg' => 'Четке кагылды', 'en' => 'Declined', 'mn' => 'Татгалзсан'],
            'failed' => ['ru' => 'Ошибка', 'kz' => 'Қате', 'kg' => 'Ката', 'en' => 'Failed', 'mn' => 'Алдаа'],
            'cancelled' => ['ru' => 'Отменено', 'kz' => 'Болдырылды', 'kg' => 'Жокко чыгарылды', 'en' => 'Cancelled', 'mn' => 'Цуцлагдсан'],
        ],
        'withdrawal_statuses' => [
            'new' => ['ru' => 'Новая', 'kz' => 'Жаңа', 'kg' => 'Жаңы', 'en' => 'New', 'mn' => 'Шинэ'],
            'pending' => ['ru' => 'Ожидает', 'kz' => 'Күтуде', 'kg' => 'Күтүүдө', 'en' => 'Pending', 'mn' => 'Хүлээгдэж байна'],
            'processing' => ['ru' => 'В обработке', 'kz' => 'Өңделуде', 'kg' => 'Иштетилүүдө', 'en' => 'Processing', 'mn' => 'Боловсруулж байна'],
            'in_progress' => ['ru' => 'В обработке', 'kz' => 'Өңделуде', 'kg' => 'Иштетилүүдө', 'en' => 'In progress', 'mn' => 'Боловсруулж байна'],
            'approved' => ['ru' => 'Подтверждено', 'kz' => 'Расталды', 'kg' => 'Ырасталды', 'en' => 'Approved', 'mn' => 'Баталгаажсан'],
            'completed' => ['ru' => 'Выплачено', 'kz' => 'Төленді', 'kg' => 'Төлөндү', 'en' => 'Paid', 'mn' => 'Төлөгдсөн'],
            'paid' => ['ru' => 'Выплачено', 'kz' => 'Төленді', 'kg' => 'Төлөндү', 'en' => 'Paid', 'mn' => 'Төлөгдсөн'],
            'rejected' => ['ru' => 'Отклонено', 'kz' => 'Қабылданбады', 'kg' => 'Четке кагылды', 'en' => 'Rejected', 'mn' => 'Татгалзсан'],
            'declined' => ['ru' => 'Отклонено', 'kz' => 'Қабылданбады', 'kg' => 'Четке кагылды', 'en' => 'Declined', 'mn' => 'Татгалзсан'],
            'failed' => ['ru' => 'Ошибка', 'kz' => 'Қате', 'kg' => 'Ката', 'en' => 'Failed', 'mn' => 'Алдаа'],
        ],
        'product_statuses' => [
            'active' => ['ru' => 'Активен', 'kz' => 'Белсенді', 'kg' => 'Активдүү', 'en' => 'Active', 'mn' => 'Идэвхтэй'],
            'inactive' => ['ru' => 'Неактивно', 'kz' => 'Белсенді емес', 'kg' => 'Активдүү эмес', 'en' => 'Inactive', 'mn' => 'Идэвхгүй'],
            'out_of_stock' => ['ru' => 'Нет в наличии', 'kz' => 'Қоймада жоқ', 'kg' => 'Кампада жок', 'en' => 'Out of stock', 'mn' => 'Нөөцгүй'],
        ],
        'wallet_directions' => [
            'credit' => ['ru' => 'Начисление', 'kz' => 'Есептеу', 'kg' => 'Чегерүү', 'en' => 'Credit', 'mn' => 'Нэмэгдэл'],
            'debit' => ['ru' => 'Списание', 'kz' => 'Шегеру', 'kg' => 'Кемитүү', 'en' => 'Debit', 'mn' => 'Хасалт'],
            'neutral' => ['ru' => 'Операция', 'kz' => 'Операция', 'kg' => 'Операция', 'en' => 'Operation', 'mn' => 'Үйлдэл'],
        ],
        'transaction_types' => [
            'withdrawal_hold' => ['ru' => 'Вывод: сумма в холде', 'kz' => 'Шығару: сома холдта', 'kg' => 'Чыгаруу: сумма холддо', 'en' => 'Withdrawal: amount on hold', 'mn' => 'Таталт: дүн түгжигдсэн'],
            'withdrawal_request' => ['ru' => 'Вывод: заявка создана', 'kz' => 'Шығару: өтінім құрылды', 'kg' => 'Чыгаруу: арыз түзүлдү', 'en' => 'Withdrawal request created', 'mn' => 'Таталтын хүсэлт үүссэн'],
            'withdrawal_approved' => ['ru' => 'Вывод: выплата подтверждена', 'kz' => 'Шығару: төлем расталды', 'kg' => 'Чыгаруу: төлөм ырасталды', 'en' => 'Withdrawal approved', 'mn' => 'Таталт баталгаажсан'],
            'withdrawal_rejected' => ['ru' => 'Вывод: заявка отклонена', 'kz' => 'Шығару: өтінім қабылданбады', 'kg' => 'Чыгаруу: арыз четке кагылды', 'en' => 'Withdrawal rejected', 'mn' => 'Таталтын хүсэлт татгалзсан'],
            'payout_completed' => ['ru' => 'Вывод: выплата завершена', 'kz' => 'Шығару: төлем аяқталды', 'kg' => 'Чыгаруу: төлөм аяктады', 'en' => 'Payout completed', 'mn' => 'Төлбөр дууссан'],
            'binary_bonus_pending' => ['ru' => 'Бинарный бонус в ожидании', 'kz' => 'Бинарлық бонус күтуде', 'kg' => 'Бинардык бонус күтүүдөө', 'en' => 'Binary bonus pending', 'mn' => 'Хоёртын бонус хүлээгдэж байна'],
            'binary_bonus_main' => ['ru' => 'Бинарный бонус: основной кошелёк', 'kz' => 'Бинарлық бонус: негізгі әмиян', 'kg' => 'Бинардык бонус: негизги капчык', 'en' => 'Binary bonus: main wallet', 'mn' => 'Хоёртын бонус: үндсэн хэтэвч'],
            'binary_bonus_deposit' => ['ru' => 'Бинарный бонус: депозит', 'kz' => 'Бинарлық бонус: депозит', 'kg' => 'Бинардык бонус: депозит', 'en' => 'Binary bonus: deposit', 'mn' => 'Хоёртын бонус: депозит'],
            'referral_bonus' => ['ru' => 'Реферальный бонус', 'kz' => 'Рефералдық бонус', 'kg' => 'Рефералдык бонус', 'en' => 'Referral bonus', 'mn' => 'Урилгын бонус'],
            'package_assignment' => ['ru' => 'Назначение пакета', 'kz' => 'Пакет тағайындау', 'kg' => 'Пакет дайындоо', 'en' => 'Package assignment', 'mn' => 'Багц оноолт'],
            'package_activation' => ['ru' => 'Покупка пакета', 'kz' => 'Пакет сатып алу', 'kg' => 'Пакет сатып алуу', 'en' => 'Package purchase', 'mn' => 'Багц худалдан авалт'],
            'package_upgrade' => ['ru' => 'Upgrade пакета', 'kz' => 'Пакет upgrade', 'kg' => 'Пакет upgrade', 'en' => 'Package upgrade', 'mn' => 'Багц upgrade'],
            'status_bonus' => ['ru' => 'Статусный бонус', 'kz' => 'Статус бонусы', 'kg' => 'Статус бонусу', 'en' => 'Status bonus', 'mn' => 'Статусын бонус'],
            'bonus_x2' => ['ru' => 'Bonus X2', 'kz' => 'Bonus X2', 'kg' => 'Bonus X2', 'en' => 'Bonus X2', 'mn' => 'Bonus X2'],
            'cashback' => ['ru' => 'Кэшбэк', 'kz' => 'Кэшбэк', 'kg' => 'Кэшбэк', 'en' => 'Cashback', 'mn' => 'Кэшбэк'],
            'deposit_purchase' => ['ru' => 'Покупка с депозитного кошелька', 'kz' => 'Депозит әмиянынан сатып алу', 'kg' => 'Депозит капчыктан сатып алуу', 'en' => 'Deposit wallet purchase', 'mn' => 'Депозит хэтэвчээр худалдан авалт'],
        ],
        'transaction_effects' => [
            'affects_balance' => ['ru' => 'Влияет на баланс', 'kz' => 'Балансқа әсер етеді', 'kg' => 'Балансқа таасир этет', 'en' => 'Affects balance', 'mn' => 'Үлдэгдэлд нөлөөлнө'],
            'does_not_affect_balance' => ['ru' => 'Не влияет на баланс', 'kz' => 'Балансқа әсер етпейді', 'kg' => 'Балансқа таасир этпейт', 'en' => 'Does not affect balance', 'mn' => 'Үлдэгдэлд нөлөөлөхгүй'],
        ],
    ];

    public static function label(string $group, mixed $code, mixed $fallback = null, ?string $language = null): string
    {
        $normalizedGroup = self::normalizeGroup($group);
        $normalizedCode = self::normalizeCode($code);

        if ($normalizedCode === '') {
            return self::fallback($fallback, '');
        }

        $translations = self::LABELS[$normalizedGroup][$normalizedCode] ?? null;

        return (string) LocalizedValue::get($translations, self::fallback($fallback, (string) $code), $language);
    }

    public static function package(mixed $code, mixed $fallback = null, ?string $language = null): string
    {
        return self::label('packages', $code, $fallback, $language);
    }

    public static function mlmStatus(mixed $code, mixed $fallback = null, ?string $language = null): string
    {
        return self::label('mlm_statuses', $code, $fallback, $language);
    }

    public static function accountStatus(mixed $code, mixed $fallback = null, ?string $language = null): string
    {
        return self::label('account_statuses', $code, $fallback, $language);
    }

    public static function orderStatus(mixed $code, mixed $fallback = null, ?string $language = null): string
    {
        return self::label('order_statuses', $code, $fallback, $language);
    }

    public static function transactionStatus(mixed $code, mixed $fallback = null, ?string $language = null): string
    {
        return self::label('transaction_statuses', $code, $fallback, $language);
    }

    public static function withdrawalStatus(mixed $code, mixed $fallback = null, ?string $language = null): string
    {
        return self::label('withdrawal_statuses', $code, $fallback, $language);
    }

    public static function productStatus(mixed $code, mixed $fallback = null, ?string $language = null): string
    {
        return self::label('product_statuses', $code, $fallback, $language);
    }

    private static function normalizeGroup(string $group): string
    {
        return strtolower(trim($group));
    }

    private static function normalizeCode(mixed $code): string
    {
        $normalized = strtolower(trim((string) $code));
        $normalized = preg_replace('/[^a-z0-9]+/i', '_', $normalized) ?? $normalized;

        return trim($normalized, '_');
    }

    private static function fallback(mixed $fallback, string $default): string
    {
        if (is_string($fallback) && trim($fallback) !== '') {
            return $fallback;
        }

        if (is_numeric($fallback)) {
            return (string) $fallback;
        }

        return $default;
    }
}

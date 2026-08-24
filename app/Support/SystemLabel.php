<?php

namespace App\Support;

final class SystemLabel
{
    /**
     * @var array<string, array<string, array<string, string>>>
     */
    private const LABELS = [
        'packages' => [
            'start' => ['ru' => 'START', 'kk' => 'START', 'ky' => 'START', 'en' => 'START', 'mn' => 'START'],
            'vip' => ['ru' => 'VIP', 'kk' => 'VIP', 'ky' => 'VIP', 'en' => 'VIP', 'mn' => 'VIP'],
            'elite' => ['ru' => 'ELITE', 'kk' => 'ELITE', 'ky' => 'ELITE', 'en' => 'ELITE', 'mn' => 'ELITE'],
        ],
        'mlm_statuses' => [
            'user' => ['ru' => 'Партнёр', 'kk' => 'Серіктес', 'ky' => 'Өнөктөш', 'en' => 'Partner', 'mn' => 'Түнш'],
            'manager' => ['ru' => 'Менеджер', 'kk' => 'Менеджер', 'ky' => 'Менеджер', 'en' => 'Manager', 'mn' => 'Менежер'],
            'leader' => ['ru' => 'Лидер', 'kk' => 'Лидер', 'ky' => 'Лидер', 'en' => 'Leader', 'mn' => 'Лидер'],
            'director' => ['ru' => 'Директор', 'kk' => 'Директор', 'ky' => 'Директор', 'en' => 'Director', 'mn' => 'Директор'],
            'bronze_director' => ['ru' => 'Бронзовый директор', 'kk' => 'Қола директор', 'ky' => 'Коло директор', 'en' => 'Bronze Director', 'mn' => 'Хүрэл директор'],
            'silver_director' => ['ru' => 'Серебряный директор', 'kk' => 'Күміс директор', 'ky' => 'Күмүш директор', 'en' => 'Silver Director', 'mn' => 'Мөнгөн директор'],
            'gold_director' => ['ru' => 'Золотой директор', 'kk' => 'Алтын директор', 'ky' => 'Алтын директор', 'en' => 'Gold Director', 'mn' => 'Алтан директор'],
            'platinum_director' => ['ru' => 'Платиновый директор', 'kk' => 'Платина директор', 'ky' => 'Платина директор', 'en' => 'Platinum Director', 'mn' => 'Платинум директор'],
            'emerald_director' => ['ru' => 'Изумрудный директор', 'kk' => 'Изумруд директор', 'ky' => 'Изумруд директор', 'en' => 'Emerald Director', 'mn' => 'Маргад директор'],
            'diamond_director' => ['ru' => 'Бриллиантовый директор', 'kk' => 'Бриллиант директор', 'ky' => 'Бриллиант директор', 'en' => 'Diamond Director', 'mn' => 'Очир директор'],
        ],
        'account_statuses' => [
            'active' => ['ru' => 'Активен', 'kk' => 'Белсенді', 'ky' => 'Активдүү', 'en' => 'Active', 'mn' => 'Идэвхтэй'],
            'inactive' => ['ru' => 'Неактивно', 'kk' => 'Белсенді емес', 'ky' => 'Активдүү эмес', 'en' => 'Inactive', 'mn' => 'Идэвхгүй'],
            'blocked' => ['ru' => 'Заблокирован', 'kk' => 'Бұғатталған', 'ky' => 'Бөгөттөлгөн', 'en' => 'Blocked', 'mn' => 'Хаагдсан'],
        ],
        'order_statuses' => [
            'pending' => ['ru' => 'Ожидает подтверждения', 'kk' => 'Растауды күтуде', 'ky' => 'Ырастоону күтөт', 'en' => 'Pending confirmation', 'mn' => 'Баталгаажуулалт хүлээгдэж байна'],
            'confirmed' => ['ru' => 'Подтверждено', 'kk' => 'Расталды', 'ky' => 'Ырасталды', 'en' => 'Confirmed', 'mn' => 'Баталгаажсан'],
            'shipped' => ['ru' => 'Отправлен', 'kk' => 'Жіберілді', 'ky' => 'Жөнөтүлдү', 'en' => 'Shipped', 'mn' => 'Илгээгдсэн'],
            'completed' => ['ru' => 'Выполнен', 'kk' => 'Орындалды', 'ky' => 'Аткарылды', 'en' => 'Completed', 'mn' => 'Дууссан'],
            'cancelled' => ['ru' => 'Отменён', 'kk' => 'Болдырылды', 'ky' => 'Жокко чыгарылды', 'en' => 'Cancelled', 'mn' => 'Цуцлагдсан'],
        ],
        'payment_statuses' => [
            'unpaid' => ['ru' => 'Не оплачено', 'kk' => 'Төленбеген', 'ky' => 'Төлөнгөн эмес', 'en' => 'Unpaid', 'mn' => 'Төлөгдөөгүй'],
            'pending' => ['ru' => 'Ожидает оплаты', 'kk' => 'Төлемді күтуде', 'ky' => 'Төлөмдү күтөт', 'en' => 'Payment pending', 'mn' => 'Төлбөр хүлээгдэж байна'],
            'paid' => ['ru' => 'Оплачено', 'kk' => 'Төленді', 'ky' => 'Төлөндү', 'en' => 'Paid', 'mn' => 'Төлөгдсөн'],
            'failed' => ['ru' => 'Ошибка оплаты', 'kk' => 'Төлем қатесі', 'ky' => 'Төлөм катасы', 'en' => 'Payment failed', 'mn' => 'Төлбөрийн алдаа'],
            'refunded' => ['ru' => 'Возвращено', 'kk' => 'Қайтарылды', 'ky' => 'Кайтарылды', 'en' => 'Refunded', 'mn' => 'Буцаагдсан'],
            'cancelled' => ['ru' => 'Платеж отменен', 'kk' => 'Төлем болдырылмады', 'ky' => 'Төлөм жокко чыгарылды', 'en' => 'Payment cancelled', 'mn' => 'Төлбөр цуцлагдсан'],
        ],
        'transaction_statuses' => [
            'new' => ['ru' => 'Новая', 'kk' => 'Жаңа', 'ky' => 'Жаңы', 'en' => 'New', 'mn' => 'Шинэ'],
            'pending' => ['ru' => 'Ожидает', 'kk' => 'Күтуде', 'ky' => 'Күтүүдө', 'en' => 'Pending', 'mn' => 'Хүлээгдэж байна'],
            'processing' => ['ru' => 'В обработке', 'kk' => 'Өңделуде', 'ky' => 'Иштетилүүдө', 'en' => 'Processing', 'mn' => 'Боловсруулж байна'],
            'completed' => ['ru' => 'Завершено', 'kk' => 'Аяқталды', 'ky' => 'Аяктады', 'en' => 'Completed', 'mn' => 'Дууссан'],
            'approved' => ['ru' => 'Подтверждено', 'kk' => 'Расталды', 'ky' => 'Ырасталды', 'en' => 'Approved', 'mn' => 'Баталгаажсан'],
            'paid' => ['ru' => 'Выплачено', 'kk' => 'Төленді', 'ky' => 'Төлөндү', 'en' => 'Paid', 'mn' => 'Төлөгдсөн'],
            'rejected' => ['ru' => 'Отклонено', 'kk' => 'Қабылданбады', 'ky' => 'Четке кагылды', 'en' => 'Rejected', 'mn' => 'Татгалзсан'],
            'declined' => ['ru' => 'Отклонено', 'kk' => 'Қабылданбады', 'ky' => 'Четке кагылды', 'en' => 'Declined', 'mn' => 'Татгалзсан'],
            'failed' => ['ru' => 'Ошибка', 'kk' => 'Қате', 'ky' => 'Ката', 'en' => 'Failed', 'mn' => 'Алдаа'],
            'cancelled' => ['ru' => 'Отменено', 'kk' => 'Болдырылды', 'ky' => 'Жокко чыгарылды', 'en' => 'Cancelled', 'mn' => 'Цуцлагдсан'],
        ],
        'withdrawal_statuses' => [
            'new' => ['ru' => 'Новая', 'kk' => 'Жаңа', 'ky' => 'Жаңы', 'en' => 'New', 'mn' => 'Шинэ'],
            'pending' => ['ru' => 'Ожидает', 'kk' => 'Күтуде', 'ky' => 'Күтүүдө', 'en' => 'Pending', 'mn' => 'Хүлээгдэж байна'],
            'processing' => ['ru' => 'В обработке', 'kk' => 'Өңделуде', 'ky' => 'Иштетилүүдө', 'en' => 'Processing', 'mn' => 'Боловсруулж байна'],
            'in_progress' => ['ru' => 'В обработке', 'kk' => 'Өңделуде', 'ky' => 'Иштетилүүдө', 'en' => 'In progress', 'mn' => 'Боловсруулж байна'],
            'approved' => ['ru' => 'Подтверждено', 'kk' => 'Расталды', 'ky' => 'Ырасталды', 'en' => 'Approved', 'mn' => 'Баталгаажсан'],
            'completed' => ['ru' => 'Выплачено', 'kk' => 'Төленді', 'ky' => 'Төлөндү', 'en' => 'Paid', 'mn' => 'Төлөгдсөн'],
            'paid' => ['ru' => 'Выплачено', 'kk' => 'Төленді', 'ky' => 'Төлөндү', 'en' => 'Paid', 'mn' => 'Төлөгдсөн'],
            'rejected' => ['ru' => 'Отклонено', 'kk' => 'Қабылданбады', 'ky' => 'Четке кагылды', 'en' => 'Rejected', 'mn' => 'Татгалзсан'],
            'declined' => ['ru' => 'Отклонено', 'kk' => 'Қабылданбады', 'ky' => 'Четке кагылды', 'en' => 'Declined', 'mn' => 'Татгалзсан'],
            'failed' => ['ru' => 'Ошибка', 'kk' => 'Қате', 'ky' => 'Ката', 'en' => 'Failed', 'mn' => 'Алдаа'],
        ],
        'product_statuses' => [
            'active' => ['ru' => 'Активен', 'kk' => 'Белсенді', 'ky' => 'Активдүү', 'en' => 'Active', 'mn' => 'Идэвхтэй'],
            'inactive' => ['ru' => 'Неактивно', 'kk' => 'Белсенді емес', 'ky' => 'Активдүү эмес', 'en' => 'Inactive', 'mn' => 'Идэвхгүй'],
            'out_of_stock' => ['ru' => 'Нет в наличии', 'kk' => 'Қоймада жоқ', 'ky' => 'Кампада жок', 'en' => 'Out of stock', 'mn' => 'Нөөцгүй'],
        ],
        'wallet_directions' => [
            'credit' => ['ru' => 'Начисление', 'kk' => 'Есептеу', 'ky' => 'Чегерүү', 'en' => 'Credit', 'mn' => 'Нэмэгдэл'],
            'debit' => ['ru' => 'Списание', 'kk' => 'Шегеру', 'ky' => 'Кемитүү', 'en' => 'Debit', 'mn' => 'Хасалт'],
            'neutral' => ['ru' => 'Операция', 'kk' => 'Операция', 'ky' => 'Операция', 'en' => 'Operation', 'mn' => 'Үйлдэл'],
        ],
        'transaction_types' => [
            'withdrawal_hold' => ['ru' => 'Вывод: сумма в холде', 'kk' => 'Шығару: сома холдта', 'ky' => 'Чыгаруу: сумма холддо', 'en' => 'Withdrawal: amount on hold', 'mn' => 'Таталт: дүн түгжигдсэн'],
            'withdrawal_request' => ['ru' => 'Вывод: заявка создана', 'kk' => 'Шығару: өтінім құрылды', 'ky' => 'Чыгаруу: арыз түзүлдү', 'en' => 'Withdrawal request created', 'mn' => 'Таталтын хүсэлт үүссэн'],
            'withdrawal_approved' => ['ru' => 'Вывод: выплата подтверждена', 'kk' => 'Шығару: төлем расталды', 'ky' => 'Чыгаруу: төлөм ырасталды', 'en' => 'Withdrawal approved', 'mn' => 'Таталт баталгаажсан'],
            'withdrawal_rejected' => ['ru' => 'Вывод: заявка отклонена', 'kk' => 'Шығару: өтінім қабылданбады', 'ky' => 'Чыгаруу: арыз четке кагылды', 'en' => 'Withdrawal rejected', 'mn' => 'Таталтын хүсэлт татгалзсан'],
            'payout_completed' => ['ru' => 'Вывод: выплата завершена', 'kk' => 'Шығару: төлем аяқталды', 'ky' => 'Чыгаруу: төлөм аяктады', 'en' => 'Payout completed', 'mn' => 'Төлбөр дууссан'],
            'binary_bonus_pending' => ['ru' => 'Бинарный бонус в ожидании', 'kk' => 'Бинарлық бонус күтуде', 'ky' => 'Бинардык бонус күтүүдөө', 'en' => 'Binary bonus pending', 'mn' => 'Хоёртын бонус хүлээгдэж байна'],
            'binary_bonus_main' => ['ru' => 'Бинарный бонус: основной кошелёк', 'kk' => 'Бинарлық бонус: негізгі әмиян', 'ky' => 'Бинардык бонус: негизги капчык', 'en' => 'Binary bonus: main wallet', 'mn' => 'Хоёртын бонус: үндсэн хэтэвч'],
            'binary_bonus_deposit' => ['ru' => 'Бинарный бонус: депозит', 'kk' => 'Бинарлық бонус: депозит', 'ky' => 'Бинардык бонус: депозит', 'en' => 'Binary bonus: deposit', 'mn' => 'Хоёртын бонус: депозит'],
            'referral_bonus' => ['ru' => 'Реферальный бонус', 'kk' => 'Рефералдық бонус', 'ky' => 'Рефералдык бонус', 'en' => 'Referral bonus', 'mn' => 'Урилгын бонус'],
            'order_payment' => ['ru' => 'Оплата заказа', 'kk' => 'Тапсырыс төлемі', 'ky' => 'Заказ төлөмү', 'en' => 'Order payment', 'mn' => 'Захиалгын төлбөр'],
            'order_deposit_payment' => ['ru' => 'Оплата заказа: депозит', 'kk' => 'Тапсырыс төлемі: депозит', 'ky' => 'Заказ төлөмү: депозит', 'en' => 'Order payment: deposit', 'mn' => 'Захиалгын төлбөр: депозит'],
            'order_deposit_refund' => ['ru' => 'Возврат депозитной части заказа', 'kk' => 'Тапсырыстың депозит бөлігін қайтару', 'ky' => 'Заказдын депозит бөлүгүн кайтаруу', 'en' => 'Order deposit part refund', 'mn' => 'Захиалгын депозит хэсгийн буцаалт'],
            'partner_transfer_out' => ['ru' => 'Перевод партнёру', 'kk' => 'Серіктеске аудару', 'ky' => 'Өнөктөшкө которуу', 'en' => 'Transfer to partner', 'mn' => 'Түнш рүү шилжүүлэг'],
            'partner_transfer_in' => ['ru' => 'Перевод от партнёра', 'kk' => 'Серіктестен аударым', 'ky' => 'Өнөктөштөн которуу', 'en' => 'Transfer from partner', 'mn' => 'Түншээс шилжүүлэг'],
            'main_to_deposit_debit' => ['ru' => 'Перевод на депозит: списание', 'kk' => 'Депозитке аудару: шегеру', 'ky' => 'Депозитке которуу: кемитүү', 'en' => 'Main to deposit transfer: debit', 'mn' => 'Депозит рүү шилжүүлэг: хасалт'],
            'main_to_deposit_credit' => ['ru' => 'Перевод на депозит: зачисление', 'kk' => 'Депозитке аудару: есептеу', 'ky' => 'Депозитке которуу: чегерүү', 'en' => 'Main to deposit transfer: credit', 'mn' => 'Депозит рүү шилжүүлэг: нэмэгдэл'],
            'package_assignment' => ['ru' => 'Назначение пакета', 'kk' => 'Пакет тағайындау', 'ky' => 'Пакет дайындоо', 'en' => 'Package assignment', 'mn' => 'Багц оноолт'],
            'package_activation' => ['ru' => 'Покупка пакета', 'kk' => 'Пакет сатып алу', 'ky' => 'Пакет сатып алуу', 'en' => 'Package purchase', 'mn' => 'Багц худалдан авалт'],
            'package_upgrade' => ['ru' => 'Upgrade пакета', 'kk' => 'Пакет upgrade', 'ky' => 'Пакет upgrade', 'en' => 'Package upgrade', 'mn' => 'Багц upgrade'],
            'package_auto_upgrade' => ['ru' => 'Автоматическое достижение пакета', 'kk' => 'Пакетке автоматты жету', 'ky' => 'Пакетке автоматтык жетүү', 'en' => 'Automatic package achievement', 'mn' => 'Багц автоматаар хүрсэн'],
            'status_bonus' => ['ru' => 'Статусный бонус', 'kk' => 'Статус бонусы', 'ky' => 'Статус бонусу', 'en' => 'Status bonus', 'mn' => 'Статусын бонус'],
            'bonus_x2' => ['ru' => 'Bonus X2', 'kk' => 'Bonus X2', 'ky' => 'Bonus X2', 'en' => 'Bonus X2', 'mn' => 'Bonus X2'],
            'cashback' => ['ru' => 'Кэшбэк', 'kk' => 'Кэшбэк', 'ky' => 'Кэшбэк', 'en' => 'Cashback', 'mn' => 'Кэшбэк'],
            'deposit_purchase' => ['ru' => 'Покупка с депозитного кошелька', 'kk' => 'Депозит әмиянынан сатып алу', 'ky' => 'Депозит капчыктан сатып алуу', 'en' => 'Deposit wallet purchase', 'mn' => 'Депозит хэтэвчээр худалдан авалт'],
            'deposit_product_purchase' => ['ru' => 'Покупка депозитного товара', 'kk' => 'Депозиттік тауарды сатып алу', 'ky' => 'Депозиттик товарды сатып алуу', 'en' => 'Deposit product purchase', 'mn' => 'Депозит бүтээгдэхүүн худалдан авалт'],
            'deposit_purchase_cashback' => ['ru' => 'Cashback 20% за покупку с депозита', 'kk' => 'Депозиттен сатып алу үшін 20% cashback', 'ky' => 'Депозиттен сатып алуу үчүн 20% cashback', 'en' => '20% cashback for deposit purchase', 'mn' => 'Депозит худалдан авалтын 20% cashback'],
        ],
        'transaction_effects' => [
            'affects_balance' => ['ru' => 'Влияет на баланс', 'kk' => 'Балансқа әсер етеді', 'ky' => 'Балансқа таасир этет', 'en' => 'Affects balance', 'mn' => 'Үлдэгдэлд нөлөөлнө'],
            'does_not_affect_balance' => ['ru' => 'Не влияет на баланс', 'kk' => 'Балансқа әсер етпейді', 'ky' => 'Балансқа таасир этпейт', 'en' => 'Does not affect balance', 'mn' => 'Үлдэгдэлд нөлөөлөхгүй'],
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

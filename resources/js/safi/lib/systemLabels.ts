import { getCurrentLanguage, normalizeLanguage, SupportedLanguage } from './language';

type TranslationSet = Record<SupportedLanguage, string>;
type LabelGroup = Record<string, TranslationSet>;

const systemLabels = {
  packages: {
    start: { ru: 'Старт', kz: 'Старт', kg: 'Старт', en: 'Start', mn: 'Старт' },
    vip: { ru: 'VIP', kz: 'VIP', kg: 'VIP', en: 'VIP', mn: 'VIP' },
    elite: { ru: 'Элит', kz: 'Элит', kg: 'Элит', en: 'Elite', mn: 'Элит' },
  },
  mlmStatuses: {
    user: { ru: 'Партнёр', kz: 'Серіктес', kg: 'Өнөктөш', en: 'Partner', mn: 'Түнш' },
    manager: { ru: 'Менеджер', kz: 'Менеджер', kg: 'Менеджер', en: 'Manager', mn: 'Менежер' },
    leader: { ru: 'Лидер', kz: 'Лидер', kg: 'Лидер', en: 'Leader', mn: 'Лидер' },
    director: { ru: 'Директор', kz: 'Директор', kg: 'Директор', en: 'Director', mn: 'Директор' },
    bronze_director: { ru: 'Бронзовый директор', kz: 'Қола директор', kg: 'Коло директор', en: 'Bronze Director', mn: 'Хүрэл директор' },
    silver_director: { ru: 'Серебряный директор', kz: 'Күміс директор', kg: 'Күмүш директор', en: 'Silver Director', mn: 'Мөнгөн директор' },
    gold_director: { ru: 'Золотой директор', kz: 'Алтын директор', kg: 'Алтын директор', en: 'Gold Director', mn: 'Алтан директор' },
    platinum_director: { ru: 'Платиновый директор', kz: 'Платина директор', kg: 'Платина директор', en: 'Platinum Director', mn: 'Платинум директор' },
    emerald_director: { ru: 'Изумрудный директор', kz: 'Изумруд директор', kg: 'Изумруд директор', en: 'Emerald Director', mn: 'Маргад директор' },
    diamond_director: { ru: 'Бриллиантовый директор', kz: 'Бриллиант директор', kg: 'Бриллиант директор', en: 'Diamond Director', mn: 'Очир директор' },
  },
  accountStatuses: {
    active: { ru: 'Активен', kz: 'Белсенді', kg: 'Активдүү', en: 'Active', mn: 'Идэвхтэй' },
    inactive: { ru: 'Неактивно', kz: 'Белсенді емес', kg: 'Активдүү эмес', en: 'Inactive', mn: 'Идэвхгүй' },
    blocked: { ru: 'Заблокирован', kz: 'Бұғатталған', kg: 'Бөгөттөлгөн', en: 'Blocked', mn: 'Хаагдсан' },
  },
  orderStatuses: {
    pending: { ru: 'Ожидает подтверждения', kz: 'Растауды күтуде', kg: 'Ырастоону күтөт', en: 'Pending confirmation', mn: 'Баталгаажуулалт хүлээгдэж байна' },
    confirmed: { ru: 'Подтверждено', kz: 'Расталды', kg: 'Ырасталды', en: 'Confirmed', mn: 'Баталгаажсан' },
    shipped: { ru: 'Отправлен', kz: 'Жіберілді', kg: 'Жөнөтүлдү', en: 'Shipped', mn: 'Илгээгдсэн' },
    completed: { ru: 'Выполнен', kz: 'Орындалды', kg: 'Аткарылды', en: 'Completed', mn: 'Дууссан' },
    cancelled: { ru: 'Отменён', kz: 'Болдырылды', kg: 'Жокко чыгарылды', en: 'Cancelled', mn: 'Цуцлагдсан' },
  },
  transactionStatuses: {
    new: { ru: 'Новая', kz: 'Жаңа', kg: 'Жаңы', en: 'New', mn: 'Шинэ' },
    pending: { ru: 'Ожидает', kz: 'Күтуде', kg: 'Күтүүдө', en: 'Pending', mn: 'Хүлээгдэж байна' },
    processing: { ru: 'В обработке', kz: 'Өңделуде', kg: 'Иштетилүүдө', en: 'Processing', mn: 'Боловсруулж байна' },
    completed: { ru: 'Завершено', kz: 'Аяқталды', kg: 'Аяктады', en: 'Completed', mn: 'Дууссан' },
    approved: { ru: 'Подтверждено', kz: 'Расталды', kg: 'Ырасталды', en: 'Approved', mn: 'Баталгаажсан' },
    paid: { ru: 'Выплачено', kz: 'Төленді', kg: 'Төлөндү', en: 'Paid', mn: 'Төлөгдсөн' },
    rejected: { ru: 'Отклонено', kz: 'Қабылданбады', kg: 'Четке кагылды', en: 'Rejected', mn: 'Татгалзсан' },
    declined: { ru: 'Отклонено', kz: 'Қабылданбады', kg: 'Четке кагылды', en: 'Declined', mn: 'Татгалзсан' },
    failed: { ru: 'Ошибка', kz: 'Қате', kg: 'Ката', en: 'Failed', mn: 'Алдаа' },
    cancelled: { ru: 'Отменено', kz: 'Болдырылды', kg: 'Жокко чыгарылды', en: 'Cancelled', mn: 'Цуцлагдсан' },
  },
  withdrawalStatuses: {
    new: { ru: 'Новая', kz: 'Жаңа', kg: 'Жаңы', en: 'New', mn: 'Шинэ' },
    pending: { ru: 'Ожидает', kz: 'Күтуде', kg: 'Күтүүдө', en: 'Pending', mn: 'Хүлээгдэж байна' },
    processing: { ru: 'В обработке', kz: 'Өңделуде', kg: 'Иштетилүүдө', en: 'Processing', mn: 'Боловсруулж байна' },
    in_progress: { ru: 'В обработке', kz: 'Өңделуде', kg: 'Иштетилүүдө', en: 'In progress', mn: 'Боловсруулж байна' },
    approved: { ru: 'Подтверждено', kz: 'Расталды', kg: 'Ырасталды', en: 'Approved', mn: 'Баталгаажсан' },
    completed: { ru: 'Выплачено', kz: 'Төленді', kg: 'Төлөндү', en: 'Paid', mn: 'Төлөгдсөн' },
    paid: { ru: 'Выплачено', kz: 'Төленді', kg: 'Төлөндү', en: 'Paid', mn: 'Төлөгдсөн' },
    rejected: { ru: 'Отклонено', kz: 'Қабылданбады', kg: 'Четке кагылды', en: 'Rejected', mn: 'Татгалзсан' },
    declined: { ru: 'Отклонено', kz: 'Қабылданбады', kg: 'Четке кагылды', en: 'Declined', mn: 'Татгалзсан' },
    failed: { ru: 'Ошибка', kz: 'Қате', kg: 'Ката', en: 'Failed', mn: 'Алдаа' },
  },
  productStatuses: {
    active: { ru: 'Активен', kz: 'Белсенді', kg: 'Активдүү', en: 'Active', mn: 'Идэвхтэй' },
    inactive: { ru: 'Неактивно', kz: 'Белсенді емес', kg: 'Активдүү эмес', en: 'Inactive', mn: 'Идэвхгүй' },
    out_of_stock: { ru: 'Нет в наличии', kz: 'Қоймада жоқ', kg: 'Кампада жок', en: 'Out of stock', mn: 'Нөөцгүй' },
  },
  transactionTypes: {
    withdrawal_hold: { ru: 'Вывод: сумма в холде', kz: 'Шығару: сома холдта', kg: 'Чыгаруу: сумма холддо', en: 'Withdrawal: amount on hold', mn: 'Таталт: дүн түгжигдсэн' },
    withdrawal_request: { ru: 'Вывод: заявка создана', kz: 'Шығару: өтінім құрылды', kg: 'Чыгаруу: арыз түзүлдү', en: 'Withdrawal request created', mn: 'Таталтын хүсэлт үүссэн' },
    withdrawal_approved: { ru: 'Вывод: выплата подтверждена', kz: 'Шығару: төлем расталды', kg: 'Чыгаруу: төлөм ырасталды', en: 'Withdrawal approved', mn: 'Таталт баталгаажсан' },
    withdrawal_rejected: { ru: 'Вывод: заявка отклонена', kz: 'Шығару: өтінім қабылданбады', kg: 'Чыгаруу: арыз четке кагылды', en: 'Withdrawal rejected', mn: 'Таталтын хүсэлт татгалзсан' },
    payout_completed: { ru: 'Вывод: выплата завершена', kz: 'Шығару: төлем аяқталды', kg: 'Чыгаруу: төлөм аяктады', en: 'Payout completed', mn: 'Төлбөр дууссан' },
    binary_bonus_pending: { ru: 'Бинарный бонус в ожидании', kz: 'Бинарлық бонус күтуде', kg: 'Бинардык бонус күтүүдөө', en: 'Binary bonus pending', mn: 'Хоёртын бонус хүлээгдэж байна' },
    binary_bonus_main: { ru: 'Бинарный бонус: основной кошелёк', kz: 'Бинарлық бонус: негізгі әмиян', kg: 'Бинардык бонус: негизги капчык', en: 'Binary bonus: main wallet', mn: 'Хоёртын бонус: үндсэн хэтэвч' },
    binary_bonus_deposit: { ru: 'Бинарный бонус: депозит', kz: 'Бинарлық бонус: депозит', kg: 'Бинардык бонус: депозит', en: 'Binary bonus: deposit', mn: 'Хоёртын бонус: депозит' },
    referral_bonus: { ru: 'Реферальный бонус', kz: 'Рефералдық бонус', kg: 'Рефералдык бонус', en: 'Referral bonus', mn: 'Урилгын бонус' },
    package_activation_credit: { ru: 'Начисление за покупку пакета', kz: 'Пакет сатып алу үшін есептеу', kg: 'Пакет сатып алуу үчүн чегерүү', en: 'Package purchase credit', mn: 'Багц худалдан авалтын нэмэгдэл' },
    package_upgrade_credit: { ru: 'Начисление за upgrade пакета', kz: 'Пакет upgrade үшін есептеу', kg: 'Пакет upgrade үчүн чегерүү', en: 'Package upgrade credit', mn: 'Багц upgrade нэмэгдэл' },
    admin_package_assignment_credit: { ru: 'Начисление за назначение пакета', kz: 'Пакет тағайындау үшін есептеу', kg: 'Пакет дайындоо үчүн чегерүү', en: 'Admin package assignment credit', mn: 'Багц оноолтын нэмэгдэл' },
    status_bonus: { ru: 'Статусный бонус', kz: 'Статус бонусы', kg: 'Статус бонусу', en: 'Status bonus', mn: 'Статусын бонус' },
    bonus_x2: { ru: 'Bonus X2', kz: 'Bonus X2', kg: 'Bonus X2', en: 'Bonus X2', mn: 'Bonus X2' },
    cashback: { ru: 'Кэшбэк', kz: 'Кэшбэк', kg: 'Кэшбэк', en: 'Cashback', mn: 'Кэшбэк' },
    deposit_purchase: { ru: 'Покупка с депозитного кошелька', kz: 'Депозит әмиянынан сатып алу', kg: 'Депозит капчыктан сатып алуу', en: 'Deposit wallet purchase', mn: 'Депозит хэтэвчээр худалдан авалт' },
  },
} satisfies Record<string, LabelGroup>;

const groupAliases: Record<string, keyof typeof systemLabels> = {
  packages: 'packages',
  package_labels: 'packages',
  mlm_statuses: 'mlmStatuses',
  mlmStatuses: 'mlmStatuses',
  account_statuses: 'accountStatuses',
  accountStatuses: 'accountStatuses',
  order_statuses: 'orderStatuses',
  orderStatuses: 'orderStatuses',
  transaction_statuses: 'transactionStatuses',
  transactionStatuses: 'transactionStatuses',
  withdrawal_statuses: 'withdrawalStatuses',
  withdrawalStatuses: 'withdrawalStatuses',
  product_statuses: 'productStatuses',
  productStatuses: 'productStatuses',
  transaction_types: 'transactionTypes',
  transactionTypes: 'transactionTypes',
};

// Handles DB codes such as GOLD_DIRECTOR without changing the stored value.
export function normalizeSystemCode(code?: string | number | null) {
  return String(code || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/gi, '_')
    .replace(/^_+|_+$/g, '');
}

export function systemLabel(group: keyof typeof systemLabels | string, code?: string | number | null, fallback?: string, language?: string) {
  const key = normalizeSystemCode(code);

  if (!key) {
    return fallback || '';
  }

  const groupName = groupAliases[group] || groupAliases[normalizeSystemCode(group)] || (group as keyof typeof systemLabels);
  const translations = systemLabels[groupName]?.[key];
  const lang = normalizeLanguage(language || getCurrentLanguage());

  return translations?.[lang] || translations?.ru || fallback || String(code || '');
}

export function packageLabel(code?: string | number | null, fallback?: string, language?: string) {
  return systemLabel('packages', code, fallback, language);
}

export function mlmStatusLabel(code?: string | number | null, fallback?: string, language?: string) {
  return systemLabel('mlmStatuses', code, fallback, language);
}

export function accountStatusLabel(code?: string | number | null, fallback?: string, language?: string) {
  return systemLabel('accountStatuses', code, fallback, language);
}

export function orderStatusLabel(code?: string | number | null, fallback?: string, language?: string) {
  return systemLabel('orderStatuses', code, fallback, language);
}

export function transactionStatusLabel(code?: string | number | null, fallback?: string, language?: string) {
  return systemLabel('transactionStatuses', code, fallback, language);
}

export function withdrawalStatusLabel(code?: string | number | null, fallback?: string, language?: string) {
  return systemLabel('withdrawalStatuses', code, fallback, language);
}

export function productStatusLabel(code?: string | number | null, fallback?: string, language?: string) {
  return systemLabel('productStatuses', code, fallback, language);
}

export function transactionTypeLabel(code?: string | number | null, fallback?: string, language?: string) {
  return systemLabel('transactionTypes', code, fallback, language);
}

export { systemLabels };

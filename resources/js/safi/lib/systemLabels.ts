import { getCurrentLanguage, normalizeLanguage, SupportedLanguage } from './language';

type TranslationSet = Record<SupportedLanguage, string>;
type LabelGroup = Record<string, TranslationSet>;

const systemLabels = {
  packages: {
    start: { ru: 'START', kk: 'START', ky: 'START', en: 'START', mn: 'START' },
    vip: { ru: 'VIP', kk: 'VIP', ky: 'VIP', en: 'VIP', mn: 'VIP' },
    elite: { ru: 'ELITE', kk: 'ELITE', ky: 'ELITE', en: 'ELITE', mn: 'ELITE' },
  },
  mlmStatuses: {
    user: { ru: 'Партнёр', kk: 'Серіктес', ky: 'Өнөктөш', en: 'Partner', mn: 'Түнш' },
    manager: { ru: 'Менеджер', kk: 'Менеджер', ky: 'Менеджер', en: 'Manager', mn: 'Менежер' },
    leader: { ru: 'Лидер', kk: 'Лидер', ky: 'Лидер', en: 'Leader', mn: 'Лидер' },
    director: { ru: 'Директор', kk: 'Директор', ky: 'Директор', en: 'Director', mn: 'Директор' },
    bronze_director: { ru: 'Бронзовый директор', kk: 'Қола директор', ky: 'Коло директор', en: 'Bronze Director', mn: 'Хүрэл директор' },
    silver_director: { ru: 'Серебряный директор', kk: 'Күміс директор', ky: 'Күмүш директор', en: 'Silver Director', mn: 'Мөнгөн директор' },
    gold_director: { ru: 'Золотой директор', kk: 'Алтын директор', ky: 'Алтын директор', en: 'Gold Director', mn: 'Алтан директор' },
    platinum_director: { ru: 'Платиновый директор', kk: 'Платина директор', ky: 'Платина директор', en: 'Platinum Director', mn: 'Платинум директор' },
    emerald_director: { ru: 'Изумрудный директор', kk: 'Изумруд директор', ky: 'Изумруд директор', en: 'Emerald Director', mn: 'Маргад директор' },
    diamond_director: { ru: 'Бриллиантовый директор', kk: 'Бриллиант директор', ky: 'Бриллиант директор', en: 'Diamond Director', mn: 'Очир директор' },
  },
  accountStatuses: {
    active: { ru: 'Активен', kk: 'Белсенді', ky: 'Активдүү', en: 'Active', mn: 'Идэвхтэй' },
    inactive: { ru: 'Неактивно', kk: 'Белсенді емес', ky: 'Активдүү эмес', en: 'Inactive', mn: 'Идэвхгүй' },
    blocked: { ru: 'Заблокирован', kk: 'Бұғатталған', ky: 'Бөгөттөлгөн', en: 'Blocked', mn: 'Хаагдсан' },
  },
  orderStatuses: {
    pending: { ru: 'Ожидает подтверждения', kk: 'Растауды күтуде', ky: 'Ырастоону күтөт', en: 'Pending confirmation', mn: 'Баталгаажуулалт хүлээгдэж байна' },
    confirmed: { ru: 'Подтверждено', kk: 'Расталды', ky: 'Ырасталды', en: 'Confirmed', mn: 'Баталгаажсан' },
    shipped: { ru: 'Отправлен', kk: 'Жіберілді', ky: 'Жөнөтүлдү', en: 'Shipped', mn: 'Илгээгдсэн' },
    completed: { ru: 'Выполнен', kk: 'Орындалды', ky: 'Аткарылды', en: 'Completed', mn: 'Дууссан' },
    cancelled: { ru: 'Отменён', kk: 'Болдырылды', ky: 'Жокко чыгарылды', en: 'Cancelled', mn: 'Цуцлагдсан' },
  },
  transactionStatuses: {
    new: { ru: 'Новая', kk: 'Жаңа', ky: 'Жаңы', en: 'New', mn: 'Шинэ' },
    pending: { ru: 'Ожидает', kk: 'Күтуде', ky: 'Күтүүдө', en: 'Pending', mn: 'Хүлээгдэж байна' },
    processing: { ru: 'В обработке', kk: 'Өңделуде', ky: 'Иштетилүүдө', en: 'Processing', mn: 'Боловсруулж байна' },
    completed: { ru: 'Завершено', kk: 'Аяқталды', ky: 'Аяктады', en: 'Completed', mn: 'Дууссан' },
    approved: { ru: 'Подтверждено', kk: 'Расталды', ky: 'Ырасталды', en: 'Approved', mn: 'Баталгаажсан' },
    paid: { ru: 'Выплачено', kk: 'Төленді', ky: 'Төлөндү', en: 'Paid', mn: 'Төлөгдсөн' },
    rejected: { ru: 'Отклонено', kk: 'Қабылданбады', ky: 'Четке кагылды', en: 'Rejected', mn: 'Татгалзсан' },
    declined: { ru: 'Отклонено', kk: 'Қабылданбады', ky: 'Четке кагылды', en: 'Declined', mn: 'Татгалзсан' },
    failed: { ru: 'Ошибка', kk: 'Қате', ky: 'Ката', en: 'Failed', mn: 'Алдаа' },
    cancelled: { ru: 'Отменено', kk: 'Болдырылды', ky: 'Жокко чыгарылды', en: 'Cancelled', mn: 'Цуцлагдсан' },
  },
  withdrawalStatuses: {
    new: { ru: 'Новая', kk: 'Жаңа', ky: 'Жаңы', en: 'New', mn: 'Шинэ' },
    pending: { ru: 'Ожидает', kk: 'Күтуде', ky: 'Күтүүдө', en: 'Pending', mn: 'Хүлээгдэж байна' },
    processing: { ru: 'В обработке', kk: 'Өңделуде', ky: 'Иштетилүүдө', en: 'Processing', mn: 'Боловсруулж байна' },
    in_progress: { ru: 'В обработке', kk: 'Өңделуде', ky: 'Иштетилүүдө', en: 'In progress', mn: 'Боловсруулж байна' },
    approved: { ru: 'Подтверждено', kk: 'Расталды', ky: 'Ырасталды', en: 'Approved', mn: 'Баталгаажсан' },
    completed: { ru: 'Выплачено', kk: 'Төленді', ky: 'Төлөндү', en: 'Paid', mn: 'Төлөгдсөн' },
    paid: { ru: 'Выплачено', kk: 'Төленді', ky: 'Төлөндү', en: 'Paid', mn: 'Төлөгдсөн' },
    rejected: { ru: 'Отклонено', kk: 'Қабылданбады', ky: 'Четке кагылды', en: 'Rejected', mn: 'Татгалзсан' },
    declined: { ru: 'Отклонено', kk: 'Қабылданбады', ky: 'Четке кагылды', en: 'Declined', mn: 'Татгалзсан' },
    failed: { ru: 'Ошибка', kk: 'Қате', ky: 'Ката', en: 'Failed', mn: 'Алдаа' },
  },
  productStatuses: {
    active: { ru: 'Активен', kk: 'Белсенді', ky: 'Активдүү', en: 'Active', mn: 'Идэвхтэй' },
    inactive: { ru: 'Неактивно', kk: 'Белсенді емес', ky: 'Активдүү эмес', en: 'Inactive', mn: 'Идэвхгүй' },
    out_of_stock: { ru: 'Нет в наличии', kk: 'Қоймада жоқ', ky: 'Кампада жок', en: 'Out of stock', mn: 'Нөөцгүй' },
  },
  transactionTypes: {
    withdrawal_hold: { ru: 'Вывод: сумма в холде', kk: 'Шығару: сома холдта', ky: 'Чыгаруу: сумма холддо', en: 'Withdrawal: amount on hold', mn: 'Таталт: дүн түгжигдсэн' },
    withdrawal_request: { ru: 'Вывод: заявка создана', kk: 'Шығару: өтінім құрылды', ky: 'Чыгаруу: арыз түзүлдү', en: 'Withdrawal request created', mn: 'Таталтын хүсэлт үүссэн' },
    withdrawal_approved: { ru: 'Вывод: выплата подтверждена', kk: 'Шығару: төлем расталды', ky: 'Чыгаруу: төлөм ырасталды', en: 'Withdrawal approved', mn: 'Таталт баталгаажсан' },
    withdrawal_rejected: { ru: 'Вывод: заявка отклонена', kk: 'Шығару: өтінім қабылданбады', ky: 'Чыгаруу: арыз четке кагылды', en: 'Withdrawal rejected', mn: 'Таталтын хүсэлт татгалзсан' },
    payout_completed: { ru: 'Вывод: выплата завершена', kk: 'Шығару: төлем аяқталды', ky: 'Чыгаруу: төлөм аяктады', en: 'Payout completed', mn: 'Төлбөр дууссан' },
    binary_bonus_pending: { ru: 'Бинарный бонус в ожидании', kk: 'Бинарлық бонус күтуде', ky: 'Бинардык бонус күтүүдөө', en: 'Binary bonus pending', mn: 'Хоёртын бонус хүлээгдэж байна' },
    binary_bonus_main: { ru: 'Бинарный бонус: основной кошелёк', kk: 'Бинарлық бонус: негізгі әмиян', ky: 'Бинардык бонус: негизги капчык', en: 'Binary bonus: main wallet', mn: 'Хоёртын бонус: үндсэн хэтэвч' },
    binary_bonus_deposit: { ru: 'Бинарный бонус: депозит', kk: 'Бинарлық бонус: депозит', ky: 'Бинардык бонус: депозит', en: 'Binary bonus: deposit', mn: 'Хоёртын бонус: депозит' },
    referral_bonus: { ru: 'Реферальный бонус', kk: 'Рефералдық бонус', ky: 'Рефералдык бонус', en: 'Referral bonus', mn: 'Урилгын бонус' },
    order_payment: { ru: 'Оплата заказа', kk: 'Тапсырыс төлемі', ky: 'Заказ төлөмү', en: 'Order payment', mn: 'Захиалгын төлбөр' },
    partner_transfer_out: { ru: 'Перевод партнёру', kk: 'Серіктеске аудару', ky: 'Өнөктөшкө которуу', en: 'Transfer to partner', mn: 'Түнш рүү шилжүүлэг' },
    partner_transfer_in: { ru: 'Перевод от партнёра', kk: 'Серіктестен аударым', ky: 'Өнөктөштөн которуу', en: 'Transfer from partner', mn: 'Түншээс шилжүүлэг' },
    main_to_deposit_debit: { ru: 'Перевод на депозит: списание', kk: 'Депозитке аудару: шегеру', ky: 'Депозитке которуу: кемитүү', en: 'Main to deposit transfer: debit', mn: 'Депозит рүү шилжүүлэг: хасалт' },
    main_to_deposit_credit: { ru: 'Перевод на депозит: зачисление', kk: 'Депозитке аудару: есептеу', ky: 'Депозитке которуу: чегерүү', en: 'Main to deposit transfer: credit', mn: 'Депозит рүү шилжүүлэг: нэмэгдэл' },
    package_assignment: { ru: 'Назначение пакета', kk: 'Пакет тағайындау', ky: 'Пакет дайындоо', en: 'Package assignment', mn: 'Багц оноолт' },
    package_activation: { ru: 'Покупка пакета', kk: 'Пакет сатып алу', ky: 'Пакет сатып алуу', en: 'Package purchase', mn: 'Багц худалдан авалт' },
    package_upgrade: { ru: 'Upgrade пакета', kk: 'Пакет upgrade', ky: 'Пакет upgrade', en: 'Package upgrade', mn: 'Багц upgrade' },
    package_auto_upgrade: { ru: 'Автоматическое достижение пакета', kk: 'Пакетке автоматты жету', ky: 'Пакетке автоматтык жетүү', en: 'Automatic package achievement', mn: 'Багц автоматаар хүрсэн' },
    status_bonus: { ru: 'Статусный бонус', kk: 'Статус бонусы', ky: 'Статус бонусу', en: 'Status bonus', mn: 'Статусын бонус' },
    bonus_x2: { ru: 'Bonus X2', kk: 'Bonus X2', ky: 'Bonus X2', en: 'Bonus X2', mn: 'Bonus X2' },
    cashback: { ru: 'Кэшбэк', kk: 'Кэшбэк', ky: 'Кэшбэк', en: 'Cashback', mn: 'Кэшбэк' },
    deposit_purchase: { ru: 'Покупка с депозитного кошелька', kk: 'Депозит әмиянынан сатып алу', ky: 'Депозит капчыктан сатып алуу', en: 'Deposit wallet purchase', mn: 'Депозит хэтэвчээр худалдан авалт' },
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

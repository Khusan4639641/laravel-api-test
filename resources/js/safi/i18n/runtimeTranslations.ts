import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import ru from '../locales/ru.json';
import kk from '../locales/kk.json';
import en from '../locales/en.json';
import mn from '../locales/mn.json';
import { normalizeLanguage, SupportedLanguage } from '../lib/language';

type TranslationEntry = Partial<Record<SupportedLanguage, string>>;

const localeResources: Record<SupportedLanguage, Record<string, unknown>> = { ru, kk, en, mn };

const manualTranslations: Record<string, TranslationEntry> = {
  'Загрузка...': { ru: 'Загрузка...', kk: 'Жүктелуде...', en: 'Loading...', mn: 'Ачааллаж байна...' },
  'Загрузка кабинета': { ru: 'Загрузка кабинета', kk: 'Кабинет жүктелуде', en: 'Loading dashboard', mn: 'Кабинет ачааллаж байна' },
  'Проверка доступа': { ru: 'Проверка доступа', kk: 'Қолжетімділік тексерілуде', en: 'Checking access', mn: 'Хандалтыг шалгаж байна' },
  'Кабинет недоступен': { ru: 'Кабинет недоступен', kk: 'Кабинет қолжетімсіз', en: 'Dashboard unavailable', mn: 'Кабинет боломжгүй' },
  'Не удалось подтвердить сессию пользователя.': { ru: 'Не удалось подтвердить сессию пользователя.', kk: 'Пайдаланушы сессиясын растау мүмкін болмады.', en: 'Could not verify the user session.', mn: 'Хэрэглэгчийн сессийг баталгаажуулж чадсангүй.' },
  'Повторить': { ru: 'Повторить', kk: 'Қайталау', en: 'Retry', mn: 'Дахин оролдох' },
  'Уведомления': { ru: 'Уведомления', kk: 'Хабарламалар', en: 'Notifications', mn: 'Мэдэгдэл' },
  'Открыть меню': { ru: 'Открыть меню', kk: 'Мәзірді ашу', en: 'Open menu', mn: 'Цэс нээх' },
  'Закрыть меню': { ru: 'Закрыть меню', kk: 'Мәзірді жабу', en: 'Close menu', mn: 'Цэс хаах' },
  'Глобальный поиск': { ru: 'Глобальный поиск', kk: 'Жалпы іздеу', en: 'Global search', mn: 'Ерөнхий хайлт' },
  'Навигация': { ru: 'Навигация', kk: 'Навигация', en: 'Navigation', mn: 'Навигаци' },
  'Управление': { ru: 'Управление', kk: 'Басқару', en: 'Management', mn: 'Удирдлага' },
  'Выйти': { ru: 'Выйти', kk: 'Шығу', en: 'Log out', mn: 'Гарах' },
  'Обзор': { ru: 'Обзор', kk: 'Шолу', en: 'Overview', mn: 'Тойм' },
  'Партнёры': { ru: 'Партнёры', kk: 'Серіктестер', en: 'Partners', mn: 'Түншүүд' },
  'Партнеры': { ru: 'Партнёры', kk: 'Серіктестер', en: 'Partners', mn: 'Түншүүд' },
  'Структура': { ru: 'Структура', kk: 'Құрылым', en: 'Structure', mn: 'Бүтэц' },
  'Транзакции': { ru: 'Транзакции', kk: 'Транзакциялар', en: 'Transactions', mn: 'Гүйлгээ' },
  'Заявки на вывод': { ru: 'Заявки на вывод', kk: 'Шығаруға өтінімдер', en: 'Withdrawal requests', mn: 'Татан авалтын хүсэлтүүд' },
  'Бонусы': { ru: 'Бонусы', kk: 'Бонустар', en: 'Bonuses', mn: 'Урамшуулал' },
  'Пакеты': { ru: 'Пакеты', kk: 'Пакеттер', en: 'Packages', mn: 'Багцууд' },
  'Статусы': { ru: 'Статусы', kk: 'Статустар', en: 'Statuses', mn: 'Статусууд' },
  'Продукты': { ru: 'Продукты', kk: 'Өнімдер', en: 'Products', mn: 'Бүтээгдэхүүн' },
  'Товары': { ru: 'Товары', kk: 'Тауарлар', en: 'Products', mn: 'Бүтээгдэхүүн' },
  'Новости': { ru: 'Новости', kk: 'Жаңалықтар', en: 'News', mn: 'Мэдээ' },
  'Поддержка': { ru: 'Поддержка', kk: 'Қолдау', en: 'Support', mn: 'Дэмжлэг' },
  'Отчёты': { ru: 'Отчёты', kk: 'Есептер', en: 'Reports', mn: 'Тайлан' },
  'Настройки': { ru: 'Настройки', kk: 'Баптаулар', en: 'Settings', mn: 'Тохиргоо' },
  'Профиль': { ru: 'Профиль', kk: 'Профиль', en: 'Profile', mn: 'Профайл' },
  'Dashboard': { ru: 'Кабинет', kk: 'Кабинет', en: 'Dashboard', mn: 'Кабинет' },
  'Пакет': { ru: 'Пакет', kk: 'Пакет', en: 'Package', mn: 'Багц' },
  'Статус': { ru: 'Статус', kk: 'Статус', en: 'Status', mn: 'Статус' },
  'Партнёр': { ru: 'Партнёр', kk: 'Серіктес', en: 'Partner', mn: 'Түнш' },
  'Партнер': { ru: 'Партнёр', kk: 'Серіктес', en: 'Partner', mn: 'Түнш' },
  'Пользователь': { ru: 'Пользователь', kk: 'Пайдаланушы', en: 'User', mn: 'Хэрэглэгч' },
  'Бухгалтер': { ru: 'Бухгалтер', kk: 'Бухгалтер', en: 'Accountant', mn: 'Нягтлан' },
  'Сохранить': { ru: 'Сохранить', kk: 'Сақтау', en: 'Save', mn: 'Хадгалах' },
  'Сохранение...': { ru: 'Сохранение...', kk: 'Сақталуда...', en: 'Saving...', mn: 'Хадгалж байна...' },
  'Отмена': { ru: 'Отмена', kk: 'Бас тарту', en: 'Cancel', mn: 'Цуцлах' },
  'Добавить': { ru: 'Добавить', kk: 'Қосу', en: 'Add', mn: 'Нэмэх' },
  'Удалить': { ru: 'Удалить', kk: 'Жою', en: 'Delete', mn: 'Устгах' },
  'Редактировать': { ru: 'Редактировать', kk: 'Өңдеу', en: 'Edit', mn: 'Засах' },
  'Создать': { ru: 'Создать', kk: 'Жасау', en: 'Create', mn: 'Үүсгэх' },
  'Создание...': { ru: 'Создание...', kk: 'Жасалуда...', en: 'Creating...', mn: 'Үүсгэж байна...' },
  'Обновить': { ru: 'Обновить', kk: 'Жаңарту', en: 'Refresh', mn: 'Шинэчлэх' },
  'Скачать': { ru: 'Скачать', kk: 'Жүктеу', en: 'Download', mn: 'Татах' },
  'Экспорт': { ru: 'Экспорт', kk: 'Экспорт', en: 'Export', mn: 'Экспорт' },
  'Фильтры': { ru: 'Фильтры', kk: 'Сүзгілер', en: 'Filters', mn: 'Шүүлтүүр' },
  'Открыть': { ru: 'Открыть', kk: 'Ашу', en: 'Open', mn: 'Нээх' },
  'Закрыть': { ru: 'Закрыть', kk: 'Жабу', en: 'Close', mn: 'Хаах' },
  'Назад': { ru: 'Назад', kk: 'Артқа', en: 'Back', mn: 'Буцах' },
  'Копировать': { ru: 'Копировать', kk: 'Көшіру', en: 'Copy', mn: 'Хуулах' },
  'Скопировать': { ru: 'Скопировать', kk: 'Көшіру', en: 'Copy', mn: 'Хуулах' },
  'Скопировать доступы': { ru: 'Скопировать доступы', kk: 'Кіру деректерін көшіру', en: 'Copy credentials', mn: 'Нэвтрэх мэдээлэл хуулах' },
  'Доступы': { ru: 'Доступы', kk: 'Кіру деректері', en: 'Credentials', mn: 'Нэвтрэх мэдээлэл' },
  'Логин': { ru: 'Логин', kk: 'Логин', en: 'Login', mn: 'Нэвтрэх нэр' },
  'Email': { ru: 'Email', kk: 'Email', en: 'Email', mn: 'Email' },
  'Телефон': { ru: 'Телефон', kk: 'Телефон', en: 'Phone', mn: 'Утас' },
  'Пароль': { ru: 'Пароль', kk: 'Құпиясөз', en: 'Password', mn: 'Нууц үг' },
  'Повторите пароль': { ru: 'Повторите пароль', kk: 'Құпиясөзді қайталаңыз', en: 'Repeat password', mn: 'Нууц үгээ давтана уу' },
  'Новый пароль': { ru: 'Новый пароль', kk: 'Жаңа құпиясөз', en: 'New password', mn: 'Шинэ нууц үг' },
  'Сгенерировать пароль': { ru: 'Сгенерировать пароль', kk: 'Құпиясөз жасау', en: 'Generate password', mn: 'Нууц үг үүсгэх' },
  'Имя': { ru: 'Имя', kk: 'Аты', en: 'Name', mn: 'Нэр' },
  'Роль': { ru: 'Роль', kk: 'Рөл', en: 'Role', mn: 'Үүрэг' },
  'Ветка': { ru: 'Ветка', kk: 'Тармақ', en: 'Branch', mn: 'Мөчир' },
  'Спонсор': { ru: 'Спонсор', kk: 'Демеуші', en: 'Sponsor', mn: 'Ивээн тэтгэгч' },
  'Категория': { ru: 'Категория', kk: 'Санат', en: 'Category', mn: 'Ангилал' },
  'Тема': { ru: 'Тема', kk: 'Тақырып', en: 'Subject', mn: 'Сэдэв' },
  'Сообщение': { ru: 'Сообщение', kk: 'Хабарлама', en: 'Message', mn: 'Зурвас' },
  'Сумма': { ru: 'Сумма', kk: 'Сома', en: 'Amount', mn: 'Дүн' },
  'Способ вывода': { ru: 'Способ вывода', kk: 'Шығару тәсілі', en: 'Withdrawal method', mn: 'Татах арга' },
  'Описание': { ru: 'Описание', kk: 'Сипаттама', en: 'Description', mn: 'Тайлбар' },
  'Название': { ru: 'Название', kk: 'Атауы', en: 'Name', mn: 'Нэр' },
  'Цена': { ru: 'Цена', kk: 'Баға', en: 'Price', mn: 'Үнэ' },
  'Остаток': { ru: 'Остаток', kk: 'Қалдық', en: 'Stock', mn: 'Үлдэгдэл' },
  'Действия': { ru: 'Действия', kk: 'Әрекеттер', en: 'Actions', mn: 'Үйлдэл' },
  'Дата': { ru: 'Дата', kk: 'Күні', en: 'Date', mn: 'Огноо' },
  'Тип': { ru: 'Тип', kk: 'Түрі', en: 'Type', mn: 'Төрөл' },
  'Комментарий': { ru: 'Комментарий', kk: 'Пікір', en: 'Comment', mn: 'Тайлбар' },
  'Нет данных': { ru: 'Нет данных', kk: 'Деректер жоқ', en: 'No data', mn: 'Мэдээлэл алга' },
  'Ошибка': { ru: 'Ошибка', kk: 'Қате', en: 'Error', mn: 'Алдаа' },
  'Доступ запрещён': { ru: 'Доступ запрещён', kk: 'Қолжетімділік жоқ', en: 'Access denied', mn: 'Хандалт хориглогдсон' },
  'Аккаунт заблокирован': { ru: 'Аккаунт заблокирован', kk: 'Аккаунт бұғатталған', en: 'Account is blocked', mn: 'Аккаунт хаагдсан' },
  'Вывод средств временно недоступен': { ru: 'Вывод средств временно недоступен', kk: 'Қаражат шығару уақытша қолжетімсіз', en: 'Withdrawals are temporarily unavailable', mn: 'Татан авалт түр хугацаанд боломжгүй' },
  'Некорректная реферальная ссылка': { ru: 'Некорректная реферальная ссылка', kk: 'Рефералдық сілтеме қате', en: 'Invalid referral link', mn: 'Буруу лавлагааны холбоос' },
  'Активен': { ru: 'Активен', kk: 'Белсенді', en: 'Active', mn: 'Идэвхтэй' },
  'Неактивен': { ru: 'Неактивен', kk: 'Белсенді емес', en: 'Inactive', mn: 'Идэвхгүй' },
  'Заблокирован': { ru: 'Заблокирован', kk: 'Бұғатталған', en: 'Blocked', mn: 'Хаагдсан' },
  'Заблокировать': { ru: 'Заблокировать', kk: 'Бұғаттау', en: 'Block', mn: 'Хаах' },
  'Разблокировать': { ru: 'Разблокировать', kk: 'Бұғаттан шығару', en: 'Unblock', mn: 'Нээх' },
  'Изменить пароль': { ru: 'Изменить пароль', kk: 'Құпиясөзді өзгерту', en: 'Change password', mn: 'Нууц үг солих' },
  'Изменить пакет': { ru: 'Изменить пакет', kk: 'Пакетті өзгерту', en: 'Change package', mn: 'Багц солих' },
  'Изменить статус': { ru: 'Изменить статус', kk: 'Статусты өзгерту', en: 'Change status', mn: 'Статус солих' },
  'Изменить': { ru: 'Изменить', kk: 'Өзгерту', en: 'Change', mn: 'Солих' },
  'Сохранить заметку': { ru: 'Сохранить заметку', kk: 'Ескертпені сақтау', en: 'Save note', mn: 'Тэмдэглэл хадгалах' },
  'Заметка администратора': { ru: 'Заметка администратора', kk: 'Әкімші ескертпесі', en: 'Admin note', mn: 'Админы тэмдэглэл' },
  'Основная информация': { ru: 'Основная информация', kk: 'Негізгі ақпарат', en: 'Main information', mn: 'Үндсэн мэдээлэл' },
  'Последние транзакции': { ru: 'Последние транзакции', kk: 'Соңғы транзакциялар', en: 'Latest transactions', mn: 'Сүүлийн гүйлгээ' },
  'Смотреть все': { ru: 'Смотреть все', kk: 'Барлығын көру', en: 'View all', mn: 'Бүгдийг харах' },
  'Все': { ru: 'Все', kk: 'Барлығы', en: 'All', mn: 'Бүгд' },
  'Левая ветка': { ru: 'Левая ветка', kk: 'Сол тармақ', en: 'Left branch', mn: 'Зүүн мөчир' },
  'Правая ветка': { ru: 'Правая ветка', kk: 'Оң тармақ', en: 'Right branch', mn: 'Баруун мөчир' },
  'Лично пригласил': { ru: 'Лично пригласил', kk: 'Жеке шақырды', en: 'Personally invited', mn: 'Өөрөө урьсан' },
  'Всего в структуре': { ru: 'Всего в структуре', kk: 'Құрылымда барлығы', en: 'Total in structure', mn: 'Бүтцэд нийт' },
  'Доступно к выводу': { ru: 'Доступно к выводу', kk: 'Шығаруға қолжетімді', en: 'Available for withdrawal', mn: 'Татах боломжтой' },
  'Быстрые действия': { ru: 'Быстрые действия', kk: 'Жылдам әрекеттер', en: 'Quick actions', mn: 'Шуурхай үйлдэл' },
  'Вывод': { ru: 'Вывод', kk: 'Шығару', en: 'Withdrawal', mn: 'Татах' },
  'Отправить заявку': { ru: 'Отправить заявку', kk: 'Өтінім жіберу', en: 'Submit request', mn: 'Хүсэлт илгээх' },
  'Заявка на вывод': { ru: 'Заявка на вывод', kk: 'Шығаруға өтінім', en: 'Withdrawal request', mn: 'Татан авалтын хүсэлт' },
  'История выводов': { ru: 'История выводов', kk: 'Шығару тарихы', en: 'Withdrawal history', mn: 'Татан авалтын түүх' },
  'Карта партнера': { ru: 'Карта партнера', kk: 'Серіктес картасы', en: 'Partner card', mn: 'Түншийн карт' },
  'Счет ИП': { ru: 'Счёт ИП', kk: 'ЖК шоты', en: 'Business account', mn: 'Бизнес данс' },
  'Счёт ИП': { ru: 'Счёт ИП', kk: 'ЖК шоты', en: 'Business account', mn: 'Бизнес данс' },
  'Банковская карта (KZT)': { ru: 'Банковская карта (KZT)', kk: 'Банк картасы (KZT)', en: 'Bank card (KZT)', mn: 'Банкны карт (KZT)' },
  'Доступные способы вывода': { ru: 'Доступные способы вывода', kk: 'Қолжетімді шығару тәсілдері', en: 'Available withdrawal methods', mn: 'Боломжтой татах аргууд' },
  'Минимальная сумма вывода': { ru: 'Минимальная сумма вывода', kk: 'Ең төменгі шығару сомасы', en: 'Minimum withdrawal amount', mn: 'Татах доод дүн' },
  'Название компании': { ru: 'Название компании', kk: 'Компания атауы', en: 'Company name', mn: 'Компанийн нэр' }
};

const sourceMap = buildSourceMap();
const originalText = new WeakMap<Text, string>();

export function RuntimeTextLocalizer() {
  const { i18n } = useTranslation();
  const language = normalizeLanguage(i18n.resolvedLanguage || i18n.language);

  useEffect(() => {
    const root = document.body;

    applyTranslations(root, language);

    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node instanceof HTMLElement || node instanceof Text) {
            applyTranslations(node, language);
          }
        });

        if (mutation.type === 'characterData' && mutation.target instanceof Text) {
          translateTextNode(mutation.target, language);
        }
      });
    });

    observer.observe(root, {
      childList: true,
      subtree: true,
      characterData: true,
    });

    return () => observer.disconnect();
  }, [language]);

  return null;
}

function applyTranslations(root: HTMLElement | Text, language: SupportedLanguage) {
  if (root instanceof Text) {
    translateTextNode(root, language);
    return;
  }

  translateAttributes(root, language);

  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, {
    acceptNode(node) {
      if (node instanceof HTMLElement && shouldSkipElement(node)) {
        return NodeFilter.FILTER_REJECT;
      }

      return NodeFilter.FILTER_ACCEPT;
    },
  });

  let node = walker.nextNode();

  while (node) {
    if (node instanceof Text) {
      translateTextNode(node, language);
    } else if (node instanceof HTMLElement) {
      translateAttributes(node, language);
    }

    node = walker.nextNode();
  }
}

function translateTextNode(node: Text, language: SupportedLanguage) {
  const current = node.nodeValue || '';
  const source = originalText.get(node) || current;
  const translated = translateLiteral(source, language);

  originalText.set(node, source);

  if (current !== translated) {
    node.nodeValue = translated;
  }
}

function translateAttributes(element: HTMLElement, language: SupportedLanguage) {
  ['placeholder', 'title', 'aria-label'].forEach((attribute) => {
    const value = element.getAttribute(attribute);

    if (!value) {
      return;
    }

    const originalAttribute = `data-i18n-original-${attribute}`;
    const source = element.getAttribute(originalAttribute) || value;
    const translated = translateLiteral(source, language);

    element.setAttribute(originalAttribute, source);

    if (value !== translated) {
      element.setAttribute(attribute, translated);
    }
  });
}

function translateLiteral(value: string, language: SupportedLanguage) {
  const leading = value.match(/^\s*/)?.[0] || '';
  const trailing = value.match(/\s*$/)?.[0] || '';
  const trimmed = value.trim().replace(/\s+/g, ' ');

  if (!trimmed) {
    return value;
  }

  const exact = sourceMap[trimmed]?.[language];

  if (exact) {
    return `${leading}${exact}${trailing}`;
  }

  let replaced = trimmed;

  Object.keys(sourceMap)
    .filter((source) => source.length > 2 && replaced.includes(source) && sourceMap[source]?.[language])
    .sort((a, b) => b.length - a.length)
    .forEach((source) => {
      replaced = replaced.split(source).join(sourceMap[source]?.[language] || source);
    });

  return replaced !== trimmed ? `${leading}${replaced}${trailing}` : value;
}

function buildSourceMap(): Record<string, TranslationEntry> {
  const map: Record<string, TranslationEntry> = { ...manualTranslations };
  const ruLeaves = flattenLeaves(localeResources.ru);

  (Object.keys(ruLeaves) as string[]).forEach((key) => {
    const source = ruLeaves[key];

    if (!source || map[source]) {
      return;
    }

    map[source] = {
      ru: source,
      kk: flattenLeaves(localeResources.kk)[key],
      en: flattenLeaves(localeResources.en)[key],
      mn: flattenLeaves(localeResources.mn)[key],
    };
  });

  return map;
}

function flattenLeaves(value: unknown, prefix = '', result: Record<string, string> = {}) {
  if (typeof value === 'string') {
    result[prefix] = value;
    return result;
  }

  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return result;
  }

  Object.entries(value as Record<string, unknown>).forEach(([key, nestedValue]) => {
    flattenLeaves(nestedValue, prefix ? `${prefix}.${key}` : key, result);
  });

  return result;
}

function shouldSkipElement(element: HTMLElement) {
  return ['SCRIPT', 'STYLE', 'TEXTAREA', 'CODE', 'PRE'].includes(element.tagName);
}

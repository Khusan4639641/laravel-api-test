# Safi Life: аудит и миграция мультиязычности

Дата аудита: 2026-08-03.

## 1. Найденная причина

На frontend одновременно работали три механизма:

1. Уже установленный `i18next` / `react-i18next` с JSON-словарями.
2. GTranslate website widget, который динамически загружался с `https://cdn.gtranslate.net/widgets/latest/dropdown.js` и использовал `googtrans`, `safi_lang` и `safi_language`.
3. Собственный `RuntimeTextLocalizer`, который после рендера React обходил весь документ через `MutationObserver` и `TreeWalker`, затем изменял текстовые DOM-узлы и атрибуты.

GTranslate и локальный DOM-обход переводили уже отрендеренный HTML без знания семантики данных. Поэтому «кабинет» интерпретировался как мебель, персональные данные попадали под замену, а частично переведённый React DOM повторно обрабатывался другим механизмом. Дополнительной причиной были несовместимые коды `kz`/`kg` в старых сохранённых значениях и `kk`/`ky` в части нового UI.

## 2. Где автоматический перевод отключён

- Удалён `resources/js/safi/lib/gtranslate.ts` и его runtime-подключение из router.
- Language selector больше не вызывает GTranslate и меняет только locale `i18next`.
- Из `App.tsx` удалён `RuntimeTextLocalizer`.
- Из `runtimeTranslations.ts` удалены React-effect, `MutationObserver`, `TreeWalker` и запись в DOM-узлы. Файл сохранён только как контролируемый словарь для явно переданной UI-строки.
- Удалены CSS-хаки для Google/GTranslate toolbar и widget DOM.
- `<body>` помечен `translate="no"`, чтобы браузерный перевод страницы не конкурировал с i18next.
- При первом запуске удаляются только legacy localStorage-ключи `safi_lang`, `safi_language` и cookie `googtrans`. Auth, session и CSRF cookies не затрагиваются.

В исходниках и production bundle отсутствуют Google Translate/GTranslate scripts, iframe, `goog-te` DOM и глобальный DOM-observer.

## 3. Единая locale-модель

Внутренние locale:

| Label в UI | Locale |
| --- | --- |
| RU | `ru` |
| KZ | `kk` |
| KG | `ky` |
| EN | `en` |
| MN | `mn` |

Frontend хранит canonical locale в `localStorage.safi_locale` и cookie `safi_locale`, восстанавливает его при загрузке и передаёт `Accept-Language` во всех API-запросах. Значение нормализуется только к `ru`, `kk`, `ky`, `en`, `mn`; неизвестное значение даёт `ru`. Старые входные `kz` и `kg` читаются только как migration compatibility и немедленно нормализуются в `kk`/`ky`.

Laravel middleware `SetLocaleFromRequest` применён глобально. `config/app.php` использует `ru` как locale и fallback. Для существующих DB JSON разрешено чтение старых полей `kz`/`kg`, но canonical `kk`/`ky` имеет приоритет. Запись кодов и пользовательские данные в базе не изменялись.

## 4. Словари

Основные i18next-словари:

- `resources/js/safi/locales/ru.json`
- `resources/js/safi/locales/kk.json`
- `resources/js/safi/locales/ky.json`
- `resources/js/safi/locales/en.json`
- `resources/js/safi/locales/mn.json`

Все пять JSON-файлов валидны и имеют одинаковую структуру ключей. Отдельные legacy admin/dashboard literals находятся в `resources/js/safi/i18n/runtimeTranslations.ts` и `adminText.ts`; это контролируемые словари, использующие тот же locale i18next, а не вторая runtime-система и не DOM-переводчик.

Backend-файлы:

- `lang/{ru,kk,ky,en,mn}/api.php`
- `lang/{ru,kk,ky,en,mn}/auth.php`
- `lang/{ru,kk,ky,en,mn}/validation.php`

Missing translation fallback — русский. Имя ключа пользователю не выводится. В development missing key записывается в console; production-страница не запускает машинный перевод.

## 5. Исправленные ручные переводы

Казахский словарь использует кириллицу. Зафиксированы, в частности:

- `Басты бет`
- `Компания туралы`
- `Өнімдер`
- `Мүмкіндіктер`
- `Кіру`
- `Жеке кабинет`
- `Өз әлеуетіңізді Safi арқылы ашыңыз`
- `Неліктен Safi Life-ты таңдайды?`
- `Бастапқы пакеттер`
- `Өзіңізге сәйкес пакетті таңдап, Safi Life-пен бірге табыс табуды бастаңыз.`
- admin actions: `Деректерді өңдеу`, `Құпиясөзді өзгерту`, `Балансты өзгерту`, `Бұғаттау`, `Бинарды есептеу`, `Серіктесті жою`.

Запрещённые варианты `Жаман ставка`, `Шкаф`, `Nege tandaydy`, `èz alueutizdi`, `Бастапқы пакеттеушісі` отсутствуют и в исходных словарях, и в production bundle.

## 6. No-translate данные

Создан общий `NoTranslate` component (`translate="no"`, class `notranslate`). Он применяется в public, dashboard и admin представлениях для:

- имени, фамилии, отчества и полного ФИО;
- login/username, email, телефона;
- user, order, transaction, ticket и payment ID;
- referral code и referral URL;
- адреса, комментария и support-сообщения пользователя;
- имени файла и платёжных реквизитов;
- package code и SKU;
- брендов и технических обозначений: Safi Life, Safi, TipTop Pay, Visa, Mastercard, START, VIP, ELITE, PV, KZT.

Input values не передаются в `t()`/UI-translator. Поля редактирования пользователя, профиля, регистрации, checkout, search и password дополнительно имеют `translate="no"`. Пример в тестах использует полностью вымышленные данные.

## 7. Проверенные страницы

HTTP smoke-check выполнен для:

- `/`, `/about`, `/business`, `/products`, `/marketing-plan`, `/news`, `/faq`, `/contacts`, `/login`, `/register`, `/cart`;
- `/legal`, `/legal/offer`, `/legal/privacy`, `/legal/delivery`, `/legal/refund`, `/legal/requisites`;
- `/dashboard/profile`, `/dashboard/structure`, `/dashboard/transactions`, `/dashboard/support`;
- `/admin/partners`, `/admin/structure`, `/admin/transactions`, `/admin/bonuses`, `/admin/support`.

Основные маршруты возвращают HTTP 200; `/register` штатно возвращает redirect на login по текущей политике приложения. Добавлен совместимый public route `/marketing-plan` без изменения существующего `/marketing`.

Код и словари проверены для RU, KZ/`kk`, KG/`ky`, EN и MN. Headless Chrome подтвердил сохранение каждого canonical locale, отсутствие translator iframe, наличие `START`/`VIP`/`ELITE` и `Safi Life` во всех пяти языках. На homepage проверены viewport 320, 360, 375, 390, 430, 768 и 1440 px — horizontal overflow не обнаружен. Responsive-компоненты используют wrapping, `overflow-wrap`, responsive modal/card/table варианты и не уменьшают текст до фиксированного нечитаемого размера. Финальная визуальная проверка реальным пользователем на целевых устройствах всё равно рекомендуется как часть release QA.

## 8. Русский fallback и строки для утверждения

Машинный перевод намеренно не используется. Русский fallback оставлен для:

- длинных юридических текстов offer/privacy/delivery/refund/payment до утверждения юристом;
- отдельных длинных маркетинговых фраз KG и MN, для которых нет утверждённой редакции;
- части legacy admin literals, прежде всего KY, пока редактор не утвердит терминологию.

Заимствованные технические слова (`бизнес`, `маркетинг`, `бинар`, `статус`, `профиль`, `каталог`) могут совпадать по написанию в нескольких кириллических языках и не являются результатом DOM-перевода. Следующий контент-аудит должен быть редакторским, а не автоматическим.

## 9. Изменённые области

- Locale/i18n: `resources/js/safi/i18n.ts`, `lib/language.ts`, locale JSON, `useUiText.ts`, `runtimeTranslations.ts`, `adminText.ts`, `systemLabels.ts`.
- Layout/runtime: `App.tsx`, `router/routes.tsx`, `LanguageSwitcher.tsx`, `NoTranslate.tsx`, public Blade shell и frontend CSS/font fallbacks.
- Public UI: homepage, about, business, products, marketing plan, news, FAQ, contacts, login/register, cart/payment and legal pages.
- Dashboard UI: layout/sidebar, overview, profile, structure, transactions, bonuses, orders, package status and support.
- Admin UI: layout/sidebar, partners/detail/edit modal, structure, transactions, bonuses, orders, withdrawals, packages, profile, support and password requests.
- Backend: locale middleware/support/config, localized API messages and five Laravel language directories.
- Tests: locale mapping/fallback, language middleware, FAQ/menu/system label compatibility, personal-data invariance, package codes and exact Kazakh strings.

Бизнес-расчёты, PV, бинар, балансы, транзакционные правила, заказы, TipTopPay flow, referral/tree logic, DB schema и пользовательские записи не изменялись.

## 10. Проверки

- `php artisan optimize:clear` — успешно.
- `npm run build` — успешно, 1865 modules; только информационное Vite warning о размере main chunk.
- `php artisan test --filter=Locale` — 5 tests, 62 assertions, passed.
- `php artisan test --filter=Translation` — 7 tests, 68 assertions, passed.
- `php artisan test --filter=Language` — 10 tests, 59 assertions, passed.
- Дополнительно: SystemLabelLocalization — 4/19; MenuApi — 5/72; FaqLanguage — 2/16; MlmNotificationPersistence — 7/145, всё passed.
- PHP syntax check для `app`, `config`, `lang`, `tests/Feature` — passed.
- Frontend test runner отсутствует: в `package.json` нет `test` script и зависимостей Jest/Vitest/Playwright/Cypress, поэтому `npm test -- --runInBand` не запускался.
- Static bundle audit — внешних translator hooks и запрещённых строк не найдено.
- Headless Chrome — RU/KK/KY/EN/MN persistence, public route rendering, extended Cyrillic glyph availability, package/brand invariance и responsive overflow checks passed; translator iframe count = 0.

Git-команды, migrations, seeders и destructive database commands во время работы не выполнялись.

## 11. Follow-up: mobile navigation, 2026-08-04

Public header переведён на единый namespace для desktop, overflow и mobile drawer:

- `navigation.home`
- `navigation.about`
- `navigation.products`
- `navigation.opportunities`
- `navigation.marketingPlan`
- `navigation.howToStart`
- `navigation.faq`
- `navigation.contacts`
- `auth.login`
- `auth.dashboard`

Все варианты navigation строятся из одного `PUBLIC_NAVIGATION_ITEMS` в `Header.tsx`. В `kk` добавлены утверждённые значения в обычном регистре; uppercase выполняется только CSS.

Локальная проверка в новом профиле Headless Chrome на 320, 375, 390 и 430 px подтвердила правильный порядок всех пунктов, правильные auth-кнопки, `safi_locale=kk` после reload, отсутствие overflow, translator iframe и translator network requests. Desktop header использует тот же набор и значения.

В исходниках и локальном fresh build старых строк нет. Service worker, Workbox и CacheStorage registration в проекте не найдены.

Отдельно проверен действующий production `https://safilife.kz/`: 2026-08-04 сервер продолжал отдавать старый `/build/assets/main--8Lqrvr1.js`. Этот asset содержит `cdn.gtranslate.net` и при загрузке страницы создаёт запросы к Google Translate. Поэтому production может продолжать показывать старый машинный перевод до развёртывания нового `public/build` и обновления server-side manifest. Production deployment в рамках локальной задачи не выполнялся.

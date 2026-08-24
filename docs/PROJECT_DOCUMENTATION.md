# Safi Life — техническая документация проекта

> Снимок текущей реализации: 24.08.2026. Источник истины — файлы репозитория `/var/www/project7` и фактический вывод `php artisan route:list --json`. Документ не описывает желаемое ТЗ как уже реализованное.

## Назначение и границы системы

Safi Life — монолитное Laravel-приложение с JSON API и React SPA. Оно объединяет публичный каталог, регистрацию по реферальным ссылкам, личный кабинет партнёра, бинарную MLM-структуру, пакеты и PV, бонусы, кошельки, заказы, TipTopPay, вывод средств, поддержку и back office.

Технологический стек:

- backend: PHP `^8.3`, Laravel `^13.0`, Laravel Sanctum `^4.3`;
- frontend: React `19.2.6`, TypeScript, React Router `7.15`, Vite `8`, Tailwind CSS `4`;
- штатные драйверы из `.env.example`: SQLite, database session/cache/queue, log mail;
- расчёт денег/PV: строки decimal и функции BCMath в финансовых сервисах;
- базовая business timezone: `Asia/Tashkent` (`config/app.php`, binary scheduler и period resolver);
- API локализуется по `Accept-Language`; фактические языки frontend: `ru`, `kk`, `ky`, `en`, `mn`.

## Как читать документацию

| Документ | Содержание |
|---|---|
| [ARCHITECTURE.md](./ARCHITECTURE.md) | архитектура, lifecycle, каталоги, auth/RBAC, безопасность, интеграции |
| [CONTROLLERS.md](./CONTROLLERS.md) | все 54 controller-класса и реальные execution flow методов |
| [SERVICES.md](./SERVICES.md) | все 35 сервисов, зависимости, таблицы, транзакции и side effects |
| [API.md](./API.md) | все 172 route entries, в том числе 164 API route entries |
| [DATABASE.md](./DATABASE.md) | 50 migrations, 38 создаваемых приложением таблиц и 29 Eloquent models |
| [MLM.md](./MLM.md) | registration, sponsor, tree, packages, PV, referral, binary, status и X2 |
| [WALLET.md](./WALLET.md) | wallets, ledger, transfers, withdrawal и финансовый audit trail |
| [ADMIN.md](./ADMIN.md) | роли, admin/support endpoints и соответствующие React-страницы |
| [FRONTEND.md](./FRONTEND.md) | React/Vite, 47 pages, 22 components, API client и page-to-API map |
| [COMMANDS.md](./COMMANDS.md) | 9 custom commands, scheduler, повторные запуски и уровень риска |
| [DEPLOYMENT.md](./DEPLOYMENT.md) | ENV, сборка и то, что реально найдено/не найдено для production |
| [TESTING.md](./TESTING.md) | 129 test classes, 865 test methods, factory и seeders |

## Главные бизнес-сущности

- `User`/`UserProfile`: учётная запись, sponsor, роль, пакет, статус и кэшированные PV.
- `BinaryNode`: место пользователя в бинарном дереве, `L`/`R`, materialized path.
- `Package`: START/VIP/ELITE, цена, activity/turnover PV, проценты бонусов.
- `PvTransaction`: поток turnover PV от buyer к каждому upline и признак `is_bonusable`.
- `BinaryBonusRun`/`BinaryBonusCalculation`: периодический расчёт и его audit snapshot.
- `BonusTransaction`: referral, binary, cashback, status и X2 ledger.
- `Wallet`/`WalletTransaction`: денежный баланс и журнал всех влияющих/невлияющих операций.
- `Product`, `Order`, `OrderItem`, `Payment`: каталог, checkout и TipTopPay.
- `WithdrawalRequest`: hold и решение бухгалтера/super admin.
- `StatusBonusDefinition`/`UserStatusBonus`, `X2BonusDefinition`/`UserX2Bonus`: квалификации и защита от повторной выдачи.
- `SupportTicket`, messages и attachments: партнёрская поддержка.
- `AdminActionLog`, `TransactionAdminAudit`: журнал опасных административных действий.

## Реальный request lifecycle

```text
Browser
  ↓
React/Vite SPA (resources/js/safi)
  ↓ fetch /api/* + Bearer Sanctum token + Accept-Language
Laravel route (routes/api.php)
  ↓
SetLocaleFromRequest (глобальный middleware)
  ↓
auth:sanctum → account_active → role_permission/own_resource (где назначены)
  ↓
Controller
  ↓
FormRequest ИЛИ inline Request::validate()
  ↓
Service (для write/business flow) ИЛИ Eloquent query (для read/CRUD flow)
  ↓
DB::transaction + lockForUpdate (финансовые операции)
  ↓
Eloquent models / tables
  ↓
WalletTransaction / BonusTransaction / PV audit / Notification
  ↓
JsonResource / JSON response
```

Отдельных Repository/DTO/Action слоёв нет. Событий Laravel и listeners нет: сервисы вызывают следующие сервисы и notifications напрямую. Custom Jobs нет. Поэтому ожидаемая универсальная цепочка `Event → Job → Listener` в текущем коде обычно отсутствует.

## Архитектурный контур

```text
public web pages ────────────────┐
dashboard pages ─ Sanctum ──────┼─→ controllers → services → Eloquent → DB
admin/support ─ Sanctum + RBAC ─┘                         │
                                                         ├─→ wallet/PV/bonus audit rows
                                                         ├─→ mail/database notifications
                                                         └─→ TipTopPay webhook state machine

Scheduler (1-е и 15-е, 03:00 Asia/Tashkent)
  └─→ safi:binary-recalculate-all --scheduled
       └─→ ScheduledBinaryBonusService → BonusService → wallets + binary run ledger
```

Подробнее: [общая архитектура](./ARCHITECTURE.md), [MLM](./MLM.md), [wallet](./WALLET.md).

## Критический MLM flow

```text
Регистрация
  ↓ resolve sponsor по login/id
PartnerRegistrationService
  ├─→ User + UserProfile
  ├─→ main/bonus/deposit wallets
  └─→ BinaryTreeService: выбранная L/R side-chain

Пакет / товарный оборот
  ↓
PackageService или CheckoutOrderService
  ├─→ PvService::addUserPv()              (personal/activity PV)
  ├─→ PvService::accrueTurnoverToUplines()
  │    └─→ PvTransaction на каждого upline + left/right/remaining PV
  ├─→ BonusService::accrueReferralBonus()   (package/admin assignment only;
  │                                          regular product order не вызывает)
  └─→ StatusService::recalculate()
       ├─→ StatusBonusService
       └─→ X2BonusService для sponsor

Полумесячный binary
  ↓
bonusable PV по обеим веткам − PV прошлых runs
  ↓ min(left, right)
matched PV × 500 KZT × package.binary_percent
  ↓
90% main wallet + 10% deposit wallet
```

Подробные правила, исключения и пример расчёта: [MLM / Bonus System](./MLM.md).

## Пакеты в текущем PackageSeeder

| Code | Price, KZT | `pv` / activity PV | turnover PV | referral field | binary |
|---|---:|---:|---:|---:|---:|
| START | 60 000 | 100 | 100 | 10% | 7% |
| VIP | 180 000 | 300 | 300 | 10% | 8% |
| ELITE | 300 000 | 500 | 200 | 10% | 10% |

`BUSINESS`, если остался в БД, seeder переводит в inactive/non-upgradeable. Публичные пакеты — только START/VIP/ELITE. Referral service фактически применяет константу 10%, а не читает `packages.referral_percent`; это различие важно при изменении каталога.

## Wallet и деньги

У пользователя создаются три KZT-кошелька: `main`, `bonus`, `deposit`. Основные денежные бонусы поступают в `main`; 10% binary поступают в `deposit`. Использование `bonus` как целевого кошелька в текущих сервисах не найдено.

Каждый `credit`/`debit`:

1. работает внутри транзакции вызывающего сервиса;
2. блокирует/обновляет `wallets.balance`;
3. пишет `wallet_transactions` с `balance_before`, `balance_after`, direction, source morph и metadata;
4. `affects_balance=false` применяется для audit-only операций (package assignment/payment approval и подобные).

Полный ledger и withdrawal state machine: [WALLET.md](./WALLET.md).

## Authentication и authorization

- Login принимает `login` или email; при `login` также ищется точное совпадение `user_profiles.phone`.
- Успешные login/register создают Sanctum personal access token с именем `api` без заданного expiration.
- React хранит token в `localStorage` под ключом `safi_token` и отправляет `Authorization: Bearer`.
- Protected API использует `auth:sanctum` и `account_active`.
- Back office использует `role_permission:<permission>` из `config/role_permissions.php`.
- Отдельных Policies нет; ownership заказа проверяется controller, support — middleware плюс controller/service.

| Role | Основные зоны | Ограничения |
|---|---|---|
| `user` | `/dashboard/*`, orders, withdrawal, transfers | frontend пускает только в dashboard; API dashboard не имеет отдельного `dashboard.access` middleware |
| `support` | support tickets, forgot-password | нет financial/catalog permissions |
| `admin` | overview, partners, structure, read transactions/withdrawals/orders, manage orders/bonuses | catalog/settings/reports/withdrawal decision/identity/balance/create partner ограничены |
| `accountant` | overview, transactions, withdrawals, orders, reports | approve/reject withdrawal разрешён; catalog/partners/bonuses недоступны |
| `super_admin` | весь back office | destructive endpoints дополнительно проверяют super admin в FormRequest/service/controller |

Подробнее: [security и RBAC](./ARCHITECTURE.md#authentication-и-authorization), [admin](./ADMIN.md).

## API и frontend

- `routes/web.php` отдаёт одну Blade-страницу `resources/views/app.blade.php`; React Router обслуживает public/dashboard/admin/support routes.
- `/register`, `/registration`, `/sign-up` на server и в SPA перенаправляются на `/login`; рабочая referral page — `/register-ref-branch?ref=...&branch=left|right`.
- Имеется 164 API route entry: 24 public и 140 Sanctum-protected; 81 начинаются с `/api/admin`, 34 — с `/api/dashboard`.
- Swagger/OpenAPI/`l5-swagger`/`scribe` не найден.

Полные таблицы: [API.md](./API.md), [FRONTEND.md](./FRONTEND.md), [CONTROLLERS.md](./CONTROLLERS.md).

## Database, cache, queues и filesystem

- 50 migration files создают 38 application/framework tables; Laravel дополнительно использует служебную `migrations`.
- По `.env.example`: database session, database cache и database queue; таблицы для всех трёх присутствуют.
- Business jobs не найдены; notifications не реализуют `ShouldQueue`, поэтому выполняются синхронно, несмотря на trait `Queueable`.
- Cache применяется Laravel и для mutex/lock scheduled binary calculation; отдельного domain cache service нет.
- Avatar/news/product изображения пишутся на public disk. Support attachments пишутся на local/private disk и выдаются через авторизованный download endpoint.

Подробнее: [DATABASE.md](./DATABASE.md), [COMMANDS.md](./COMMANDS.md).

## Events, notifications и внешние интеграции

Laravel Event/Listener классы не найдены. Реальные trigger chains:

| Trigger | Прямой вызов | Channels | Recipient |
|---|---|---|---|
| public registration | `UserRegisteredNotification` | mail | новый пользователь |
| referral/binary/cashback/status cash bonus | `BonusAccruedNotification` | mail + database | получатель бонуса |
| новый статус | `StatusAchievedNotification` | mail + database | пользователь |
| X2 qualification | `X2BonusAwardedNotification` | mail + database | пользователь |
| withdrawal request | `WithdrawalRequestedNotification` | mail | заявитель |
| partner transfer | `PartnerTransferCompletedNotification` | database | sender и recipient |

Внешняя платёжная интеграция — TipTopPay: browser widget `https://widget.tiptoppay.kz/bundles/widget.js`, payment intents и public check/pay/confirm/fail/refund/cancel callbacks. Других runtime payment API, SMS, Telegram или push integrations не найдено. Google Fonts и несколько удалённых image URL используются только frontend-контентом.

## Если нужно изменить X — где искать

| Что изменить | Основные реальные файлы |
|---|---|
| регистрация/login | `routes/api.php`; `app/Http/Controllers/Api/AuthController.php`; `app/Http/Requests/Auth/RegisterRequest.php`; `app/Http/Requests/Auth/LoginRequest.php`; `app/Services/PartnerRegistrationService.php` |
| sponsor/referral link | `app/Http/Controllers/Api/ReferralController.php`; `app/Services/PartnerRegistrationService.php`; `app/Models/User.php`; `app/Services/BinaryTreeService.php` |
| placement / structure tree | `app/Services/BinaryTreeService.php`; `app/Services/BinaryTreeSideResolver.php`; `app/Models/BinaryNode.php`; dashboard/admin `StructureController.php` in their controller directories |
| пакет/активация/upgrade | `app/Services/PackageService.php`; `app/Services/PackagePurchaseAvailabilityService.php`; `app/Services/PackageAutoUpgradeFromPaidOrdersService.php`; `app/Http/Controllers/Api/PackageActivationController.php`; `app/Http/Controllers/Api/PackageUpgradeController.php`; `database/seeders/PackageSeeder.php` |
| PV | `app/Services/PvService.php`; `app/Services/DashboardBranchVolumeService.php`; `app/Models/PvTransaction.php`; `app/Console/Commands/RecalculateBranchPvCommand.php` |
| referral bonus | `app/Services/BonusService.php` (`accrueReferralBonus`); `app/Services/ReferralBonusBaseResolver.php`; фактические callers `PackageService.php`, `PartnerRegistrationService.php` |
| binary bonus | `app/Services/BonusService.php`; `app/Services/ScheduledBinaryBonusService.php`; `app/Console/Commands/BinaryRecalculateAllCommand.php`; `app/Console/Commands/BinaryRecalculationRollbackBatchCommand.php` |
| status | `app/Services/StatusService.php`; `app/Services/StatusBonusService.php`; `app/Models/StatusBonusDefinition.php`; `database/seeders/StatusBonusDefinitionSeeder.php` |
| X2 | `app/Services/X2BonusService.php`; `app/Models/X2BonusDefinition.php`; `database/seeders/X2BonusDefinitionSeeder.php` |
| wallet/ledger | `app/Services/WalletService.php`; `app/Services/PartnerTransferService.php`; `app/Services/InternalWalletTransferService.php`; `app/Models/Wallet.php`; `app/Models/WalletTransaction.php` |
| withdrawal | `app/Http/Controllers/Api/WithdrawalController.php`; `app/Http/Controllers/Api/Dashboard/WithdrawalController.php`; `app/Http/Requests/Withdrawal/StoreWithdrawalRequest.php`; `app/Services/WithdrawalService.php`; `app/Http/Controllers/Api/Admin/WithdrawalController.php` |
| order/PV/stock | `app/Services/CheckoutOrderService.php`; `app/Http/Requests/Order/StoreOrderRequest.php`; `app/Http/Controllers/Api/OrderController.php`; `app/Models/Product.php`; `app/Models/Order.php`; `app/Models/OrderItem.php` |
| deposit purchase/cashback | `app/Services/DepositPurchaseService.php`; `app/Http/Controllers/Api/DepositPurchaseController.php`; `app/Services/BonusService.php` (`accrueDepositPurchaseCashback`) |
| TipTopPay | `config/tiptoppay.php`; `app/Http/Controllers/Api/Payments/TipTopPayIntentController.php`; `app/Http/Controllers/Api/Payments/TipTopPayWebhookController.php`; `app/Services/Payments/TipTopPayService.php`; `resources/js/safi/hooks/useTipTopPayWidget.ts`; `resources/js/safi/lib/tiptoppay.ts` |
| notifications | `app/Notifications/`; dispatching services above; `app/Services/TransactionNotificationTextFactory.php` |
| admin partners | `app/Http/Controllers/Api/Admin/UserController.php`; `app/Http/Controllers/Api/Admin/PartnerController.php`; `resources/js/safi/pages/admin/AdminPartners.tsx`; `resources/js/safi/pages/admin/AdminPartnerDetail.tsx` |
| admin transactions/bonuses | `app/Http/Controllers/Api/Admin/TransactionController.php`; `app/Http/Controllers/Api/Admin/BonusController.php`; `app/Services/AdminWalletTransactionService.php`; `app/Services/BonusAdminAdjustmentService.php` |
| support | dashboard/staff `SupportTicketController.php`; `app/Services/SupportTicketService.php`; `app/Services/SupportAttachmentStorage.php`; dashboard/admin `Support.tsx`/`AdminSupport.tsx` |
| permissions/menu | `config/role_permissions.php`; `app/Http/Controllers/Api/PermissionController.php`; `resources/js/safi/lib/permissions.ts`; admin/dashboard layout components |
| translations/labels | `app/Http/Middleware/SetLocaleFromRequest.php`; `app/Support/LocalizedValue.php`; `app/Support/SystemLabel.php`; `lang/`; `resources/js/safi/locales/` |

Полный class dependency index: [SERVICES.md](./SERVICES.md), controller-to-route index: [CONTROLLERS.md](./CONTROLLERS.md).

## ⚠️ Операции, изменяющие деньги

Каждая операция ниже — **HIGH RISK**:

- package activation/upgrade/manual assignment: меняет package, PV, referral/status effects и audit transactions;
- binary calculate/recalculate/scheduled run: создаёт или корректирует два wallet legs и binary ledgers;
- binary rollback/reconciliation: удаляет/восстанавливает runs, bonuses, calculations, notifications, PV и replay wallet balances;
- referral/status/X2/cashback: кредитует `main` и создаёт bonus+wallet ledgers;
- manual wallet/bonus edit/delete: меняет уже отражённые balances и audit;
- partner transfer/internal transfer: атомарный debit/credit двух кошельков;
- withdrawal request/approve/reject: переносит деньги между balance и hold или возвращает их;
- partner delete/cleanup: reverses bonuses, PV, package transactions, withdrawals/orders и закрывает wallets;
- status bonus repair: способен достроить отсутствующий денежный ledger.

Перед production-запуском любой write-команды использовать backup, dry-run если он существует, сверку ожидаемого периода/ID и последующий ledger reconciliation. Подробно: [COMMANDS.md](./COMMANDS.md#high-risk-operations).

## Potential issues, найденные чтением кода

Код не изменялся. Это не полный security audit, а конкретные наблюдения:

1. **HIGH — PV до оплаты заказа.** `CheckoutOrderService::create()` начисляет product-order PV сразу после создания обычного заказа, тогда как TipTopPay payment может остаться pending/failed/cancelled/refunded. Admin cancellation возвращает stock, но не void-ит связанные PV effects. TipTopPay cancel/refund переводит order в cancelled, однако в этом service flow не восстанавливает stock и также не void-ит PV. Referral bonus для regular product order текущий `CheckoutOrderService` не начисляет.
2. **HIGH — package refund не отменяет MLM effects.** Успешный package payment вызывает `PackageService` и создаёт package/PV/referral/status effects. Последующий TipTopPay refund/cancel для payment type `package` только меняет `payments.status`; обратной деактивации пакета и reversal PV/bonus/wallet audit в terminal handler нет.
3. **HIGH — webhook без секрета.** `TipTopPayService::validWebhookSignature()` возвращает `true`, если `TIPTOPPAY_WEBHOOK_SECRET` пуст. При таком deployment публичные payment callbacks не аутентифицируются. Fail/refund/cancel flows также не сверяют amount/currency так, как check/pay/confirm.
4. **HIGH — status recalc может начислить деньги.** `mlm:recalculate-statuses` не имеет dry-run/production guard; `StatusService::recalculate()` вызывает `StatusBonusService::syncForUser()` и X2 check, что может создать bonus/wallet rows.
5. **HIGH — cleanup пишет по умолчанию.** `safi:cleanup-deleted-user-effects` выполняет reversals, если не указан `--dry-run`; отдельного `--force` или production guard нет.
6. **HIGH — admin transaction mutation.** `AdminWalletTransactionService::applyWalletDelta()` не проверяет отрицательный итоговый баланс при редактировании ledger transaction; endpoint update доступен admin/super admin через FormRequest.
7. **MEDIUM — login/register rate limit.** Явный throttle `6,1` есть только у forgot-password endpoints; на login, register и payment callbacks отдельный throttle в routes не назначен.
8. **MEDIUM — earnings status total.** `EarningsSummaryService` читает grouped bonus key `status`, а реальные status bonuses создаются как `status_bonus`; поле summary может показывать ноль при наличии начислений.
9. **MEDIUM — package percent divergence.** referral начисление использует константу 10% `BonusService`, а `packages.referral_percent` лишь хранится/отдаётся API. Изменение поля пакета само по себе не меняет расчёт.
10. **MEDIUM — неиспользованный stub.** `ReferralService` содержит пустые методы с объявленными return types. Текущих callers не найдено; будущий вызов `accrueReferralBonus()` завершится ошибкой отсутствующего return.
11. **LOW/operational — sync mail in transactions.** Notifications не queued; mail отправляется непосредственно из business transaction. Ошибка mail transport способна прервать вызывающий flow.

## Не найдено в текущей реализации / требует проверки

**Не найдено в текущей реализации:**

- отдельные классы `PackageActivationService`, `PackageUpgradeService`, `PvAccrualService`, `BinaryBonusService`, `ReferralBonusService`, `NotificationService`; их функции распределены по реальным сервисам;
- `app/Jobs`, `app/Events`, `app/Listeners`, `app/Policies`, `app/Repositories`, `app/DTO`, `app/Enums`, `app/Mail`, `modules/`;
- Swagger/OpenAPI;
- SMS, Telegram, push или другая payment integration кроме TipTopPay;
- фактическое использование `bonus` wallet для начисления бонусов;
- model/class `ProductItem`; товарные позиции реализованы как `OrderItem`/`order_items`;
- начисление referral bonus для regular product order: `ReferralBonusBaseResolver::resolveForProductOrder()` существует, но current caller не найден;
- workflow выбора/выплаты `compensation_amount` status reward;
- refund initiation endpoint: реализованы входящие TipTopPay refund/cancel callbacks, но исходящий refund API client не найден;
- Docker/Coolify/Nginx/PHP-FPM/Supervisor/CI deployment manifests.

**Требует дополнительной проверки вне репозитория:**

- реальный production `.env`, DB engine, secrets и configured queue/cache/mail drivers;
- Nginx/PHP-FPM, cron `schedule:run`, queue worker/Supervisor и release procedure на сервере;
- фактическая регистрация callback URL в кабинете TipTopPay;
- наличие backup/monitoring/log aggregation и финансовой reconciliation процедуры;
- бизнес-решение, должен ли product PV начисляться до оплаты, и как обрабатывать refund/cancel PV;
- назначение существующего, но неиспользуемого `bonus` wallet и компенсаций поездок.

## Покрытие документации

Оценка относится к содержимому репозитория, а не к внешней production-инфраструктуре:

| Область | Coverage | Основание |
|---|---:|---|
| Backend | 100% | 54 controllers, 35 services, requests/resources/middleware/notifications/support classes |
| Frontend | 100% | 47 pages, 22 components, router, context, hook, API client, i18n/libs |
| Database | 100% | 50 migrations, 38 создаваемых tables, 29 models, factory и 15 seeders |
| MLM | 100% | registration/tree/packages/PV/referral/binary/status/X2 и команды |
| API | 100% | 172 route entries из `route:list`, включая aliases и web routes |
| Deployment | 70% | repo config/build описаны; внешняя server-конфигурация отсутствует |

## Проверенная количественная сводка

| Объект | Изучено |
|---|---:|
| controller classes | 54 (+ abstract base и pagination concern) |
| services/value-result classes | 35 |
| Eloquent models | 29 |
| custom Artisan commands | 9 (+ route closure `inspire`) |
| registered routes | 172, из них API 164 |
| FormRequest / JsonResource / middleware | 13 / 20 / 7 |
| jobs / events / listeners / notifications | 0 / 0 / 0 / 6 |
| React pages / components | 47 / 22 |
| migration files / application-created tables | 50 / 38 |
| runnable test classes / test methods | 129 / 865 |
| factory / seeders | 1 / 15 |

## Статус изменения репозитория

В рамках документирования application code, schema, frontend и tests не изменялись. Команды миграции/seeding и destructive commands не запускались. Commit и push не выполнялись.

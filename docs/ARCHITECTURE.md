# Архитектура Safi Life

[Главная](./PROJECT_DOCUMENTATION.md) · [Controllers](./CONTROLLERS.md) · [Services](./SERVICES.md) · [API](./API.md) · [Database](./DATABASE.md)

## Системный контекст

Репозиторий представляет один Laravel 13 application. Laravel обслуживает JSON API, Sanctum tokens, scheduler, notifications, filesystem и одну Blade-точку входа. React/Vite — SPA внутри того же deployable artifact. Отдельных microservices, module packages или repository/action layers нет.

Composer runtime dependencies: PHP `^8.3`, `laravel/framework ^13.0`, `laravel/sanctum ^4.3`, `laravel/tinker ^3.0`. Development dependencies: Faker, Pail, PAO, Pint, Mockery, Collision и PHPUnit 12. Отдельные payment/MLM/vendor packages не подключены: эта логика находится в `app/Services`.

```text
┌──────────────── Browser ────────────────┐
│ React Router                           │
│ public / dashboard / admin / support   │
│ CartContext + API client + i18n        │
└───────────────────┬────────────────────┘
                    │ /api/* JSON, Bearer token
┌───────────────────▼────────────────────┐
│ Laravel 13                              │
│ Routes → Middleware → Controllers       │
│             ↓                           │
│       Services / Eloquent               │
│             ↓                           │
│ DB + Filesystem + Notifications         │
└───────────┬───────────────────┬─────────┘
            │                   │
       TipTopPay           Mail/database
       callbacks           notifications
```

## Backend architecture

Архитектура pragmatically layered:

- routes задают public/protected/RBAC группы;
- controllers выполняют HTTP orchestration, inline validation или вызывают FormRequest;
- JsonResource нормализуют API output;
- services содержат MLM, payment, wallet, order, support и destructive maintenance logic;
- Eloquent models задают persistence/relations/scopes;
- transaction boundaries находятся в сервисах, а финансовые строки блокируются `lockForUpdate()`;
- Laravel notifications вызываются непосредственно из сервисов;
- console commands являются adapters к service layer.

CQRS, domain events, repositories и DTO отсутствуют. Read endpoints часто строят Eloquent query прямо в controller; write endpoints преимущественно делегируют сервису.

## Frontend architecture

Entry chain:

```text
resources/views/app.blade.php
  ↓ @vite(resources/js/safi/main.tsx)
main.tsx
  ↓ React StrictMode
App.tsx
  ↓ CartProvider
router/routes.tsx
  ├─ MainLayout → public pages
  ├─ DashboardLayout → partner pages
  ├─ AdminLayout → admin/support/accountant pages
  └─ SupportUnavailable fallback
```

State management library не используется. Состояние:

- component-local React state;
- `CartContext` с `localStorage.safi_cart_items`;
- Sanctum token в `localStorage.safi_token`;
- tree view settings admin structure — localStorage;
- permissions/current user загружаются layouts через `/api/me` и `/api/me/permissions`.

`lib/api.ts` — единый fetch client, type declarations и normalizers. `lib/endpoints.ts` — endpoint registry. `apiRequest()` добавляет `Accept`, `Accept-Language`, Bearer token, `credentials: same-origin`, сериализует JSON/FormData и на 401 удаляет token/redirects на login. Redux/Zustand/React Query не найдены.

## Request lifecycle

### Public read

```text
React page
  → apiRequest(auth=false)
  → /api/public/* route
  → api + SetLocaleFromRequest
  → PublicApi Controller
  → Eloquent query
  → JsonResource
  → JSON
```

### Authenticated write

```text
React form
  → apiRequest(auth=true, Bearer)
  → auth:sanctum
  → EnsureAccountActive
  → optional EnsureRolePermission / EnsureUserCanAccessOwnResource
  → Controller
  → FormRequest or inline validation
  → Service
  → DB::transaction + lockForUpdate
  → model updates + audit rows + notification
  → Resource/JSON
```

### Payment callback

```text
TipTopPay POST callback
  → public /api/payments/tiptoppay/{event}
  → TipTopPayWebhookController
  → TipTopPayService::handle*()
  → HMAC check, если webhook secret задан
  → locate Payment (external ID, legacy order fallback)
  → transaction + status transition
  → Order/Package effects + provider-response audit
  → {code: 0} или rejection response
```

Подробно: [TipTopPay](./SERVICES.md#tiptoppayservice), [public payment callbacks](./API.md#public-api).

## Карта каталогов

### `app/`

```text
app/
├── Console/
│   └── Commands/              9 custom Artisan commands
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── Admin/         back office
│   │       ├── Auth/          forgot-password flow
│   │       ├── Concerns/      pagination response helper
│   │       ├── Dashboard/     partner dashboard
│   │       ├── Payments/      TipTopPay intent/callback adapters
│   │       ├── PublicApi/     public localized catalog/content
│   │       └── Support/       staff ticket API
│   ├── Middleware/            locale, active account, roles, ownership
│   ├── Requests/              13 FormRequests
│   └── Resources/             20 JSON Resources
├── Models/                    29 Eloquent models
├── Notifications/             6 synchronous notifications
├── Providers/                 empty AppServiceProvider
├── Services/
│   └── Payments/              TipTopPayService
└── Support/                   localization/legal/labels helpers
```

`Console/Commands` вызываются вручную или scheduler. Они взаимодействуют с `BonusService`, `ScheduledBinaryBonusService`, `PartnerDeletionService`, `StatusBonusService`, `DashboardBranchVolumeService` и rollback services. Риски приведены в [COMMANDS.md](./COMMANDS.md).

`Http/Controllers/Api/Admin` вызывается `/api/admin/*`; каждый route защищён permission middleware. Пустой `Admin\SupportTicketController` наследует staff controller, но реальные routes указывают на `Api\Support\TicketController` напрямую.

`Http/Controllers/Api/Dashboard` обслуживает React dashboard. Основные models: user/wallet/bonus/tree/order/withdrawal/support; business writes делегируются services.

`Http/Requests` выполняет transport validation. Самые важные: registration identity/referral, order delivery/payment strategy, withdrawal method, partner creation и super-admin transaction delete.

`Http/Resources` — единственное выраженное представление ответа. Resource часто публикует snake_case и совместимые camelCase aliases; frontend normalizers поддерживают оба формата.

`Models` описаны в [DATABASE.md](./DATABASE.md#eloquent-models). `Services` — в [SERVICES.md](./SERVICES.md).

### `routes/`, `bootstrap/`, `config/`

- `routes/api.php`: 164 route entries, public/auth/dashboard/support/admin aliases.
- `routes/web.php`: redirects registration aliases и SPA catch-all, исключающий `api|storage`.
- `routes/console.php`: `inspire` и два schedule entries binary calculation.
- `bootstrap/app.php`: route registration, health `/up`, global locale middleware и aliases.
- `bootstrap/providers.php`: только `AppServiceProvider`; provider register/boot пусты.
- `config/role_permissions.php`: frontend navigation и server permission-role matrix.
- `config/safi.php`: feature flags и withdrawal settings.
- `config/tiptoppay.php`: payment settings.
- остальные config — штатные Laravel app/auth/cache/database/filesystem/log/mail/queue/sanctum/services/session.

### `database/`

- `migrations/`: 50 файлов; schema и evolution финансовых/audit полей.
- `seeders/`: 15 классов, включая master `DatabaseSeeder`; часть demo seeders.
- `factories/UserFactory.php`: единственная factory.

Полная карта: [DATABASE.md](./DATABASE.md).

### `resources/`

```text
resources/
├── css/app.css               стандартный/не основной entry
├── js/app.js                 пустой legacy entry
├── js/safi/
│   ├── components/           layouts, structure canvas, UI
│   ├── config/               frontend feature flags
│   ├── context/              CartContext
│   ├── data/                 static/demo content fallbacks
│   ├── hooks/                TipTopPay widget hook
│   ├── i18n/ + locales/      runtime/UI translations
│   ├── lib/                  API, endpoints, permissions, labels, formatters
│   ├── pages/                public/admin/dashboard pages
│   ├── router/               React Router declaration
│   ├── App.tsx
│   ├── main.tsx
│   └── index.css
└── views/app.blade.php        SPA host
```

Подробнее: [FRONTEND.md](./FRONTEND.md).

`resources/views/welcome.blade.php` — оставшийся Laravel welcome template; зарегистрированного route, который его возвращает, не найдено.

Root `lang/` содержит backend translation catalogs `api.php`, `auth.php`, `validation.php` для `ru`, `kk`, `ky`, `en`, `mn`; это отдельный от frontend i18next каталог.

### `public/`, `storage/`, `tests/`

- `public/index.php` — HTTP front controller; `.htaccess`; `robots.txt`; placeholder SVG; локальный Vite build в `public/build` существует, но Git его не отслеживает.
- `storage/app/public` — avatars/news/products через public disk; `storage/app/private/support/...` — support attachments; binary rollback artifacts также пишутся в private storage.
- `storage/framework` — sessions/cache/views/testing artifacts; содержимое runtime не является business source.
- `tests/Feature`, `tests/Unit`, `tests/Support` — подробности в [TESTING.md](./TESTING.md).

### Не существующие ожидаемые каталоги

`modules`, `app/Jobs`, `app/Events`, `app/Listeners`, `app/Policies`, `app/Observers`, `app/Repositories`, `app/DTO`, `app/Enums`, `app/Mail` — **Не найдено в текущей реализации**.

## Authentication и authorization

### Registration

`POST /api/register` → `RegisterRequest` → `AuthController::register()` → `PartnerRegistrationService::register()`.

- Public registration без sponsor закрыта по умолчанию (`SAFI_PUBLIC_REGISTRATION_ENABLED=false`).
- Валидный sponsor/referral flow разрешён даже при закрытой общей регистрации.
- Sponsor должен быть active `user`/`super_admin` и иметь активный START/VIP/ELITE.
- При sponsor обязательна `branch=L|R`.
- Создаются user/profile/3 wallets/binary node, отправляется mail, выдаётся Sanctum token.
- Выбранный public package не назначается: package purchase/payment должен пройти отдельно; package purchase feature по умолчанию также выключен.

### Login/token

`LoginRequest`: либо `login`, либо email и password. `AuthController` ищет login или profile phone; email обрабатывается отдельно. Password проверяется `Hash::check`. Blocked/inactive/deleted/archived account отклоняется. Token хранится в `personal_access_tokens`; logout удаляет только текущий token.

Password reset стандартным broker не используется в UI flow. Реализован staff-assisted flow:

```text
check email → verify email+phone → forgot_password_requests:pending
  → support/admin/super_admin
  → resetPassword (Hash + revoke all tokens) или cancel
```

### Middleware

| Middleware | Назначение | Где используется |
|---|---|---|
| `SetLocaleFromRequest` | нормализует `Accept-Language`, ставит locale/request attribute | глобально |
| `auth:sanctum` | Bearer personal token | protected API |
| `EnsureAccountActive` | 403 для trashed/blocked/inactive/deleted/archived | protected API |
| `EnsureRolePermission` | permission key → allowed roles из config | admin/support API |
| `EnsureUserCanAccessOwnResource` | сравнивает `resource.user_id` | dashboard ticket aliases |
| `EnsureAdmin` | admin/super_admin или переданные roles | alias существует, текущая основная матрица использует role_permission |
| `EnsureSuperAdmin` | только super_admin | alias; часть controllers/services проверяет непосредственно |
| `EnsureSupportOrSuperAdmin` | support/admin/super_admin | alias; основные routes используют support.manage |

### Role matrix

| Permission | Roles |
|---|---|
| `support.manage` | support, admin, super_admin |
| `admin.overview` | admin, accountant, super_admin |
| `admin.transactions.read` | admin, accountant, super_admin |
| `admin.withdrawals.read` | admin, accountant, super_admin |
| `admin.orders.read` | admin, accountant, super_admin |
| `admin.orders.manage` | admin, super_admin |
| `admin.bonuses.manage` | admin, super_admin |
| `admin.read` | admin, super_admin |
| `admin.catalog.write` | super_admin |
| `admin.settings` | super_admin |
| `admin.reports` | accountant, super_admin |
| `admin.partners.create` | super_admin |
| `admin.partners.manage` | admin, super_admin |
| `admin.partners.identity/balance` | super_admin |
| `admin.forgot_password.manage` | support, admin, super_admin |
| `admin.withdrawals.manage` | accountant, super_admin |

`dashboard.access` определён как `user`, но dashboard route group его не применяет: все authenticated active roles могут технически вызвать dashboard API, хотя React layouts перенаправляют non-user.

## Transactions и consistency

Финансовые write services используют `DB::transaction` и pessimistic locks:

- WalletService сам обновляет переданный/загруженный wallet; вызывающие bonus/order/withdrawal/transfer services блокируют нужные строки.
- PartnerTransferService и InternalWalletTransferService блокируют wallets в детерминированном порядке, чтобы снизить deadlock risk.
- Binary/service adjustments сверяют и корректируют main/deposit legs.
- Status/X2 user-definition unique constraints обеспечивают once-only award marker.
- Binary runs имеют unique `(user_id, period_start, period_end)` и service-level duplicate checks.
- Partner transfer имеет unique `(sender_user_id, idempotency_key)` при non-null key.

## Caching, queues и scheduler

- Domain read cache не используется.
- Laravel cache driver задаётся `CACHE_STORE`; scheduled binary service использует cache lock, а schedule — `withoutOverlapping()` с общим mutex name.
- `.env.example` указывает database queue, и framework job tables присутствуют, но business Jobs/dispatch points не найдены.
- Notifications используют trait `Queueable`, но не `ShouldQueue`, то есть вызываются synchronously.
- Scheduler: 1-е и 15-е число, 03:00 `Asia/Tashkent`, команда `safi:binary-recalculate-all --scheduled`.

## Filesystem

| Данные | Disk/path | Access |
|---|---|---|
| user avatar | public, обычно `avatars/*` | URL через storage link |
| news/product images | public | public resource URL |
| support attachment | local, `support/{ticket}/{message}/...` | authenticated controller download |
| binary rollback artifacts | `storage/app/private/binary-rollbacks` по default command path | CLI/server filesystem |

Upload validation: avatar image max 5 MB; support allowlist jpg/jpeg/png/webp/pdf/doc/docx/xls/xlsx/txt max 5 MB; admin news/product validators ограничивают image uploads. Реальный web server upload limit требует проверки.

## External integrations

### TipTopPay

Единственная runtime payment integration. Backend не выполняет outbound charge API: создаёт widget intent, browser загружает hosted JS, провайдер вызывает callbacks. Секретные значения берутся только из ENV. Подробности: [SERVICES.md](./SERVICES.md#tiptoppayservice).

### Mail/database notifications

Mail transport — Laravel mail config; `.env.example` задаёт `log`. Database notifications хранятся в `notifications`. Отдельных Mailables нет.

Все classes находятся в `app/Notifications`, используют `Queueable`, но не реализуют `ShouldQueue`:

| Notification | Direct trigger/caller | Channels | Payload/recipient |
|---|---|---|---|
| `UserRegisteredNotification` | `PartnerRegistrationService` после create flow | mail | welcome и login новому user; password не отправляется; `toArray()` существует, но database channel не выбран |
| `BonusAccruedNotification` | Bonus/Status bonus cash flows | mail, database | type/amount/source transaction context получателю bonus |
| `StatusAchievedNotification` | `StatusService` при upward rank | mail, database | previous/new status and weak-leg PV пользователю |
| `X2BonusAwardedNotification` | `X2BonusService` после marker | mail, database | definition/reward/amount/qualification пользователю |
| `WithdrawalRequestedNotification` | `WithdrawalService::requestWithdrawal()` | mail | request amount/method/status заявителю |
| `PartnerTransferCompletedNotification` | `PartnerTransferService` | database | direction/counterparty/amount отдельно sender и recipient |

Поскольку dispatch выполняется внутри вызывающего synchronous flow, mail transport exception способен rollback-нуть surrounding DB transaction. SMS/Telegram/push channels не найдены.

### Frontend external content

Google Fonts, TipTopPay widget, Unsplash fallback/demo images и внешний Safi logo URL. Это frontend HTTP resources, не server-side business API.

## Security review

Положительные механизмы:

- Sanctum tokens и active-account middleware;
- role-permission matrix на admin routes;
- FormRequests/inline Laravel validation;
- Eloquent/query builder вместо raw user-controlled SQL; найденные raw clauses parameterized или constant;
- mass assignment ограничен `$fillable`; чувствительные updates часто используют explicit `forceFill` после validation;
- DB locks и transactions для денежных flows;
- source IDs/metadata, admin logs и transaction audits;
- private support attachments и ownership check;
- binary/award unique constraints и idempotency keys.

Potential issues с уровнем риска перечислены в [главном документе](./PROJECT_DOCUMENTATION.md#potential-issues-найденные-чтением-кода). Дополнительно:

- Laravel web CSRF применим к `web` middleware; основной Bearer API находится в `api` group и не использует CSRF, что соответствует token API. `credentials: same-origin` отправляется frontend, но авторизация опирается на Bearer token.
- Token находится в localStorage: XSS получает к нему доступ; CSP/security headers не настроены в repo и требуют проверки на web server.
- Payment callbacks public by design; безопасность полностью зависит от заполненного webhook secret.
- Login/register явного route throttle не имеют.
- Admin order status cancellation восстанавливает stock, но не PV; payment-status вручную `paid` запускает auto-package-upgrade.

## Providers, observers, events и lifecycle hooks

`AppServiceProvider` пуст. Model observers и custom Eloquent boot hooks не найдены. Поэтому ни один важный financial effect не запускается скрытым observer; он виден как прямой вызов service/controller. Это облегчает трассировку, но означает, что обход основного сервиса (например direct model update) может обойти ожидаемые side effects.

| Business trigger | Laravel Event | Listener | Реальный side effect path |
|---|---|---|---|
| registration | не найден | не найден | `PartnerRegistrationService` прямо создаёт tree/wallets и вызывает notification |
| package activation/upgrade | не найден | не найден | `PackageService` прямо вызывает PV/referral/status/wallet services |
| referral/binary bonus | не найден | не найден | `BonusService` прямо пишет ledgers и вызывает notification |
| status/X2 | не найден | не найден | `StatusService` → `StatusBonusService`/`X2BonusService` |
| withdrawal | не найден | не найден | controller → `WithdrawalService` |
| order/payment | не найден | не найден | `CheckoutOrderService` и `TipTopPayService` |

Каталоги `app/Events` и `app/Listeners` отсутствуют; event auto-discovery/registration в provider не настроены.

## Support helper classes

| Class/path | Purpose | Called by / persistence |
|---|---|---|
| `App\Support\LocalizedValue` — `app/Support/LocalizedValue.php` | нормализует ru/kk/ky/en/mn (legacy kz/kg), выбирает translated value с ru/fallback | JsonResources, status/permission/public controllers; read-only |
| `App\Support\SystemLabel` — `app/Support/SystemLabel.php` | central labels package, MLM/account/order/payment/transaction/withdrawal/product status, directions and transaction types | Resources/controllers/notification text; read-only |
| `App\Support\LegalSettings` — `app/Support/LegalSettings.php` | allowlisted legal/company/contact defaults, grouping/types and public values | public legal, admin settings/readiness; `ensureDefaults()` может `firstOrCreate` rows in `system_settings` даже на GET |

Других providers нет: `bootstrap/providers.php` регистрирует только пустой `AppServiceProvider`.

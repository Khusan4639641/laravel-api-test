# Admin, support и user dashboard

Этот документ связывает React-страницы с реальными API и backend-классами. Полный список HTTP-методов и middleware приведён в [API.md](./API.md), execution flow контроллеров — в [CONTROLLERS.md](./CONTROLLERS.md).

## Два уровня контроля доступа

1. `GET /api/me/permissions` (`PermissionController`) читает `config/role_permissions.php` и возвращает роль, стартовый URL, разрешённые frontend paths и меню.
2. `AdminLayout` и `DashboardLayout` получают `/api/me` и `/api/me/permissions`, проверяют bearer token и frontend path. Это UX-защита, а не доверенная граница.
3. API находится под `auth:sanctum` и `account_active`; чувствительные группы дополнительно защищены `role_permission:<permission>` (`EnsureRolePermission`).
4. Доступ к собственным support tickets дополнительно проверяет `own_resource:ticket` (`EnsureUserCanAccessOwnResource`).

Роли хранятся строкой в `users.role`; отдельные таблицы roles/permissions **не найдены в текущей реализации**.

| Role | Frontend areas | Основные API permissions | Ограничения |
|---|---|---|---|
| `user` | `/dashboard/*` | `dashboard.access` объявлен, но dashboard route group явно им не обёрнут | Backend всё равно требует Sanctum и активный аккаунт; dashboard layout отклоняет не-`user` |
| `support` | `/admin`, `/admin/support`, `/admin/forgot-password`, `/support/*` | `support.manage`, `admin.forgot_password.manage` | Нет финансовых, partner и catalog permissions |
| `admin` | большинство `/admin/*`, кроме reports/settings/readiness/bulk create | overview/read, transaction/withdrawal/order read, order/bonus/partner manage, support | Не может менять каталог, identity/balance, approving withdrawals, reports/settings |
| `accountant` | overview, transactions, withdrawals, orders, reports | read accounting data, `admin.withdrawals.manage`, `admin.reports` | Не получает `admin.read`, catalog/partners/bonus permissions |
| `super_admin` | все описанные backoffice pages | все API permissions | Единственная роль для catalog write, settings, identity, balance и partner creation |

Potential issue (LOW): `dashboard.access` определён в конфигурации, но dashboard API group не использует `role_permission:dashboard.access`. Не-`user` с действительным токеном и активным аккаунтом технически может обращаться к части `/api/dashboard/*`; frontend это блокирует, но frontend не является authorization boundary.

## Admin pages

Во всех строках ниже layout сначала вызывает `GET /api/me` и `GET /api/me/permissions`. Если страница использует несколько endpoint-ов, перечислены основные; полный реестр находится в [API.md](./API.md#admin-api).

| React path / page | API и controller | Service/data source | Доступ по frontend config |
|---|---|---|---|
| `/admin` → `AdminHome.tsx` | support видит `AdminSupport`; остальные — `GET /api/admin/overview` → `Admin\OverviewController` | агрегаты `User`, `Order`, `WalletTransaction`, `WithdrawalRequest`, `BonusTransaction` | admin/accountant/super_admin; support переходит к обращениям |
| `/admin/partners` → `AdminPartners.tsx` | partners/search/sponsors, registration packages, `POST /admin/partners` | `Admin\UserController`, `Admin\PartnerController` → `PartnerRegistrationService`, `BinaryTreeService`, `PackageService`, `WalletService` | admin/super_admin; create только super_admin API |
| `/admin/partners/bulk-create` → `AdminPartnersBulkCreate.tsx` | `POST /admin/partners/bulk-create` | `PartnerController::bulkCreate()` → тот же registration flow для каждой строки | super_admin |
| `/admin/partners/:id` → `AdminPartnerDetail.tsx` | partner/show, transactions, delete-preview, package/status/identity/balance/password, delete, binary recalc | `PartnerController`; `PackageService`, `StatusService`, `AdminWalletTransactionService`, `PartnerDeletionService`, `BonusService` | admin/super_admin; identity/balance только super_admin |
| `/admin/forgot-password` → `AdminForgotPassword.tsx` | list/show/reset/cancel password requests | `Admin\ForgotPasswordRequestController`, `ForgotPasswordRequest`, `User`, `Hash` | support/admin/super_admin |
| `/admin/structure` → `AdminStructure.tsx` | `GET /admin/structure`, `/structure/root-orphans` | `Admin\StructureController`, `BinaryNode`, cached user PV; tree DTO assembled controller-side | admin/super_admin |
| `/admin/transactions` → `AdminTransactions.tsx` | list, PATCH/DELETE transaction | `Admin\TransactionController` → `AdminWalletTransactionService`; `WalletTransaction`, `TransactionAdminAudit` | admin/accountant/super_admin (write endpoint имеет ту же read permission) |
| `/admin/withdrawals` → `AdminWithdrawals.tsx` | list, approve, reject | `Admin\WithdrawalController` → `WithdrawalService`; wallets and ledger | admin видит; approve/reject только accountant/super_admin |
| `/admin/bonuses` → `AdminBonuses.tsx` | фактически запрашивает admin transactions и вызывает binary recalc/transaction mutation | `Admin\TransactionController`, `Admin\BonusController`, `BonusService`/`ScheduledBinaryBonusService` | admin/super_admin |
| `/admin/packages` → `AdminPackages.tsx` | list/create/update packages | `Admin\PackageController`, `Package` | admin видит; write только super_admin |
| `/admin/statuses` → `AdminStatuses.tsx` | `GET /admin/statuses` | `Admin\StatusController`, `StatusService::definitions()` | admin/super_admin |
| `/admin/products` → `AdminProducts.tsx` | product list/create/update/delete and image upload | `Admin\ProductController`, `Product`, public disk | admin видит; write только super_admin |
| `/admin/orders` → `AdminOrders.tsx` | list and PATCH order status | `Admin\OrderController`, `Order`, `OrderItem`; cancellation restores reserved stock | admin/accountant/super_admin; change status admin/super_admin |
| `/admin/news` → `AdminNews.tsx` | list/create/update/delete | `Admin\NewsController`, `News`, public disk | admin видит; write only super_admin |
| `/admin/support` → `AdminSupport.tsx` | ticket list/show/reply/close/reopen, attachment download | `Support\TicketController` → `SupportTicketService`, `SupportAttachmentStorage` | support/admin/super_admin |
| `/admin/reports` → `AdminReports.tsx` | `GET /admin/reports/summary`; CSV формируется в browser из ответа | `Admin\ReportController`; read-only aggregates | accountant/super_admin |
| `/admin/settings` → `AdminSettings.tsx` | GET/PUT settings | `Admin\SettingsController`, `SystemSetting` | super_admin |
| `/admin/payment-readiness` → `AdminPaymentReadiness.tsx` | `GET /admin/payment-readiness` | `Admin\PaymentReadinessController`; проверяет TipTopPay config, routes и legal settings | super_admin; path разрешён, но отсутствует в menu config |
| `/support/profile` → `AdminProfile.tsx` | использует только данные layout из `/me` | API записи профиля backoffice не вызывает | support |

`AdminPreviewPage.tsx` (`/admin-preview`) — публичная статическая preview-страница, а не защищённая admin panel.

### Partner management flow

```text
AdminPartners / AdminPartnerDetail
  -> bearer token + role permission
  -> Admin\PartnerController
  -> FormRequest (create/update/identity/balance/bulk)
  -> PartnerRegistrationService / PackageService / StatusService /
     AdminWalletTransactionService / PartnerDeletionService
  -> users + profiles + binary_nodes + wallets + ledgers
  -> JsonResource/JSON
```

Ручное назначение пакета с `apply_business_effects=true` может создать PV, referral bonus и wallet transactions. Изменение balance и запуск binary recalculation также являются финансовыми операциями; см. [WALLET.md](./WALLET.md#admin-financial-mutation) и [COMMANDS.md](./COMMANDS.md#high-risk-operations).

## User dashboard pages

| React path / page | API | Backend flow / displayed data |
|---|---|---|
| `/dashboard` → `Overview.tsx` | overview + public statuses | `Dashboard\OverviewController`; package/status, wallets, PV, referrals, earnings, recent transactions |
| `/dashboard/structure` → `Structure.tsx` | dashboard structure and paginated partner list | `Dashboard\StructureController`; `BinaryNode`, descendants, branch/PV summaries; `StructureTreeCanvas` renders tree |
| `/dashboard/transactions` → `Transactions.tsx` | dashboard transactions | `Dashboard\TransactionController`; own `WalletTransaction` rows, filters/pagination |
| `/dashboard/bonuses` → `Bonuses.tsx` | overview, earnings summary, withdrawals, partner transfers, public statuses | `EarningsSummaryService`, `WithdrawalService`, `PartnerTransferService`; bonus/withdrawal forms and histories |
| `/dashboard/package`, `/dashboard/package-status` → `PackageStatus.tsx` | overview, dashboard packages, public statuses | current package/status, progress and available package catalog; user changes remain disabled by config by default |
| `/dashboard/products` → `Products.tsx` | regular/deposit dashboard products, earnings summary | dashboard product controllers; cart links and deposit affordability display |
| `/dashboard/orders` → `Orders.tsx` | own orders; create TipTopPay intent | `OrderController`/`Dashboard\OrderController`, `TipTopPayIntentController` |
| `/dashboard/orders/:id` → `OrderDetail.tsx` | own order; create payment intent | ownership enforced by query/controller; payment status/items/delivery details |
| `/dashboard/news` → `News.tsx` | public news | `PublicApi\NewsController`; localized news |
| `/dashboard/profile` → `Profile.tsx` | profile from layout; POST/PATCH avatar | `Dashboard\ProfileController`; image validation and public disk storage |
| `/dashboard/support` → `Support.tsx` | own tickets, show/reply/close | `Dashboard\SupportTicketController` → `SupportTicketService`; own-resource middleware |

### Dashboard data chain

```text
Dashboard page
  -> resources/js/safi/lib/api.ts
  -> Authorization: Bearer <Sanctum token>
  -> /api/dashboard/*
  -> Dashboard controller
  -> query/service
  -> model/table
  -> JSON normalization in api.ts/page
  -> cards, tables, forms or StructureTreeCanvas
```

## Support

Support включён константой `features.support = true`. User ticket создаётся с subject/category/priority/message и optional attachment. `SupportTicketService` создаёт initial message, attachment and status timestamps. Staff может reply, assign, change status, close/reopen. Attachments проходят через `SupportAttachmentStorage`; скачивание проверяет ownership для dashboard и staff permission для backoffice.

Notification class или mail для нового support message **не найдены в текущей реализации**.

## Admin-specific risks

- **HIGH:** PATCH/DELETE admin wallet transaction пересчитывает balance через delta; отрицательный итог явно не запрещён в `AdminWalletTransactionService`.
- **HIGH:** admin binary recalculation изменяет bonus and wallet ledgers; повтор для того же immutable period защищён unique run, но force recalculation создаёт correction logic и требует предварительной сверки.
- **HIGH:** manual package/status/balance operations влияют на деньги/PV; actor and reason должны сохраняться в audit metadata/logs.
- **MEDIUM:** frontend permissions скрывают UI, но нельзя использовать их вместо backend permission middleware.
- **MEDIUM:** страница `AdminBonuses` работает преимущественно с `wallet_transactions`, хотя отдельный `/api/admin/bonuses` и `bonus_transactions` также существуют; оператор должен различать эти ledgers.

## Где находится код

- Frontend routing: `resources/js/safi/router/routes.tsx`
- Layout guards: `resources/js/safi/components/admin/AdminLayout.tsx`, `resources/js/safi/components/dashboard/DashboardLayout.tsx`
- Permissions normalization: `resources/js/safi/lib/permissions.ts`
- Server role matrix: `config/role_permissions.php`
- API middleware: `app/Http/Middleware/EnsureRolePermission.php`
- Admin pages: `resources/js/safi/pages/admin/`
- Dashboard pages: `resources/js/safi/pages/dashboard/`
- Admin controllers: `app/Http/Controllers/Api/Admin/`
- Dashboard controllers: `app/Http/Controllers/Api/Dashboard/`

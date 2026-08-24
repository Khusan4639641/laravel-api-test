# Controllers и execution flow

[Главная](./PROJECT_DOCUMENTATION.md) · [API routes](./API.md) · [Services](./SERVICES.md) · [Admin](./ADMIN.md) · [Frontend](./FRONTEND.md)

Изучено 54 concrete controller classes. Все API responses JSON, кроме attachment downloads. Общая защита protected group: `auth:sanctum` + `account_active`; admin/support дополнительно имеют permission middleware, указанный в [API.md](./API.md). Laravel validation обычно даёт 422, model binding/findOrFail — 404, middleware/explicit abort — 401/403.

`RespondsWithPagination` используется list controllers: normalizes `per_page` в 1..100 и возвращает `data`, named collection alias, `links`, `meta`.

## Public/auth controllers

### `App\Http\Controllers\Api\AuthController`

Path: `app/Http/Controllers/Api/AuthController.php`. Responsibility: register/login/Sanctum session.

| Method / route | Request/authorization | Execution flow / response / errors |
|---|---|---|
| `register()` — POST `/api/register` | `RegisterRequest`, public | normalize referral/branch/package; resolve active sponsor; require sponsor package/canInvite; global registration flag only when sponsor absent; `PartnerRegistrationService::register`; `UserResource` + new Sanctum token, 201. Invalid sponsor/link/package/identity 422; registration disabled 403. [Service details](./SERVICES.md#partnerregistrationservice). |
| `login()` — POST `/api/login` | `LoginRequest`, public | find by email, login or exact profile phone; `Hash::check`; reject blocked/inactive/deleted/archived; load profile/wallet/package/sponsor/node/referral count; create token. Bad credentials/account 422. |
| `logout()` — POST `/api/logout` | Sanctum active | delete current access token only; message. |
| `me()` — GET `/api/me` | Sanctum active | loaded user graph → `UserResource`; absent auth 401. |

### `App\Http\Controllers\Api\Auth\ForgotPasswordController`

Path: `app/Http/Controllers/Api/Auth/ForgotPasswordController.php`. Routes throttled `6/min`.

- `checkEmail()` — POST `/api/auth/forgot-password/check-email`: required email; verifies non-deleted user, returns normalized email; unknown 422.
- `store()` — POST `/api/auth/forgot-password/request`: email+phone; normalizes phone digits (`8…`→`7…`), matches profile; existing pending request returned unchanged, otherwise creates pending `ForgotPasswordRequest`, records IP/user agent and `AdminActionLog`, returns 201. No password reset mail/token is sent.

### `App\Http\Controllers\Api\ReferralController`

Path: `app/Http/Controllers/Api/ReferralController.php`.

`show()` — GET `/api/ref/{user_id}/{branch}` public. Normalizes L/R, finds eligible sponsor with active public package, calls `BinaryTreeService::findSpilloverPosition`; returns sponsor, validity, direct slot and spillover parent. Invalid branch 422; sponsor 404. No write.

### `App\Http\Controllers\Api\ProductController`

Path: `app/Http/Controllers/Api/ProductController.php`.

- `index()` GET `/api/products`: active non-deposit products → `ProductResource` collection.
- `deposit()` GET `/api/products/deposit`: active deposit products.
- `show()` GET `/api/products/{product}`: active non-deposit only; otherwise 404.

These are unpaginated compatibility routes; `PublicApi\ProductController` provides paginated equivalents.

### `App\Http\Controllers\Api\PublicApi\ProductController`

Path: `app/Http/Controllers/Api/PublicApi/ProductController.php`.

- `index()` GET `/api/public/products`: paginated active non-deposit products.
- `show()` GET `/api/public/products/{product}`: active non-deposit product; 404 otherwise.

### `App\Http\Controllers\Api\PublicApi\PackageController`

Path: `app/Http/Controllers/Api/PublicApi/PackageController.php`.

- `index()` GET `/api/public/packages`: active START/VIP/ELITE, sorted/paginated.
- `registration()` GET `/api/public/registration-packages`: active registration starters START/VIP only.

Both use `PackageResource`; dashboard action fields depend on current auth only in protected package controller, not here.

### `App\Http\Controllers\Api\PublicApi\NewsController`

Path: `app/Http/Controllers/Api/PublicApi/NewsController.php`.

- `index()` GET `/api/public/news`: published/active rows ordered published_at/latest, paginated `NewsResource`.
- `show()` GET `/api/public/news/{news}`: rejects draft/unpublished with 404.

### `App\Http\Controllers\Api\PublicApi\FaqController`

Path: `app/Http/Controllers/Api/PublicApi/FaqController.php`. `index()` GET `/api/public/faqs`: active rows ordered category/sort/id, paginated `FaqResource`.

### `App\Http\Controllers\Api\PublicApi\StatusController`

Path: `app/Http/Controllers/Api/PublicApi/StatusController.php`. `index()` GET `/api/public/statuses`: calls `StatusService::publicStatuses()`, returns both `data` and `statuses`; read only.

### `App\Http\Controllers\Api\PublicApi\LegalSettingsController`

Path: `app/Http/Controllers/Api/PublicApi/LegalSettingsController.php`. Invokable GET `/api/public/legal-settings`: `LegalSettings::publicValues()` reads/ensures allowlisted system settings and returns `settings`.

### `App\Http\Controllers\Api\PermissionController`

Path: `app/Http/Controllers/Api/PermissionController.php`. GET `/api/me/permissions`: reads role definition from config (fallback user), localizes labels by request language, returns role, redirect, allowed frontend routes and menu. Does not grant backend permission itself.

## User business controllers

### `App\Http\Controllers\Api\OrderController`

Path: `app/Http/Controllers/Api/OrderController.php`. Protected.

- `index()` GET `/api/orders`: current user's orders with items/products/packages and counts; returns compatibility `data` and `orders` collections.
- `show()` GET `/api/orders/{order}`: explicit owner check; foreign order is 404.
- `store()` POST `/api/orders`: `StoreOrderRequest` → `CheckoutOrderService::create()` → `OrderResource`, 201. Validation: items, strategy, required delivery. Service locks products, creates order/snapshots, decrements stock, and either debits deposit/cashback or immediately accrues regular PV. Regular order referral bonus call отсутствует. Errors: mixed products, invalid strategy, insufficient stock/deposit, inactive product. [Order service](./SERVICES.md#checkoutorderservice).

### `App\Http\Controllers\Api\DepositPurchaseController`

Path: `app/Http/Controllers/Api/DepositPurchaseController.php`.

- `__invoke()` POST `/api/deposits/purchase`: inline product/item/delivery validation; calls `purchaseProduct(s)` (не generic amount method); returns order, deposit transaction, cashback bonus/balances, 201.
- `purchaseProduct()` POST `/api/deposit-products/{product}/purchase`: quantity >0; same flow.

`DepositPurchaseService` requires deposit-only active product, stock and balance; creates paid zero-PV order, debit deposit and 20% main cashback.

### `App\Http\Controllers\Api\PackageActivationController`

Path: `app/Http/Controllers/Api/PackageActivationController.php`. POST `/api/packages/{package}/activate`.

Protected; feature flag `user_package_changes_enabled` default false → 403. Requires active START/VIP and user without package, then `PackageService::upgradePackage`. Response `UserResource`. Inactive/wrong/current package 422. Detailed package/PV/referral effects: [PackageService](./SERVICES.md#packageservice).

### `App\Http\Controllers\Api\PackageUpgradeController`

Path: `app/Http/Controllers/Api/PackageUpgradeController.php`. POST `/api/packages/{package}/upgrade`.

Same feature guard; target active/upgradeable; `PackageService::upgradeExistingPackage` validates exact chain and returns result+UserResource. Invalid transition/package 422.

### `App\Http\Controllers\Api\BinaryBonusController`

Path: `app/Http/Controllers/Api/BinaryBonusController.php`. POST `/api/bonuses/binary/calculate`, permission `admin.bonuses.manage` despite generic URI. Calls `BonusService::calculateBinaryBonus(request user)`. Returns resource or no-bonus message/null. It calculates for the authenticated admin account itself; admin-specific user/all endpoints are normally used for partners.

### `App\Http\Controllers\Api\WithdrawalController`

Path: `app/Http/Controllers/Api/WithdrawalController.php`.

- `index()` GET `/api/withdrawals`: current user's full unpaginated withdrawal collection.
- `store()` POST `/api/withdrawals`: `StoreWithdrawalRequest`; gets main wallet; `WithdrawalService::requestWithdrawal`; 201. Missing main wallet 404; insufficient balance/invalid amount/method 422.

Dashboard aliases use separate paginated controller. [Withdrawal flow](./WALLET.md#withdrawal).

## Payment controllers

### `App\Http\Controllers\Api\Payments\TipTopPayIntentController`

Path: `app/Http/Controllers/Api/Payments/TipTopPayIntentController.php`.

- `__invoke()` POST order intent aliases `/api/orders/{order}/payment/tiptoppay/intent` and `/payments/...`: delegates ownership/payability/config checks to `TipTopPayService::createOrderPaymentIntent`; returns payment ID/external ID/widget intent.
- `package()` POST `/api/dashboard/package/{package}/payments/tiptoppay/intent`: feature flag package purchases; optional matching package code/upgrade_from; calls package intent.
- `packageFromPayload()` POST `/api/payments/tiptoppay/package-intent`: same flag; validates public package code, loads package, calls service.

Disabled flag 403; invalid transition/order/config 404/422. 50/50 intent may debit deposit part.

### `App\Http\Controllers\Api\Payments\TipTopPayWebhookController`

Path: `app/Http/Controllers/Api/Payments/TipTopPayWebhookController.php`. Public adapter.

| Method | Route | Flow |
|---|---|---|
| `status()` | GET `/api/payments/tiptoppay/status` | non-secret readiness flags |
| `check()` | POST `.../check` | HMAC, locate payment, amount/currency/status check |
| `pay()` | POST `.../pay` | HMAC; mark paid, order/package effects |
| `confirm()` | POST `.../confirm` | same paid handler with event=confirm |
| `fail()` | POST `.../fail` | mark failed/release deposit half |
| `refund()` | POST `.../refund` | terminal refunded state/release deposit |
| `cancel()` | POST `.../cancel` | terminal cancelled state/release deposit |

All business flow lives in [TipTopPayService](./SERVICES.md#tiptoppayservice). Missing webhook secret accepts unsigned callbacks — documented Potential issue.

## Dashboard controllers

### `App\Http\Controllers\Api\Dashboard\OverviewController`

Path: `app/Http/Controllers/Api/Dashboard/OverviewController.php`. GET `/api/dashboard/overview`.

Loads current user/profile/wallet/package/sponsor/node; derives branch volumes; queries recent visible transactions, bonus totals, main/total/hold balances, pending binary/withdrawals, descendant team count, order/withdrawal summaries and referral links. Returns `UserResource`, `WalletResource`, `WalletTransactionResource` plus compatibility top-level fields. Read-only across users/tree/wallet/bonus/run/order/withdrawal.

### `App\Http\Controllers\Api\Dashboard\StructureController`

Path: `app/Http/Controllers/Api/Dashboard/StructureController.php`. GET `/api/dashboard/structure`.

Loads active subtree by materialized path, decorates root branch/relative line, bulk-calculates node volumes, filters branch/search in memory, paginates rows (1..100), builds recursive tree and summaries/referral links. No node → root user only. Models: User/BinaryNode/Package; service `DashboardBranchVolumeService`. Read-only; excludes inactive nodes/accounts.

### `App\Http\Controllers\Api\Dashboard\TransactionController`

Path: `app/Http/Controllers/Api/Dashboard/TransactionController.php`. GET `/api/dashboard/transactions`.

Filters current visible ledger by all/credits/withdrawals/cashback, paginates `WalletTransactionResource`; summary queries main available, income types, pending/withdrawn. Read only.

### `App\Http\Controllers\Api\Dashboard\BonusController`

Path: `app/Http/Controllers/Api/Dashboard/BonusController.php`. GET `/api/dashboard/bonuses`: visible user bonuses with sources/wallet transaction, pagination and `EarningsSummaryService` summary.

### `App\Http\Controllers\Api\Dashboard\EarningsSummaryController`

Path: `app/Http/Controllers/Api/Dashboard/EarningsSummaryController.php`. GET `/api/dashboard/earnings-summary`: wraps `EarningsSummaryService::forUser`.

### `App\Http\Controllers\Api\Dashboard\NotificationController`

Path: `app/Http/Controllers/Api/Dashboard/NotificationController.php`. GET `/api/dashboard/notifications?limit=1..50`: fetches 3×limit recent database notifications, removes those tied to non-visible wallet transactions, returns localized data/unread count. Does not mark read; mark-read endpoint not found.

### `App\Http\Controllers\Api\Dashboard\PackageController`

Path: `app/Http/Controllers/Api/Dashboard/PackageController.php`. GET `/api/dashboard/packages`: load current package, paginate active START/VIP/ELITE as `PackageResource`; Resource consults `PackagePurchaseAvailabilityService` for action/disabled reason.

### `App\Http\Controllers\Api\Dashboard\ProductController`

Path: `app/Http/Controllers/Api/Dashboard/ProductController.php`. GET `/api/dashboard/products`: paginated active regular products.

### `App\Http\Controllers\Api\Dashboard\DepositProductController`

Path: `app/Http/Controllers/Api/Dashboard/DepositProductController.php`. GET `/api/dashboard/deposit-products`: paginated active deposit products.

### `App\Http\Controllers\Api\Dashboard\OrderController`

Path: `app/Http/Controllers/Api/Dashboard/OrderController.php`. GET `/api/dashboard/orders`: current non-voided orders, items/products/counts, paginated.

### `App\Http\Controllers\Api\Dashboard\WithdrawalController`

Path: `app/Http/Controllers/Api/Dashboard/WithdrawalController.php`.

- `index()` GET `/api/dashboard/withdrawals`: current requests paginated.
- `store()` POST same: `StoreWithdrawalRequest` → main wallet → WithdrawalService; 201.

### `App\Http\Controllers\Api\Dashboard\PartnerSearchController`

Path: `app/Http/Controllers/Api/Dashboard/PartnerSearchController.php`. GET `/api/dashboard/partners/search?q=`. For nonnumeric query min length 2; finds other active role=user by name/login/email/phone/ID, limit 1..30, returns `TransferPartnerResource`. Read-only.

### `App\Http\Controllers\Api\Dashboard\PartnerTransferController`

Path: `app/Http/Controllers/Api/Dashboard/PartnerTransferController.php`.

- `index()` GET aliases `/dashboard/partner-transfers` and `/dashboard/wallet/transfers`: sender-or-recipient transfer history, paginated.
- `store()` POST aliases: accepts recipient aliases, restricts source to main, validates amount/comment/idempotency key, calls `PartnerTransferService`, returns balances/IDs/resource 201. Deposit source/self/inactive recipient/insufficient balance → 422.

### `App\Http\Controllers\Api\Dashboard\InternalWalletTransferController`

Path: `app/Http/Controllers/Api/Dashboard/InternalWalletTransferController.php`. POST `/api/dashboard/wallets/internal-transfer`. Explicit role check admin/super_admin; validates from/to/different/positive; `InternalWalletTransferService` further restricts main→deposit; returns two WalletTransaction resources. Ordinary dashboard user gets 403.

### `App\Http\Controllers\Api\Dashboard\ProfileController`

Path: `app/Http/Controllers/Api/Dashboard/ProfileController.php`.

- `__invoke()` GET `/api/dashboard/profile`: loaded `UserResource`.
- `avatar()` POST/PATCH `/api/dashboard/profile/avatar`: image jpg/jpeg/png/webp max 5 MB, store public `avatars`, delete previous local avatar only, update users and profile, return URL. Storage failure → server error; invalid image 422.

### `App\Http\Controllers\Api\Dashboard\SupportTicketController`

Path: `app/Http/Controllers/Api/Dashboard/SupportTicketController.php`.

- `index()` GET ticket aliases: current user's tickets/messages/attachments paginated.
- `store()` POST aliases: `StoreSupportTicketRequest`, optional file; `SupportTicketService::createTicket`, 201.
- `show()` GET aliases: own-resource middleware + controller owner check; resource.
- `message()` POST messages: `SendSupportMessageRequest`; owner; service rejects closed ticket.
- `update()` PUT ticket: `UpdateSupportTicketRequest`, owner, not closed; update ticket/first user message.
- `close()` PATCH/POST aliases: owner, service closes.
- `download()` GET attachment: loads ticket, owner exact, private stream; foreign 403.

## Support staff controller

### `App\Http\Controllers\Api\Support\TicketController`

Path: `app/Http/Controllers/Api/Support/TicketController.php`. Permission `support.manage` (support/admin/super_admin); exposed under `/api/support/*` and `/api/admin/support*` aliases.

- `index()`: status/q filter over ticket/id/user identity, eager messages, paginated.
- `show()`: any ticket for authorized staff.
- `reply()`: `ReplySupportTicketRequest`, optional attachment, `SupportTicketService::sendAdminMessage`.
- `status()`: `UpdateSupportTicketStatusRequest`, updates closed/user timestamps directly.
- `assign()`: `AssignSupportTicketRequest`, default assignee=current staff, open→in_progress.
- `close()`/`reopen()`: service transitions.
- `download()`: private attachment stream; route permission is authorization.

### `App\Http\Controllers\Api\Admin\SupportTicketController`

Path: `app/Http/Controllers/Api/Admin/SupportTicketController.php`. Empty subclass of support `TicketController`. Registered route action не найден; admin aliases point to parent class directly. Its inherited methods are therefore implementation-compatible but currently unused as controller target.

## Admin controllers

### `App\Http\Controllers\Api\Admin\OverviewController`

Path: `app/Http/Controllers/Api/Admin/OverviewController.php`. GET `/api/admin/overview`, permission `admin.overview`. Aggregates role=user partners, active counts, orders/turnover/PV/bonuses/withdrawals/wallet flows, product/package counts and support status counts. Read-only JSON.

### `App\Http\Controllers\Api\Admin\UserController`

Path: `app/Http/Controllers/Api/Admin/UserController.php`.

- `index()` GET `/admin/users` and `/admin/partners`: offset or standard pagination, extensive text/numeric/status/package/city/balance/date filters/sort; partner endpoint restricts role=user; returns UserResource and summary.
- `search()` GET `/admin/partners/search`: small partner search result.
- `sponsorSearch()` GET `/admin/sponsors/search`: eligible sponsor search, supports selected sponsor inclusion.
- `show()` GET `/admin/users/{user}`: loaded UserResource.

Read-only; permission admin.read.

### `App\Http\Controllers\Api\Admin\PartnerController`

Path: `app/Http/Controllers/Api/Admin/PartnerController.php`. Main admin orchestration.

| Method | Route | Validation/flow |
|---|---|---|
| `store()` | POST `/admin/partners` | `StorePartnerRequest` super_admin; `PartnerRegistrationService`, optional START/VIP/business effects/referral; credentials + placement, 201 |
| `bulkCreate()` | POST `/admin/partners/bulk-create` | max 100; validates each row independently, creates successful rows, returns failures without global rollback |
| `show()` | GET `/{user}` | load profile/wallet/package/sponsor/node, derived branch PV, recent tx |
| `update()` | PUT `/{user}` | name/login/email/active-phone unique; updates user/profile |
| `identity()` | PATCH `/{user}/identity` | explicit super_admin; names/phone/email; transaction + AdminActionLog |
| `status()` | PATCH `/{user}/status` | allowed 10 statuses; direct status assignment + log; optional `apply_bonus_effects` only on upward rank → StatusBonusService |
| `package()` | PATCH `/{user}/package` | package ID + optional business effects; PackageService manual assignment |
| `block/unblock()` | PATCH paths | account_status; block revokes all tokens |
| `note()` | PATCH note | nullable free text admin_note |
| `balance()` | PATCH balance | explicit super_admin; set/adjust, nonnegative result; manual ledger + admin log |
| `changePassword()` | POST password | min8 confirmed; hash; returns plaintext credentials in response; tokens are not revoked here |
| `transactions()` | GET tx | recent visible rows limit 1..50 |
| `deletePreview()` | GET delete-preview | explicit super_admin; PartnerDeletionService read-only preview |
| `destroy()` | DELETE partner | explicit super_admin; reason + subtree flag; full reversal/soft delete service |
| `calculateBinaryBonus()` | POST binary-bonus/calculate | immutable/default calculation for selected user |
| `recalculateBinaryBonus()` | POST binary/recalculate | mutable current-run reconciliation with admin actor |
| `tree()` | GET tree | active subtree → BinaryNodeResource collection |

Financial/deletion methods are HIGH RISK. See [Services](./SERVICES.md) and [Wallet](./WALLET.md).

### `App\Http\Controllers\Api\Admin\BonusController`

Path: `app/Http/Controllers/Api/Admin/BonusController.php`.

- `index()` GET `/admin/bonuses`: visible active-recipient/source bonuses; filters type/status/search, paginated resource.
- `calculateBinary()` POST `/admin/bonuses/binary/calculate`: optional active user ID; absent → iterates role=user active with package; calls calculate, returns count/resources.
- `recalculateAllBinary()` POST `/admin/bonuses/binary/recalculate` and `/recalculate`: mutable BonusService all-partner recalc + audit.
- `update()` PATCH `/{bonus}`: amount>0, reason; `BonusAdminAdjustmentService` (service asserts super_admin), resource.
- `destroy()` DELETE: reason; service reverses/voids (super_admin assertion).

### `App\Http\Controllers\Api\Admin\TransactionController`

Path: `app/Http/Controllers/Api/Admin/TransactionController.php`.

- `index()` GET `/admin/transactions`: visible active-user ledger; user/type/date/search filters, summary turnover/credited/paid/pending/deferred deposit, paginated.
- `update()` PATCH transaction: `UpdateWalletTransactionAmountRequest` (admin/super_admin), AdminWalletTransactionService.
- `destroy()` DELETE: `DeleteWalletTransactionRequest` (super_admin), void service.

Route-level read permission includes accountant; FormRequests narrow write authorization.

### `App\Http\Controllers\Api\Admin\WithdrawalController`

Path: `app/Http/Controllers/Api/Admin/WithdrawalController.php`.

- `index()` GET `/admin/withdrawals`: active-user requests with profile/wallet, paginated; read permission.
- `approve()` PATCH `/{withdrawal}/approve`: manage permission accountant/super_admin; WithdrawalService approve.
- `reject()` PATCH reject: optional reason max1000; service restores funds.

### `App\Http\Controllers\Api\Admin\OrderController`

Path: `app/Http/Controllers/Api/Admin/OrderController.php`.

- `index()` GET `/admin/orders`: active-user non-voided orders; search/status/payment filter, pagination.
- `show()` GET order: rejects voided/inactive owner 404.
- `status()` PATCH status: pending/confirmed/cancelled/completed/shipped. Cancellation terminal, restores product stock once; does not void product-order PV.
- `paymentStatus()` PATCH: unpaid/pending/paid/failed/refunded/cancelled; lock row; first transition to paid confirms/pays and calls `PackageAutoUpgradeFromPaidOrdersService`.

Read versus manage permissions separated.

### `App\Http\Controllers\Api\Admin\PackageController`

Path: `app/Http/Controllers/Api/Admin/PackageController.php`.

- `index()` all packages paginated.
- `store()`/`update()` validate unique code/slug, nonnegative money/PV/percents/order and flags; fills activity/turnover fallbacks; forces BUSINESS inactive. Direct CRUD, no package effects on existing users. Write permission super_admin.

### `App\Http\Controllers\Api\Admin\ProductController`

Path: `app/Http/Controllers/Api/Admin/ProductController.php`.

- `index/show`: all products/resources.
- `store/update`: name/unique SKU/price/stock/status/deposit/image max5MB/meta; category stored in metadata; supplied `pv` is discarded and recalculated from changed price; public disk image replace/remove.
- `destroy`: hard delete product; order item FK nulls product. Write permission super_admin.

### `App\Http\Controllers\Api\Admin\NewsController`

Path: `app/Http/Controllers/Api/Admin/NewsController.php`.

List/show plus create/update/delete. Inline validation includes aliases summary→excerpt/body→content, unique slug, draft/published/archived, jpg/png/webp max5MB. Generates slug/publication time, synchronizes status/is_published and manages public `news` image. Delete model does not explicitly delete image.

### `App\Http\Controllers\Api\Admin\FaqController`

Path: `app/Http/Controllers/Api/Admin/FaqController.php`. List all paginated; CRUD category/question/answer/order/status/active/meta. Store requires core text, update uses sometimes. Direct Eloquent; delete hard. Write super_admin.

### `App\Http\Controllers\Api\Admin\ForgotPasswordRequestController`

Path: `app/Http/Controllers/Api/Admin/ForgotPasswordRequestController.php`.

- `index/show`: filter pending/all/search, loaded user/resolver.
- `resetPassword()`: min8 confirmed; pending check + row/user locks; update hash, revoke all tokens, resolve row, admin log.
- `cancel()`: pending only; optional note, resolve/cancel metadata. Permission support/admin/super_admin.

### `App\Http\Controllers\Api\Admin\StructureController`

Path: `app/Http/Controllers/Api/Admin/StructureController.php`.

- `__invoke()` GET `/admin/structure`: optional selected root, active subtree, recursive tree/flat list, branch volumes/stats. Uses DashboardBranchVolumeService; read-only.
- `rootOrphans()` GET `/admin/structure/root-orphans`: lists active accounts without active binary placement/root-like records with extensive filters/sort/offset pagination. Read-only.

### `App\Http\Controllers\Api\Admin\StatusController`

Path: `app/Http/Controllers/Api/Admin/StatusController.php`. GET `/admin/statuses`: takes `StatusService::publicStatuses()`, adds count of role=user accounts currently at each status. Read-only.

### `App\Http\Controllers\Api\Admin\ReportController`

Path: `app/Http/Controllers/Api/Admin/ReportController.php`. `summary()` обслуживает GET `/admin/reports/summary`, accountant/super_admin. Aggregates partner users, non-deposit/non-void orders, visible partner bonuses, withdrawals, six monthly chart and package/PV summaries. Read-only; totals are report queries, not ledger reconciliation.

### `App\Http\Controllers\Api\Admin\SettingsController`

Path: `app/Http/Controllers/Api/Admin/SettingsController.php`.

- `index()` ensures default company/withdrawal/contact/legal settings via `firstOrCreate`, then returns key map + resources. GET therefore can write missing defaults.
- `update()` accepts optional `settings` map or all request fields, ignores invalid key names, upserts JSON values/type/group, returns index. Super_admin only.

### `App\Http\Controllers\Api\Admin\PaymentReadinessController`

Path: `app/Http/Controllers/Api/Admin/PaymentReadinessController.php`. GET `/admin/payment-readiness`, super_admin. Read-only except LegalSettings may ensure defaults. Checks APP_URL HTTPS, TipTopPay config, required legal requisites, product image/stock, order payment columns, callback route registration and delivery rules. It does not contact TipTopPay.

## FormRequest catalog

| Request | Endpoint(s) | `authorize()` | Rules/business constraints |
|---|---|---|---|
| `LoginRequest` | login | true | login or email + password |
| `RegisterRequest` | register | true | identity unique among nondeleted/active phone, password, sponsor active role, L/R, package exists; aliases normalized |
| `StoreOrderRequest` | order create | true | items/products/quantity, 3 strategies, recipient/phone/city/address required |
| `StoreWithdrawalRequest` | withdrawal create | true | amount>0, `ip_account|card_account`, optional details; method aliases |
| `StorePartnerRequest` | admin create | super_admin | identity/password, eligible sponsor/branch, package, optional referral flag/role |
| `UpdateWalletTransactionAmountRequest` | admin tx patch | admin/super_admin | amount≥0.01, reason 3..500 |
| `DeleteWalletTransactionRequest` | admin tx delete | super_admin | reason 3..500 |
| `StoreSupportTicketRequest` | dashboard ticket | true | optional subject/category/priority, message or file, allowlist max5MB |
| `UpdateSupportTicketRequest` | dashboard ticket PUT | true | required subject/message |
| `SendSupportMessageRequest` | dashboard message | true | message or file, allowlist max5MB |
| `ReplySupportTicketRequest` | staff reply | true | reply alias, message/file, optional valid status |
| `UpdateSupportTicketStatusRequest` | staff status | true | one of six statuses |
| `AssignSupportTicketRequest` | staff assign | true | optional support/admin/super_admin assignee |

`authorize=true` в support requests полагается на route middleware/owner checks; это ожидаемая двухуровневая схема текущего кода.

## JsonResource catalog

Все paths имеют вид `app/Http/Resources/<Class>.php`.

| Resource | Model/data | Main consumers / representation role |
|---|---|---|
| `BinaryNodeResource` | BinaryNode + user/package/tree | partner/admin binary tree nodes |
| `BonusTransactionResource` | BonusTransaction | dashboard/admin bonus ledger, sources and metadata |
| `FaqResource` | Faq | localized public/admin FAQ fields |
| `ForgotPasswordRequestResource` | ForgotPasswordRequest | staff password request with user/resolver |
| `NewsResource` | News | localized public/admin news and image URL |
| `OrderItemResource` | OrderItem | item snapshot/product/package, unit and totals |
| `OrderResource` | Order + items/user | user/admin order, delivery and payment fields |
| `PackageResource` | Package | localized package rules plus current-user action availability |
| `PartnerTransferResource` | PartnerTransfer | sender/recipient and linked transfer metadata |
| `ProductResource` | Product | localized catalog, stock, deposit flag and informational PV |
| `SupportMessageAttachmentResource` | SupportMessageAttachment | attachment metadata/download identity, not raw file |
| `SupportTicketMessageResource` | SupportTicketMessage | sender, staff flag, content and attachments |
| `SupportTicketResource` | SupportTicket | ticket state, owner/assignee and conversation |
| `SystemSettingResource` | SystemSetting | typed/JSON setting value and metadata |
| `TransferPartnerResource` | searched User | minimal safe recipient search result |
| `UserProfileResource` | UserProfile | extended identity/location/avatar |
| `UserResource` | User + relations | auth/dashboard/admin partner representation and compatibility fields |
| `WalletResource` | Wallet | type/currency/balance/hold/available/status |
| `WalletTransactionResource` | WalletTransaction | visible ledger snapshots/source/metadata/labels |
| `WithdrawalRequestResource` | WithdrawalRequest | request amounts, method, status and processing fields |

Resources локализуют labels/content через `LocalizedValue`/`SystemLabel` и часто отдают snake_case вместе с compatibility aliases, которые нормализует `resources/js/safi/lib/api.ts`.

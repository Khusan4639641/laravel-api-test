# API routes

[Главная](./PROJECT_DOCUMENTATION.md) · [Controllers](./CONTROLLERS.md) · [Architecture](./ARCHITECTURE.md) · [Frontend](./FRONTEND.md)

Источник: фактический вывод `php artisan route:list --json` от 24.08.2026. Всего 172 route entries: 164 API (24 public, 140 Sanctum-protected) и 8 web/framework. Route aliases намеренно перечислены отдельными строками. Swagger/OpenAPI не найден.

Обозначения: `Sanctum + active` = `auth:sanctum` + `EnsureAccountActive`; permission — key из `config/role_permissions.php`. Детальный validation/service/model/response flow каждого controller: [CONTROLLERS.md](./CONTROLLERS.md).

## Public API

| Method | URI | Controller | Auth | Purpose |
|---|---|---|---|---|
| POST | `/api/auth/forgot-password/check-email` | `App\Http\Controllers\Api\Auth\ForgotPasswordController@checkEmail` | Public + throttle 6/min | Verify that email exists |
| POST | `/api/auth/forgot-password/request` | `App\Http\Controllers\Api\Auth\ForgotPasswordController@store` | Public + throttle 6/min | Create assisted password-reset request |
| POST | `/api/login` | `App\Http\Controllers\Api\AuthController@login` | Public | Login and issue Sanctum token |
| POST | `/api/payments/tiptoppay/cancel` | `App\Http\Controllers\Api\Payments\TipTopPayWebhookController@cancel` | Public | Process TipTopPay cancel callback |
| POST | `/api/payments/tiptoppay/check` | `App\Http\Controllers\Api\Payments\TipTopPayWebhookController@check` | Public | Process TipTopPay check callback |
| POST | `/api/payments/tiptoppay/confirm` | `App\Http\Controllers\Api\Payments\TipTopPayWebhookController@confirm` | Public | Process TipTopPay confirm callback |
| POST | `/api/payments/tiptoppay/fail` | `App\Http\Controllers\Api\Payments\TipTopPayWebhookController@fail` | Public | Process TipTopPay fail callback |
| POST | `/api/payments/tiptoppay/pay` | `App\Http\Controllers\Api\Payments\TipTopPayWebhookController@pay` | Public | Process TipTopPay pay callback |
| POST | `/api/payments/tiptoppay/refund` | `App\Http\Controllers\Api\Payments\TipTopPayWebhookController@refund` | Public | Process TipTopPay refund callback |
| GET/HEAD | `/api/payments/tiptoppay/status` | `App\Http\Controllers\Api\Payments\TipTopPayWebhookController@status` | Public | Return public TipTopPay readiness flags |
| GET/HEAD | `/api/products` | `App\Http\Controllers\Api\ProductController@index` | Public | List products |
| GET/HEAD | `/api/products/deposit` | `App\Http\Controllers\Api\ProductController@deposit` | Public | List active deposit-only products |
| GET/HEAD | `/api/products/{product}` | `App\Http\Controllers\Api\ProductController@show` | Public | Show products |
| GET/HEAD | `/api/public/faqs` | `App\Http\Controllers\Api\PublicApi\FaqController@index` | Public | List faqs |
| GET/HEAD | `/api/public/legal-settings` | `App\Http\Controllers\Api\PublicApi\LegalSettingsController` | Public | Return legal-settings |
| GET/HEAD | `/api/public/news` | `App\Http\Controllers\Api\PublicApi\NewsController@index` | Public | List news |
| GET/HEAD | `/api/public/news/{news}` | `App\Http\Controllers\Api\PublicApi\NewsController@show` | Public | Show news |
| GET/HEAD | `/api/public/packages` | `App\Http\Controllers\Api\PublicApi\PackageController@index` | Public | List active public MLM packages |
| GET/HEAD | `/api/public/products` | `App\Http\Controllers\Api\PublicApi\ProductController@index` | Public | List products |
| GET/HEAD | `/api/public/products/{product}` | `App\Http\Controllers\Api\PublicApi\ProductController@show` | Public | Show products |
| GET/HEAD | `/api/public/registration-packages` | `App\Http\Controllers\Api\PublicApi\PackageController@registration` | Public | List packages available to registration/admin-create UI |
| GET/HEAD | `/api/public/statuses` | `App\Http\Controllers\Api\PublicApi\StatusController@index` | Public | List statuses |
| GET/HEAD | `/api/ref/{user_id}/{branch}` | `App\Http\Controllers\Api\ReferralController@show` | Public | Validate referral link and preview placement |
| POST | `/api/register` | `App\Http\Controllers\Api\AuthController@register` | Public | Register partner and issue Sanctum token |

## Authenticated user API

| Method | URI | Controller | Auth | Purpose |
|---|---|---|---|---|
| POST | `/api/bonuses/binary/calculate` | `App\Http\Controllers\Api\BinaryBonusController` | Sanctum + active + `admin.bonuses.manage` | Calculate binary bonus |
| POST | `/api/deposit-products/{product}/purchase` | `App\Http\Controllers\Api\DepositPurchaseController@purchaseProduct` | Sanctum + active | Buy deposit product from deposit wallet |
| POST | `/api/deposits/purchase` | `App\Http\Controllers\Api\DepositPurchaseController` | Sanctum + active | Buy one or more deposit products |
| POST | `/api/logout` | `App\Http\Controllers\Api\AuthController@logout` | Sanctum + active | Delete current Sanctum token |
| GET/HEAD | `/api/me` | `App\Http\Controllers\Api\AuthController@me` | Sanctum + active | Return current user |
| GET/HEAD | `/api/me/permissions` | `App\Http\Controllers\Api\PermissionController` | Sanctum + active | Return role menu and frontend route permissions |
| GET/HEAD | `/api/orders` | `App\Http\Controllers\Api\OrderController@index` | Sanctum + active | List current user orders |
| POST | `/api/orders` | `App\Http\Controllers\Api\OrderController@store` | Sanctum + active | Create product order |
| GET/HEAD | `/api/orders/{order}` | `App\Http\Controllers\Api\OrderController@show` | Sanctum + active + controller owner check | Show owned order |
| POST | `/api/orders/{order}/payment/tiptoppay/intent` | `App\Http\Controllers\Api\Payments\TipTopPayIntentController` | Sanctum + active + owner/admin check | Create TipTopPay payment intent |
| POST | `/api/orders/{order}/payments/tiptoppay/intent` | `App\Http\Controllers\Api\Payments\TipTopPayIntentController` | Sanctum + active + owner/admin check | Create TipTopPay payment intent |
| POST | `/api/packages/{package}/activate` | `App\Http\Controllers\Api\PackageActivationController` | Sanctum + active | Activate starter package |
| POST | `/api/packages/{package}/upgrade` | `App\Http\Controllers\Api\PackageUpgradeController` | Sanctum + active | Upgrade current package |
| POST | `/api/payments/tiptoppay/package-intent` | `App\Http\Controllers\Api\Payments\TipTopPayIntentController@packageFromPayload` | Sanctum + active | Create package-payment TipTopPay intent from package ID |
| GET/HEAD | `/api/withdrawals` | `App\Http\Controllers\Api\WithdrawalController@index` | Sanctum + active | List withdrawal requests |
| POST | `/api/withdrawals` | `App\Http\Controllers\Api\WithdrawalController@store` | Sanctum + active | Create withdrawal request |

## Dashboard API

| Method | URI | Controller | Auth | Purpose |
|---|---|---|---|---|
| GET/HEAD | `/api/dashboard/bonuses` | `App\Http\Controllers\Api\Dashboard\BonusController` | Sanctum + active | List bonuses |
| GET/HEAD | `/api/dashboard/deposit-products` | `App\Http\Controllers\Api\Dashboard\DepositProductController` | Sanctum + active | Return deposit-products |
| GET/HEAD | `/api/dashboard/earnings-summary` | `App\Http\Controllers\Api\Dashboard\EarningsSummaryController` | Sanctum + active | Return earnings/wallet summary |
| GET/HEAD | `/api/dashboard/notifications` | `App\Http\Controllers\Api\Dashboard\NotificationController@index` | Sanctum + active | List visible database notifications |
| GET/HEAD | `/api/dashboard/orders` | `App\Http\Controllers\Api\Dashboard\OrderController` | Sanctum + active | Return orders |
| GET/HEAD | `/api/dashboard/overview` | `App\Http\Controllers\Api\Dashboard\OverviewController` | Sanctum + active | Return partner dashboard overview |
| POST | `/api/dashboard/package/{package}/payments/tiptoppay/intent` | `App\Http\Controllers\Api\Payments\TipTopPayIntentController@package` | Sanctum + active | Create TipTopPay payment intent |
| GET/HEAD | `/api/dashboard/packages` | `App\Http\Controllers\Api\Dashboard\PackageController` | Sanctum + active | List active packages with current-user availability |
| GET/HEAD | `/api/dashboard/partner-transfers` | `App\Http\Controllers\Api\Dashboard\PartnerTransferController@index` | Sanctum + active | List partner transfers |
| POST | `/api/dashboard/partner-transfers` | `App\Http\Controllers\Api\Dashboard\PartnerTransferController@store` | Sanctum + active | Transfer main balance to partner |
| GET/HEAD | `/api/dashboard/partners/search` | `App\Http\Controllers\Api\Dashboard\PartnerSearchController` | Sanctum + active | Search eligible partners/sponsors |
| GET/HEAD | `/api/dashboard/products` | `App\Http\Controllers\Api\Dashboard\ProductController` | Sanctum + active | Return products |
| GET/HEAD | `/api/dashboard/profile` | `App\Http\Controllers\Api\Dashboard\ProfileController` | Sanctum + active | Return user profile |
| PATCH | `/api/dashboard/profile/avatar` | `App\Http\Controllers\Api\Dashboard\ProfileController@avatar` | Sanctum + active | Upload/replace profile avatar |
| POST | `/api/dashboard/profile/avatar` | `App\Http\Controllers\Api\Dashboard\ProfileController@avatar` | Sanctum + active | Upload/replace profile avatar |
| GET/HEAD | `/api/dashboard/structure` | `App\Http\Controllers\Api\Dashboard\StructureController` | Sanctum + active | Return binary structure/tree and branch metrics |
| GET/HEAD | `/api/dashboard/support-tickets` | `App\Http\Controllers\Api\Dashboard\SupportTicketController@index` | Sanctum + active | List support tickets |
| POST | `/api/dashboard/support-tickets` | `App\Http\Controllers\Api\Dashboard\SupportTicketController@store` | Sanctum + active | Create support ticket |
| GET/HEAD | `/api/dashboard/support-tickets/{ticket}` | `App\Http\Controllers\Api\Dashboard\SupportTicketController@show` | Sanctum + active + owner(ticket) | Show support ticket |
| PUT | `/api/dashboard/support-tickets/{ticket}` | `App\Http\Controllers\Api\Dashboard\SupportTicketController@update` | Sanctum + active + owner(ticket) | Update support ticket |
| PATCH | `/api/dashboard/support-tickets/{ticket}/close` | `App\Http\Controllers\Api\Dashboard\SupportTicketController@close` | Sanctum + active + owner(ticket) | Close support ticket |
| POST | `/api/dashboard/support-tickets/{ticket}/messages` | `App\Http\Controllers\Api\Dashboard\SupportTicketController@message` | Sanctum + active + owner(ticket) | Send support message |
| GET/HEAD | `/api/dashboard/support/attachments/{attachment}/download` | `App\Http\Controllers\Api\Dashboard\SupportTicketController@download` | Sanctum + active + controller owner check | Download support attachment |
| GET/HEAD | `/api/dashboard/support/tickets` | `App\Http\Controllers\Api\Dashboard\SupportTicketController@index` | Sanctum + active | List support tickets |
| POST | `/api/dashboard/support/tickets` | `App\Http\Controllers\Api\Dashboard\SupportTicketController@store` | Sanctum + active | Create support ticket |
| GET/HEAD | `/api/dashboard/support/tickets/{ticket}` | `App\Http\Controllers\Api\Dashboard\SupportTicketController@show` | Sanctum + active + owner(ticket) | Show support ticket |
| POST | `/api/dashboard/support/tickets/{ticket}/close` | `App\Http\Controllers\Api\Dashboard\SupportTicketController@close` | Sanctum + active + owner(ticket) | Close support ticket |
| POST | `/api/dashboard/support/tickets/{ticket}/messages` | `App\Http\Controllers\Api\Dashboard\SupportTicketController@message` | Sanctum + active + owner(ticket) | Send support message |
| GET/HEAD | `/api/dashboard/transactions` | `App\Http\Controllers\Api\Dashboard\TransactionController` | Sanctum + active | List wallet transactions |
| GET/HEAD | `/api/dashboard/wallet/transfers` | `App\Http\Controllers\Api\Dashboard\PartnerTransferController@index` | Sanctum + active | List partner transfers |
| POST | `/api/dashboard/wallet/transfers` | `App\Http\Controllers\Api\Dashboard\PartnerTransferController@store` | Sanctum + active | Transfer main balance to partner |
| POST | `/api/dashboard/wallets/internal-transfer` | `App\Http\Controllers\Api\Dashboard\InternalWalletTransferController` | Sanctum + active + controller `admin|super_admin` | Transfer own main balance to deposit |
| GET/HEAD | `/api/dashboard/withdrawals` | `App\Http\Controllers\Api\Dashboard\WithdrawalController@index` | Sanctum + active | List withdrawal requests |
| POST | `/api/dashboard/withdrawals` | `App\Http\Controllers\Api\Dashboard\WithdrawalController@store` | Sanctum + active | Create withdrawal request |

## Support API

| Method | URI | Controller | Auth | Purpose |
|---|---|---|---|---|
| GET/HEAD | `/api/support/attachments/{attachment}/download` | `App\Http\Controllers\Api\Support\TicketController@download` | Sanctum + active + `support.manage` | Download support attachment |
| GET/HEAD | `/api/support/tickets` | `App\Http\Controllers\Api\Support\TicketController@index` | Sanctum + active + `support.manage` | List support tickets |
| GET/HEAD | `/api/support/tickets/{ticket}` | `App\Http\Controllers\Api\Support\TicketController@show` | Sanctum + active + `support.manage` | Show support ticket |
| PATCH | `/api/support/tickets/{ticket}/assign` | `App\Http\Controllers\Api\Support\TicketController@assign` | Sanctum + active + `support.manage` | Assign support ticket |
| POST | `/api/support/tickets/{ticket}/close` | `App\Http\Controllers\Api\Support\TicketController@close` | Sanctum + active + `support.manage` | Close support ticket |
| POST | `/api/support/tickets/{ticket}/messages` | `App\Http\Controllers\Api\Support\TicketController@reply` | Sanctum + active + `support.manage` | Send support message |
| POST | `/api/support/tickets/{ticket}/reopen` | `App\Http\Controllers\Api\Support\TicketController@reopen` | Sanctum + active + `support.manage` | Reopen support ticket |
| POST | `/api/support/tickets/{ticket}/reply` | `App\Http\Controllers\Api\Support\TicketController@reply` | Sanctum + active + `support.manage` | Reply to support ticket |
| PATCH | `/api/support/tickets/{ticket}/status` | `App\Http\Controllers\Api\Support\TicketController@status` | Sanctum + active + `support.manage` | Change support ticket status |

## Admin API

| Method | URI | Controller | Auth | Purpose |
|---|---|---|---|---|
| GET/HEAD | `/api/admin/bonuses` | `App\Http\Controllers\Api\Admin\BonusController@index` | Sanctum + active + `admin.read` | List bonuses |
| POST | `/api/admin/bonuses/binary/calculate` | `App\Http\Controllers\Api\Admin\BonusController@calculateBinary` | Sanctum + active + `admin.bonuses.manage` | Calculate binary bonus |
| POST | `/api/admin/bonuses/binary/recalculate` | `App\Http\Controllers\Api\Admin\BonusController@recalculateAllBinary` | Sanctum + active + `admin.bonuses.manage` | Recalculate binary bonus |
| POST | `/api/admin/bonuses/recalculate` | `App\Http\Controllers\Api\Admin\BonusController@recalculateAllBinary` | Sanctum + active + `admin.bonuses.manage` | Recalculate binary bonuses (alias) |
| PATCH | `/api/admin/bonuses/{bonus}` | `App\Http\Controllers\Api\Admin\BonusController@update` | Sanctum + active + `admin.bonuses.manage`; service requires super_admin | Edit bonus and linked wallet ledger |
| DELETE | `/api/admin/bonuses/{bonus}` | `App\Http\Controllers\Api\Admin\BonusController@destroy` | Sanctum + active + `admin.bonuses.manage`; service requires super_admin | Reverse/void bonus |
| GET/HEAD | `/api/admin/faqs` | `App\Http\Controllers\Api\Admin\FaqController@index` | Sanctum + active + `admin.read` | List faqs |
| POST | `/api/admin/faqs` | `App\Http\Controllers\Api\Admin\FaqController@store` | Sanctum + active + `admin.catalog.write` | Create faqs |
| PUT | `/api/admin/faqs/{faq}` | `App\Http\Controllers\Api\Admin\FaqController@update` | Sanctum + active + `admin.catalog.write` | Update faqs |
| DELETE | `/api/admin/faqs/{faq}` | `App\Http\Controllers\Api\Admin\FaqController@destroy` | Sanctum + active + `admin.catalog.write` | Delete faqs |
| GET/HEAD | `/api/admin/forgot-password-requests` | `App\Http\Controllers\Api\Admin\ForgotPasswordRequestController@index` | Sanctum + active + `admin.forgot_password.manage` | List password-reset requests |
| GET/HEAD | `/api/admin/forgot-password-requests/{forgotPasswordRequest}` | `App\Http\Controllers\Api\Admin\ForgotPasswordRequestController@show` | Sanctum + active + `admin.forgot_password.manage` | Show password-reset request |
| PATCH | `/api/admin/forgot-password-requests/{forgotPasswordRequest}/cancel` | `App\Http\Controllers\Api\Admin\ForgotPasswordRequestController@cancel` | Sanctum + active + `admin.forgot_password.manage` | Cancel password-reset request |
| POST | `/api/admin/forgot-password-requests/{forgotPasswordRequest}/reset-password` | `App\Http\Controllers\Api\Admin\ForgotPasswordRequestController@resetPassword` | Sanctum + active + `admin.forgot_password.manage` | Set new password and resolve request |
| GET/HEAD | `/api/admin/news` | `App\Http\Controllers\Api\Admin\NewsController@index` | Sanctum + active + `admin.read` | List news |
| POST | `/api/admin/news` | `App\Http\Controllers\Api\Admin\NewsController@store` | Sanctum + active + `admin.catalog.write` | Create news |
| GET/HEAD | `/api/admin/news/{news}` | `App\Http\Controllers\Api\Admin\NewsController@show` | Sanctum + active + `admin.read` | Show news |
| PUT | `/api/admin/news/{news}` | `App\Http\Controllers\Api\Admin\NewsController@update` | Sanctum + active + `admin.catalog.write` | Update news |
| DELETE | `/api/admin/news/{news}` | `App\Http\Controllers\Api\Admin\NewsController@destroy` | Sanctum + active + `admin.catalog.write` | Delete news |
| GET/HEAD | `/api/admin/orders` | `App\Http\Controllers\Api\Admin\OrderController@index` | Sanctum + active + `admin.orders.read` | List orders |
| GET/HEAD | `/api/admin/orders/{order}` | `App\Http\Controllers\Api\Admin\OrderController@show` | Sanctum + active + `admin.orders.read` | Show orders |
| PATCH | `/api/admin/orders/{order}/payment-status` | `App\Http\Controllers\Api\Admin\OrderController@paymentStatus` | Sanctum + active + `admin.orders.manage` | Change order payment status |
| PATCH | `/api/admin/orders/{order}/status` | `App\Http\Controllers\Api\Admin\OrderController@status` | Sanctum + active + `admin.orders.manage` | Change order fulfillment status |
| GET/HEAD | `/api/admin/overview` | `App\Http\Controllers\Api\Admin\OverviewController` | Sanctum + active + `admin.overview` | Return back-office overview metrics |
| GET/HEAD | `/api/admin/packages` | `App\Http\Controllers\Api\Admin\PackageController@index` | Sanctum + active + `admin.read` | List all package definitions |
| POST | `/api/admin/packages` | `App\Http\Controllers\Api\Admin\PackageController@store` | Sanctum + active + `admin.catalog.write` | Create package definition |
| PUT | `/api/admin/packages/{package}` | `App\Http\Controllers\Api\Admin\PackageController@update` | Sanctum + active + `admin.catalog.write` | Update package definition |
| POST | `/api/admin/partners` | `App\Http\Controllers\Api\Admin\PartnerController@store` | Sanctum + active + `admin.partners.create` | Create partner |
| GET/HEAD | `/api/admin/partners` | `App\Http\Controllers\Api\Admin\UserController@index` | Sanctum + active + `admin.read` | List partner accounts |
| POST | `/api/admin/partners/bulk-create` | `App\Http\Controllers\Api\Admin\PartnerController@bulkCreate` | Sanctum + active + `admin.partners.create` | Create partners in bulk |
| GET/HEAD | `/api/admin/partners/search` | `App\Http\Controllers\Api\Admin\UserController@search` | Sanctum + active + `admin.read` | Search eligible partners/sponsors |
| GET/HEAD | `/api/admin/partners/{user}` | `App\Http\Controllers\Api\Admin\PartnerController@show` | Sanctum + active + `admin.read` | Show partner |
| PUT | `/api/admin/partners/{user}` | `App\Http\Controllers\Api\Admin\PartnerController@update` | Sanctum + active + `admin.partners.manage` | Update partner |
| DELETE | `/api/admin/partners/{user}` | `App\Http\Controllers\Api\Admin\PartnerController@destroy` | Sanctum + active + `admin.partners.manage`; controller requires super_admin | Reverse and soft-delete partner |
| PATCH | `/api/admin/partners/{user}/balance` | `App\Http\Controllers\Api\Admin\PartnerController@balance` | Sanctum + active + `admin.partners.balance` | Set/adjust partner main balance |
| POST | `/api/admin/partners/{user}/binary-bonus/calculate` | `App\Http\Controllers\Api\Admin\PartnerController@calculateBinaryBonus` | Sanctum + active + `admin.bonuses.manage` | Calculate binary bonus |
| POST | `/api/admin/partners/{user}/binary/recalculate` | `App\Http\Controllers\Api\Admin\PartnerController@recalculateBinaryBonus` | Sanctum + active + `admin.bonuses.manage` | Recalculate binary bonus |
| PATCH | `/api/admin/partners/{user}/block` | `App\Http\Controllers\Api\Admin\PartnerController@block` | Sanctum + active + `admin.partners.manage` | Block partner and revoke tokens |
| POST | `/api/admin/partners/{user}/change-password` | `App\Http\Controllers\Api\Admin\PartnerController@changePassword` | Sanctum + active + `admin.partners.manage` | Change partner password |
| GET/HEAD | `/api/admin/partners/{user}/delete-preview` | `App\Http\Controllers\Api\Admin\PartnerController@deletePreview` | Sanctum + active + `admin.read`; controller requires super_admin | Preview partner deletion effects |
| PATCH | `/api/admin/partners/{user}/identity` | `App\Http\Controllers\Api\Admin\PartnerController@identity` | Sanctum + active + `admin.partners.identity` | Update partner identity |
| PATCH | `/api/admin/partners/{user}/note` | `App\Http\Controllers\Api\Admin\PartnerController@note` | Sanctum + active + `admin.partners.manage` | Update admin note |
| PATCH | `/api/admin/partners/{user}/package` | `App\Http\Controllers\Api\Admin\PartnerController@package` | Sanctum + active + `admin.partners.manage` | Assign partner package |
| PATCH | `/api/admin/partners/{user}/status` | `App\Http\Controllers\Api\Admin\PartnerController@status` | Sanctum + active + `admin.partners.manage` | Change partner status, optionally applying bonus effects |
| GET/HEAD | `/api/admin/partners/{user}/transactions` | `App\Http\Controllers\Api\Admin\PartnerController@transactions` | Sanctum + active + `admin.read` | List wallet transactions |
| GET/HEAD | `/api/admin/partners/{user}/tree` | `App\Http\Controllers\Api\Admin\PartnerController@tree` | Sanctum + active + `admin.read` | Return partner binary subtree |
| PATCH | `/api/admin/partners/{user}/unblock` | `App\Http\Controllers\Api\Admin\PartnerController@unblock` | Sanctum + active + `admin.partners.manage` | Unblock partner |
| GET/HEAD | `/api/admin/payment-readiness` | `App\Http\Controllers\Api\Admin\PaymentReadinessController` | Sanctum + active + `admin.settings` | Check payment/checkout production readiness |
| GET/HEAD | `/api/admin/products` | `App\Http\Controllers\Api\Admin\ProductController@index` | Sanctum + active + `admin.read` | List products |
| POST | `/api/admin/products` | `App\Http\Controllers\Api\Admin\ProductController@store` | Sanctum + active + `admin.catalog.write` | Create products |
| GET/HEAD | `/api/admin/products/{product}` | `App\Http\Controllers\Api\Admin\ProductController@show` | Sanctum + active + `admin.read` | Show products |
| PUT | `/api/admin/products/{product}` | `App\Http\Controllers\Api\Admin\ProductController@update` | Sanctum + active + `admin.catalog.write` | Update products |
| DELETE | `/api/admin/products/{product}` | `App\Http\Controllers\Api\Admin\ProductController@destroy` | Sanctum + active + `admin.catalog.write` | Delete products |
| GET/HEAD | `/api/admin/reports/summary` | `App\Http\Controllers\Api\Admin\ReportController@summary` | Sanctum + active + `admin.reports` | Return administrative financial/partner report |
| GET/HEAD | `/api/admin/settings` | `App\Http\Controllers\Api\Admin\SettingsController@index` | Sanctum + active + `admin.settings` | List settings |
| PUT | `/api/admin/settings` | `App\Http\Controllers\Api\Admin\SettingsController@update` | Sanctum + active + `admin.settings` | Update settings |
| GET/HEAD | `/api/admin/sponsors/search` | `App\Http\Controllers\Api\Admin\UserController@sponsorSearch` | Sanctum + active + `admin.read` | Search eligible partners/sponsors |
| GET/HEAD | `/api/admin/statuses` | `App\Http\Controllers\Api\Admin\StatusController` | Sanctum + active + `admin.read` | Return statuses |
| GET/HEAD | `/api/admin/structure` | `App\Http\Controllers\Api\Admin\StructureController` | Sanctum + active + `admin.read` | Return binary structure/tree and branch metrics |
| GET/HEAD | `/api/admin/structure/root-orphans` | `App\Http\Controllers\Api\Admin\StructureController@rootOrphans` | Sanctum + active + `admin.read` | List partners outside active binary placement |
| GET/HEAD | `/api/admin/support-tickets` | `App\Http\Controllers\Api\Support\TicketController@index` | Sanctum + active + `support.manage` | List support tickets |
| GET/HEAD | `/api/admin/support-tickets/{ticket}` | `App\Http\Controllers\Api\Support\TicketController@show` | Sanctum + active + `support.manage` | Show support ticket |
| PATCH | `/api/admin/support-tickets/{ticket}/assign` | `App\Http\Controllers\Api\Support\TicketController@assign` | Sanctum + active + `support.manage` | Assign support ticket |
| PATCH | `/api/admin/support-tickets/{ticket}/close` | `App\Http\Controllers\Api\Support\TicketController@close` | Sanctum + active + `support.manage` | Close support ticket |
| POST | `/api/admin/support-tickets/{ticket}/reply` | `App\Http\Controllers\Api\Support\TicketController@reply` | Sanctum + active + `support.manage` | Reply to support ticket |
| PATCH | `/api/admin/support-tickets/{ticket}/reply` | `App\Http\Controllers\Api\Support\TicketController@reply` | Sanctum + active + `support.manage` | Reply to support ticket |
| PATCH | `/api/admin/support-tickets/{ticket}/status` | `App\Http\Controllers\Api\Support\TicketController@status` | Sanctum + active + `support.manage` | Change support ticket status |
| GET/HEAD | `/api/admin/support/attachments/{attachment}/download` | `App\Http\Controllers\Api\Support\TicketController@download` | Sanctum + active + `support.manage` | Download support attachment |
| GET/HEAD | `/api/admin/support/tickets` | `App\Http\Controllers\Api\Support\TicketController@index` | Sanctum + active + `support.manage` | List support tickets |
| GET/HEAD | `/api/admin/support/tickets/{ticket}` | `App\Http\Controllers\Api\Support\TicketController@show` | Sanctum + active + `support.manage` | Show support ticket |
| POST | `/api/admin/support/tickets/{ticket}/close` | `App\Http\Controllers\Api\Support\TicketController@close` | Sanctum + active + `support.manage` | Close support ticket |
| POST | `/api/admin/support/tickets/{ticket}/messages` | `App\Http\Controllers\Api\Support\TicketController@reply` | Sanctum + active + `support.manage` | Send support message |
| POST | `/api/admin/support/tickets/{ticket}/reopen` | `App\Http\Controllers\Api\Support\TicketController@reopen` | Sanctum + active + `support.manage` | Reopen support ticket |
| GET/HEAD | `/api/admin/transactions` | `App\Http\Controllers\Api\Admin\TransactionController@index` | Sanctum + active + `admin.transactions.read` | List wallet transactions |
| PATCH | `/api/admin/transactions/{transaction}` | `App\Http\Controllers\Api\Admin\TransactionController@update` | Sanctum + active + `admin.transactions.read`; FormRequest admin/super_admin | Edit wallet transaction amount |
| DELETE | `/api/admin/transactions/{transaction}` | `App\Http\Controllers\Api\Admin\TransactionController@destroy` | Sanctum + active + `admin.transactions.read`; FormRequest super_admin | Void wallet transaction |
| GET/HEAD | `/api/admin/users` | `App\Http\Controllers\Api\Admin\UserController@index` | Sanctum + active + `admin.read` | List users |
| GET/HEAD | `/api/admin/users/{user}` | `App\Http\Controllers\Api\Admin\UserController@show` | Sanctum + active + `admin.read` | Show users |
| GET/HEAD | `/api/admin/withdrawals` | `App\Http\Controllers\Api\Admin\WithdrawalController@index` | Sanctum + active + `admin.withdrawals.read` | List withdrawal requests |
| PATCH | `/api/admin/withdrawals/{withdrawal}/approve` | `App\Http\Controllers\Api\Admin\WithdrawalController@approve` | Sanctum + active + `admin.withdrawals.manage` | Approve held withdrawal |
| PATCH | `/api/admin/withdrawals/{withdrawal}/reject` | `App\Http\Controllers\Api\Admin\WithdrawalController@reject` | Sanctum + active + `admin.withdrawals.manage` | Reject withdrawal and restore balance |

## Web/framework routes

| Method | URI | Controller | Auth | Purpose |
|---|---|---|---|---|
| GET/HEAD/POST/PUT/PATCH/DELETE/OPTIONS | `/register` | `Illuminate\Routing\RedirectController` | Web | Redirect registration alias to /login |
| GET/HEAD/POST/PUT/PATCH/DELETE/OPTIONS | `/registration` | `Illuminate\Routing\RedirectController` | Web | Redirect registration alias to /login |
| GET/HEAD | `/sanctum/csrf-cookie` | `Laravel\Sanctum\Http\Controllers\CsrfCookieController@show` | Web | Sanctum CSRF cookie endpoint |
| GET/HEAD/POST/PUT/PATCH/DELETE/OPTIONS | `/sign-up` | `Illuminate\Routing\RedirectController` | Web | Redirect registration alias to /login |
| GET/HEAD | `/storage/{path}` | `Closure` | Public | Serve local storage file route |
| PUT | `/storage/{path}` | `Closure` | Public | Serve/update local storage route registered by framework |
| GET/HEAD | `/up` | `Closure` | Public | Health check |
| GET/HEAD | `/{any?}` | `Illuminate\Routing\ViewController` | Web | Serve React SPA catch-all |

## Response conventions

- Validation: HTTP 422 с Laravel error bag; authorization: 401/403; missing/inaccessible owner resources часто 404.
- List endpoints с pagination обычно возвращают `data`, named alias, `links`, `meta`; compatibility endpoints могут возвращать unpaginated collection.
- Resources локализуют labels/content по `Accept-Language` и иногда публикуют snake_case + camelCase aliases.
- TipTopPay callbacks возвращают provider-compatible JSON из service; они публичны и должны проверяться HMAC secret.

## Groups not found

GraphQL, SOAP, versioned `/api/v1`, Swagger/OpenAPI и websocket/broadcast routes — **Не найдено в текущей реализации**.

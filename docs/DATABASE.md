# Database и Eloquent models

[Главная](./PROJECT_DOCUMENTATION.md) · [Architecture](./ARCHITECTURE.md) · [MLM](./MLM.md) · [Wallet](./WALLET.md) · [Services](./SERVICES.md)

## Общая карта

50 migration files создают 38 таблиц приложения/framework. Laravel также ведёт служебную таблицу `migrations`, создаваемую migrator, поэтому полностью migrated DB обычно содержит минимум 39 таблиц до runtime/vendor extensions.

```text
users ──1:1── user_profiles
  │ ├─ sponsor_id ───────────────→ users
  │ ├─ current_package_id ───────→ packages
  │ ├─1:1 binary_nodes ─ parent → binary_nodes
  │ ├─1:N wallets ─1:N wallet_transactions
  │ ├─1:N pv_transactions (buyer/upline)
  │ ├─1:N bonus_transactions
  │ ├─1:N orders ─1:N order_items ─→ products/packages
  │ │                    └ payments morph → orders/packages
  │ ├─1:N withdrawal_requests
  │ ├─1:N user_status_bonuses ─→ status_bonus_definitions
  │ ├─1:N user_x2_bonuses ─────→ x2_bonus_definitions
  │ └─1:N support_tickets ─1:N messages ─1:N attachments
  │
  └─ binary_bonus_runs ─1:N calculations
          └─→ bonus_transactions ─→ wallet_transactions
```

## Business tables

### `users`

Purpose: authentication, sponsor graph, package/status/PV cache и account management. PK `id`.

FK: nullable `sponsor_id→users` null on delete; `current_package_id→packages` null on delete; `deleted_by→users` null on delete.

Important fields: `login` unique, email unique, password/token fields, role, MLM `status`, `account_status`, package, left/right/remaining/total PV, admin note/avatar, soft-delete and deletion metadata.

Writers: registration, package/PV/status services, admin partner management, deletion/recalculation commands. Readers: virtually all API/services. SoftDeletes; password/remember token hidden.

### `user_profiles`

PK `id`, unique FK `user_id→users` cascade. Name parts, phone index, birth/country/city/address, avatar, metadata. Written registration/profile/admin identity; read resources/search/support/admin. Phone uniqueness for active accounts enforced by validation, not DB unique.

### `packages`

PK; unique code/slug. Price, `pv`, activity/turnover PV, referral/binary percent, translations, status/active/upgrade flags. Written seeder/admin catalog; read package/MLM/payment/dashboard. See [MLM package system](./MLM.md#package-system).

### `binary_nodes`

PK; unique `user_id→users`; self FK `parent_id` null on delete; unique `(parent_id,position)`. Position L/R, depth, indexed materialized `path`, `is_active`, soft delete/deletion audit. Written tree registration/demo/deletion; read structure, PV, binary, X2. `deleted_by` FK.

### `pv_transactions`

PK; `buyer_id`, `upline_id`, nullable `source_order_id`, `voided_by`. Source, branch L/R, decimal PV, bonusable flag, metadata, void timestamp. Written `PvService`; voided deletion/rollback; read branch volume/binary/reconciliation. Indices buyer/source and upline/source.

### `binary_bonus_runs`

PK; user FK; nullable bonus transaction FK. Period/status, weak/used/carry PV, amount/pending amount, metadata. Unique `(user_id,period_start,period_end)` (migration 20.08.2026). Written Bonus/Scheduled/rollback services; read bonus calculation/dashboard/admin.

### `binary_bonus_calculations`

PK; run/user/nullable bonus FKs. Snapshot left/right/weak/used/carry, money base, percent, total/main/deposit amounts, metadata. Append-only calculation audit under normal/recalc flows; rollback may delete/restore.

### `bonus_transactions`

PK; user FK; nullable wallet tx/source user/source order FKs. Bonus type, amount, PV fields, status, metadata, calculated timestamp. Written Bonus/Status/X2/admin/rollback/deletion; read dashboard/admin/reports/notifications. Semantic ledger; [wallet ledger details](./WALLET.md#bonus_transactions).

### `wallets`

PK; user FK cascade; unique `(user_id,type)`. Currency, balance, hold, status. Written all financial services/admin/deletion; read dashboard/reports/validation.

### `wallet_transactions`

PK; wallet/user FKs cascade; type/direction/amount/before/after/status/affects_balance; nullable morph `source_type/source_id`; description/metadata. Written WalletService and specialized financial/admin/rollback services; read dashboard/admin/reports/notification filtering. Core monetary audit trail.

### `withdrawal_requests`

PK; user/wallet FKs cascade. Amount/fee/net/currency, status, payment method/details, payout days, admin comment, processed timestamp. Written WithdrawalService/deletion; read user/admin/report. Current fee=0.

### `status_bonus_definitions`

PK; unique status code. Threshold, reward type/text, amount/cash/compensation, currency, flags/order. Seeder/repair writes; public/status services read.

### `user_status_bonuses`

PK; FKs user/definition, nullable bonus. Status/amount/reward/awarded/meta. Unique `(user_id,definition)` is once-only marker. Written StatusBonusService/admin/delete/repair.

### `x2_bonus_definitions`

PK; unique code. Required status/count, reward/amount/flags/order. Seeder/admin DB only; X2 reads.

### `user_x2_bonuses`

PK; FKs user/definition, nullable bonus. Code, qualified count, reward/amount/date/meta. Unique `(user_id,definition)`. Written X2/delete.

### `products`

PK; unique nullable SKU. Names/descriptions/translations, price, informational PV, stock/reserved, status/deposit flag, image path, metadata. Admin/seeder writes; public/dashboard/order/payment reads. Actual regular order turnover uses price/500, not column `pv`.

### `orders`

PK; user FK cascade; unique order number. Order/payment status, strategy/provider/external/transaction IDs, paid time/meta, monetary split/totals/PV, shipping/delivery/comment/metadata. Written checkout/deposit/payment/admin/deletion; read dashboard/admin/reports. Status conventions evolved from initial `new/pending` to current service values.

### `order_items`

PK; order FK cascade; nullable product/package FKs null on delete. Quantity, price/PV totals, product name and snapshot. Checkout/deposit creates; resources/payment/stock admin reads.

### `payments`

PK; user FK; nullable morph payable; unique external ID. Type order/package, provider, amount/currency/status/description/payload/provider response/paid/failed times. TipTopPay writes/reads. No model FK for morph at DB level.

### `partner_transfers`

PK; unique UUID; sender/recipient user FKs; nullable sender/recipient wallet tx FKs. Amount/currency/status/comment/idempotency/meta. Unique `(sender_user_id,idempotency_key)`. PartnerTransferService writes; dashboard/rollback reads.

### `admin_action_logs`

PK; nullable admin/target user FKs. Action/reason/JSON metadata/timestamps. Written registration password requests, partner admin, bonus batches/deletion/rollback/status actions. Operational audit, not immutable by DB constraint.

### `transaction_admin_audits`

PK; nullable wallet transaction FK, admin FK. Action, old/new amount/payload, mandatory reason. Written AdminWalletTransactionService; read forensic/admin tooling (dedicated API not found).

### `forgot_password_requests`

PK; nullable user/resolver FKs. Email/phone/status, request/resolve time, IP/user agent/admin note. Written public forgotten-password and staff controller; read staff UI.

### `news`

PK; optional unique slug. Localized title/category/excerpt/content, image, publication flags/time/order/meta. Seeder/admin writes; public/dashboard reads.

### `faqs`

PK. Localized category/question/answer, order/status/active/meta. Seeder/admin writes; public reads.

### `system_settings`

PK; unique key. JSON value/type/group/description. Admin Settings and LegalSettings ensure/write; public legal endpoint reads allowlisted keys.

### `support_tickets`

PK; nullable owner/assignee/closer user FKs. Subject/category/original message/status/priority/admin reply/timestamps/meta; soft deletes. Support services/controllers write/read.

### `support_ticket_messages`

PK; ticket FK cascade; nullable user; sender role/message/staff flag. Support chat writes; resources read.

### `support_message_attachments`

PK; message FK cascade. Original name, private path, MIME, size, disk. Attachment storage writes/reads; file itself находится filesystem.

### `notifications`

UUID PK; polymorphic notifiable, class type, JSON data, read/timestamps. Laravel database notification channel writes; dashboard and admin mutation services read/update/delete.

## Framework/infrastructure tables

| Table | PK/FK/fields | Writer/reader |
|---|---|---|
| `personal_access_tokens` | id, tokenable morph, unique hash token, abilities/usage/expiry | Sanctum login/logout/auth |
| `password_reset_tokens` | email PK, token/time | standard broker schema; current UI uses custom request table |
| `sessions` | string id PK, nullable user, IP/agent/payload/activity | database session driver |
| `cache` | key PK, value/expiration | Laravel cache |
| `cache_locks` | key PK, owner/expiration | scheduler/domain cache locks |
| `jobs` | id, queue/payload/attempt/reservation times | database queue driver; business jobs not found |
| `job_batches` | string id, counts/options/times | Laravel bus batches; no business usage found |
| `failed_jobs` | id/UUID, connection/queue/payload/exception/time | failed queue storage |

## Eloquent models

Все модели используют convention table, кроме `News::$table='news'`. Fillable задан Laravel `#[Fillable]` attribute; `$guarded` overrides не найдены. Custom observers/events/accessors/mutators не найдены.

| Model | Table | Fillable (сокращённо без timestamps) | Casts | Relations/scopes/methods | Main responsibility |
|---|---|---|---|---|---|
| `AdminActionLog` | admin_action_logs | admin/target/action/reason/meta | metadata array | admin,targetUser | admin audit |
| `BinaryBonusCalculation` | binary_bonus_calculations | run/user/bonus, all PV/money/meta | decimals, array | run,user,bonus | calculation snapshot |
| `BinaryBonusRun` | binary_bonus_runs | user/bonus/status/period/PV/amount/meta | datetime, decimals, array | user,bonus,calculations | period consumption/carry |
| `BinaryNode` | binary_nodes | user/parent/position/depth/path/active/delete audit | int,bool,datetime,array | user,parent,children,left/right; SoftDeletes | tree placement |
| `BonusTransaction` | bonus_transactions | recipient/primary/source/type/amount/PV/status/meta/time | decimals,array,datetime | user,wallet tx,source user/order | semantic bonus ledger |
| `Faq` | faqs | content/order/status/meta/translations | bool,int,arrays | — | localized FAQ |
| `ForgotPasswordRequest` | forgot_password_requests | user/identity/status/times/request audit | datetimes | user,resolvedBy; status constants | assisted reset request |
| `News` | news | content/publication/meta/translations | bool,datetime,int,arrays | — | localized news |
| `Order` | orders | identity/status/payment/totals/delivery/meta | decimals,datetime,arrays | user,items,payments; `withoutDepositPurchases`, `isDepositPurchase`, `hasDepositProducts`, label | checkout/payment aggregate |
| `OrderItem` | order_items | order/product/package/quantity/snapshots | int,decimals,array | order,product,package | immutable-ish item snapshot |
| `Package` | packages | code/content/price/PV/percents/flags/translations | decimals,int,bool,arrays | users,orderItems; active scopes; activity/turnover/volume methods | MLM package rules/data |
| `PartnerTransfer` | partner_transfers | UUID/users/amount/tx/comment/idempotency/meta | decimal,array | sender,recipient,transactions | transfer aggregate |
| `Payment` | payments | user/morph/type/provider/external/amount/status/payload/times | decimal,arrays,datetimes | user,payable; status/type constants | provider payment state |
| `Product` | products | content/price/PV/stock/status/deposit/image/meta/translations | decimal,int,bool,arrays | orderItems; `turnoverPv`, static price conversion | catalog/stock |
| `PvTransaction` | pv_transactions | buyer/upline/order/source/branch/PV/bonusable/void/meta | decimal,bool,datetime,array | buyer,upline,voider,order | PV event ledger |
| `StatusBonusDefinition` | status_bonus_definitions | code/name/threshold/reward/amount/flags/order | decimals,bool,int | userBonuses | status reward rule |
| `SupportMessageAttachment` | support_message_attachments | message/name/path/MIME/size/disk | default | message | stored file descriptor |
| `SupportTicket` | support_tickets | owner/assignee/content/status/times/meta | datetimes,array | user,assignedTo,closedBy,messages; `isClosed`; SoftDeletes | support aggregate |
| `SupportTicketMessage` | support_ticket_messages | ticket/user/role/message/staff | bool | ticket,user,attachments | chat entry |
| `SystemSetting` | system_settings | key/value/type/group/description | value array | — | runtime/legal settings |
| `TransactionAdminAudit` | transaction_admin_audits | tx/admin/action/old/new/reason | decimals,arrays | transaction,admin | transaction mutation audit |
| `User` | users | identity/sponsor/package/status/role/PV/delete audit | verify time/password hash, decimals,arrays,datetime | sponsor/referrals/package/profile/node/wallets/orders/payments/bonuses/withdrawals/status/X2/tx/support | auth + MLM partner |
| `UserProfile` | user_profiles | names/phone/location/avatar/meta | date,array | user | extended identity |
| `UserStatusBonus` | user_status_bonuses | user/definition/bonus/code/amount/reward/time/meta | decimal,datetime,array | user,definition,bonus | once-only status award |
| `UserX2Bonus` | user_x2_bonuses | user/definition/bonus/code/count/amount/reward/time/meta | int,decimal,datetime,array | user,definition,bonus | once-only X2 award |
| `Wallet` | wallets | user/type/currency/balance/hold/status | decimals | user,transactions,withdrawals | balance aggregate |
| `WalletTransaction` | wallet_transactions | wallet/user/type/direction/amount/snapshots/status/effect/source/meta | decimals,bool,array | wallet,user,source,adminAudits; `visible` | money audit ledger |
| `WithdrawalRequest` | withdrawal_requests | user/wallet/amount/fee/net/status/method/details/process | decimals,array,int,datetime | user,wallet | withdrawal state |
| `X2BonusDefinition` | x2_bonus_definitions | code/required status/count/reward/amount/flags/order | int,decimal,bool | userBonuses | X2 rule |

### Exact fillable and casts

Все paths: `app/Models/<Model>.php`. Модели используют `#[Fillable([...])]`; явного `$guarded` override нет, поэтому Laravel сохраняет default `['*']`, а массовое назначение разрешается указанным allowlist. Во всех effective casts также присутствует implicit `id:int`; ниже перечислены business casts (и `deleted_at` от SoftDeletes), чтобы не повторять `id` 29 раз.

- `AdminActionLog` — fillable: `admin_id,target_user_id,action,reason,metadata`; casts: `metadata:array`.
- `BinaryBonusCalculation` — fillable: `binary_bonus_run_id,user_id,bonus_transaction_id,left_pv,right_pv,weak_leg_pv,used_left_pv,used_right_pv,carry_left_pv,carry_right_pv,money_base_amount,binary_percent,bonus_amount,main_amount,deposit_amount,metadata`; casts: all PV/money/percent fields `decimal:2`, `metadata:array`.
- `BinaryBonusRun` — fillable: `user_id,bonus_transaction_id,status,period_start,period_end,weak_leg_pv,used_left_pv,used_right_pv,carry_left_pv,carry_right_pv,amount,pending_amount,metadata`; casts: periods `datetime`, PV/amount fields `decimal:2`, metadata array.
- `BinaryNode` — fillable: `user_id,parent_id,position,depth,path,is_active,deleted_by,deleted_reason,deleted_meta`; casts: `deleted_at:datetime`, `depth:integer`, `is_active:boolean`, `deleted_meta:array`.
- `BonusTransaction` — fillable: `user_id,wallet_transaction_id,source_user_id,source_order_id,bonus_type,amount,left_pv,right_pv,matched_pv,status,metadata,calculated_at`; casts: amount/PV `decimal:2`, metadata array, calculated_at datetime.
- `Faq` — fillable: `category,question,answer,sort_order,status,is_active,metadata,category_translations,question_translations,answer_translations`; casts: active boolean, order integer, metadata/translations arrays.
- `ForgotPasswordRequest` — fillable: `user_id,email,phone,status,requested_at,resolved_at,resolved_by,ip_address,user_agent,admin_note`; casts: requested/resolved datetime.
- `News` — fillable: `title,slug,category,excerpt,content,image_url,status,is_published,published_at,sort_order,metadata,title_translations,category_translations,excerpt_translations,content_translations`; casts: published boolean/datetime, order integer, metadata/translations arrays.
- `Order` — fillable: `user_id,order_number,status,payment_status,payment_strategy,payment_provider,payment_external_id,payment_transaction_id,paid_at,payment_meta,subtotal_amount,discount_amount,total_amount,card_amount,deposit_amount,total_pv,shipping_address,recipient_name,phone,city,delivery_address,comment,metadata`; casts: totals/PV decimal:2, paid_at datetime, payment_meta/shipping_address/metadata arrays.
- `OrderItem` — fillable: `order_id,product_id,product_name,package_id,quantity,unit_price,total_price,unit_pv,total_pv,item_snapshot`; casts: quantity integer, price/PV decimal:2, snapshot array.
- `Package` — fillable: `code,name,slug,description,price,pv,activity_pv,turnover_pv,referral_percent,binary_percent,sort_order,status,is_active,is_upgradeable,name_translations,description_translations`; casts: price/PV/percents decimal:2, order integer, flags boolean, translations arrays.
- `PartnerTransfer` — fillable: `uuid,sender_user_id,recipient_user_id,amount,currency,status,sender_transaction_id,recipient_transaction_id,comment,idempotency_key,meta`; casts: amount decimal:2, meta array.
- `Payment` — fillable: `user_id,payable_type,payable_id,type,provider,external_id,amount,currency,status,description,payload,provider_response,paid_at,failed_at`; casts: amount decimal:2, payload/provider_response arrays, paid/failed datetime.
- `Product` — fillable: `name,sku,description,price,pv,stock_quantity,reserved_quantity,status,is_deposit_product,image_path,metadata,name_translations,description_translations,category_translations,short_description_translations,benefits_translations,composition_translations,usage_translations`; casts: price/PV decimal:2, stock integers, deposit flag boolean, metadata/translations arrays.
- `PvTransaction` — fillable: `buyer_id,upline_id,source_order_id,source,branch,pv,is_bonusable,voided_at,voided_by,metadata`; casts: PV decimal:2, bonusable boolean, voided datetime, metadata array.
- `StatusBonusDefinition` — fillable: `status_code,status_name,threshold_pv,reward_type,amount,cash_amount,compensation_amount,compensation_available,currency,reward_text,is_cash_bonus,is_active,sort_order`; casts: threshold/amounts decimal:2, flags boolean, order integer.
- `SupportMessageAttachment` — fillable: `message_id,original_name,path,mime_type,size,disk`; business casts не объявлены.
- `SupportTicket` — fillable: `user_id,assigned_to,subject,category,message,status,priority,admin_reply,replied_at,closed_at,closed_by,last_reply_at,last_message_at,metadata`; casts: deleted/replied/closed/last times datetime, metadata array.
- `SupportTicketMessage` — fillable: `ticket_id,user_id,sender_role,message,is_staff`; casts: staff boolean.
- `SystemSetting` — fillable: `key,value,type,group,description`; casts: value array.
- `TransactionAdminAudit` — fillable: `transaction_id,admin_id,action,old_amount,new_amount,old_payload,new_payload,reason`; casts: amounts decimal:2, payloads arrays.
- `User` — fillable: `name,login,email,password,sponsor_id,current_package_id,status,account_status,admin_note,avatar_path,role,left_pv,right_pv,remaining_left_pv,remaining_right_pv,total_pv,deleted_by,deleted_reason,deleted_meta`; casts: deleted/email-verified datetime, password hashed, role string, all PV decimal:2, deleted_meta array.
- `UserProfile` — fillable: `user_id,first_name,last_name,phone,birth_date,country,city,address,avatar_path,metadata`; casts: birth date, metadata array.
- `UserStatusBonus` — fillable: `user_id,status_bonus_definition_id,bonus_transaction_id,status_code,amount,currency,reward_text,awarded_at,metadata`; casts: amount decimal:2, awarded datetime, metadata array.
- `UserX2Bonus` — fillable: `user_id,x2_bonus_definition_id,bonus_transaction_id,code,qualified_count,amount,currency,reward_text,awarded_at,metadata`; casts: count integer, amount decimal:2, awarded datetime, metadata array.
- `Wallet` — fillable: `user_id,type,currency,balance,hold_balance,status`; casts: balance/hold decimal:2.
- `WalletTransaction` — fillable: `wallet_id,user_id,type,direction,amount,balance_before,balance_after,status,affects_balance,source_type,source_id,description,metadata`; casts: amount/balances decimal:2, affects_balance boolean, metadata array.
- `WithdrawalRequest` — fillable: `user_id,wallet_id,amount,fee_amount,net_amount,currency,status,payment_method,payment_details,payout_period_days,admin_comment,processed_at`; casts: amounts decimal:2, payment details array, payout days integer, processed datetime.
- `X2BonusDefinition` — fillable: `code,required_status,required_count,reward_type,amount,currency,reward_text,is_cash_bonus,is_active,sort_order`; casts: count/order integer, amount decimal:2, flags boolean.

Accessors/mutators/observers не объявлены. `password:hashed` у User — cast, а не custom mutator. Domain helper methods/scopes/relations summarized в таблице выше и для User раскрыты ниже.

### User scopes и methods

- `activeAccount`: account_status active. Сам scope не добавляет `whereNull(deleted_at)`, но SoftDeletes global scope делает это по default.
- `activeMlm`: active non-deleted partner-like account, исключает support/accountant, требует active current public package.
- `partnerAccount`: role user.
- `eligibleSponsor`: active user или super_admin.
- Role predicates: `isUser/isSupport/isAdmin/isAccountant/isSuperAdmin`.
- `isPartnerActive/canInvitePartners`: active account, nontrashed, active START/VIP/ELITE.
- `packageStatus`: returns operational package state.

## Foreign key/delete behavior

- Большинство child operational records cascade при hard delete user/order/wallet. Application использует soft delete partner, чтобы не запускать cascade и предварительно reverses operations.
- Sponsor/current package/node parent используют nullOnDelete, сохраняя child.
- Bonus source/primary references nullOnDelete.
- Definition marker relation cascades definition/user deletion.
- Payments morph relation не имеет DB FK; payable может стать orphan при hard delete.

## Indices и duplicate protection

| Constraint | Защищает |
|---|---|
| users login/email unique | identity (soft-delete identity освобождается rewrite command/service) |
| package code/slug, product SKU, order number, payment external ID unique | external/business identity |
| binary node user unique + parent/position unique | один node и два child slots |
| wallet user/type unique | один wallet type |
| binary run user/period unique | duplicate scheduled award |
| user/status-definition unique | duplicate status reward |
| user/X2-definition unique | duplicate X2 reward |
| sender/idempotency-key unique | partner transfer retry when key provided |

Referral bonuses rely on metadata lookup, not DB unique constraint. Deposit cashback similarly uses metadata/source checks. Это service-level idempotency.

## Migrations chronology

- 04.05: Laravel base + core MLM/package/profile/tree/wallet/product/order/withdrawal/bonus/Sanctum/roles.
- 14–17.05: news/FAQ/support/settings/messages.
- 26.05: status/X2 definitions and markers.
- 01–04.06: management/translations/activity-turnover PV/stock snapshots/reward compensation/binary periods/deposit flag/notifications.
- 07–12.06: PV ledger, pending binary, delivery/avatar/search indices/affects_balance/safe deletion/TipTopPay/payments/transfers.
- 18–28.06: support attachments, transaction audits, payment strategies, forgot password.
- 20.08: unique binary period constraint.

В production следует применять обычный `php artisan migrate --force`, не `migrate:fresh`. User explicitly запрещает destructive migration; в ходе документации migrations не запускались.

## Seeders/factory

| Seeder | Purpose / risk |
|---|---|
| `PackageSeeder` | idempotent START/VIP/ELITE; deactivates BUSINESS |
| `StatusBonusDefinitionSeeder` | idempotent 9 status rules |
| `X2BonusDefinitionSeeder` | idempotent 3 X2 rules |
| `ProductSeeder` | 4 sample/current catalog products |
| `NewsSeeder`, `FaqSeeder` | localized content |
| `UserDemoSeeder` | demo users |
| `BinaryTreeDemoSeeder` | demo placement |
| `OrderDemoSeeder` | demo orders |
| `WalletDemoSeeder` | demo balances/transactions |
| `BonusAwardDemoSeeder`, `BonusDemoSeeder` | bonus demo data; `BonusDemoSeeder` exists but master seeder calls Award variant |
| `SupportTicketDemoSeeder` | demo support |
| `WithdrawalDemoSeeder` | exists, master `DatabaseSeeder` does not call it |
| `DatabaseSeeder` | calls definitions/catalog/content **and demo data** |

`UserFactory` — единственная factory. `DatabaseSeeder` не является безопасным production catalog-only seeder: он включает demo users/tree/orders/wallets/bonuses/support. Запуск `db:seed --force` в production в рамках задачи не выполнялся и не рекомендуется без явного selection отдельных definition seeders.

## Potential data concerns

- Current `users.total_pv` семантически смешивает personal/team updates; derived services rebuild it иначе. Использовать branch/PV ledger для financial decisions.
- Phone active uniqueness application-level и может иметь race без DB unique.
- Referral/cashback idempotency metadata-level, без unique key.
- Order PV создаётся до paid state; cancellation path не void-ит его.
- `payments` migration содержит compatibility branches для preexisting table; actual schema на старой production DB требует `migrate:status`/schema inspection.

# Service layer

[Главная](./PROJECT_DOCUMENTATION.md) · [MLM](./MLM.md) · [Wallet](./WALLET.md) · [Controllers](./CONTROLLERS.md) · [Database](./DATABASE.md)

В `app/Services` найдено 35 PHP-классов, включая два result/value objects и `Payments/TipTopPayService`. Ниже используются фактические имена. Отдельные ожидаемые классы `PackageActivationService`, `PackageUpgradeService`, `PvAccrualService`, `BinaryBonusService`, `ReferralBonusService`, `StatusBonusService`, `X2BonusService`, `WalletService`, `DepositPurchaseService`, `WithdrawalService`, `NotificationService` существуют лишь частично: первые пять сведены в `PackageService`, `PvService`, `BonusService`; `NotificationService` отсутствует.

## Dependency map

```text
PartnerRegistrationService
  ├─ BinaryTreeService
  ├─ WalletService
  ├─ PvService
  └─ BonusService

PackageService
  ├─ PvService → StatusService → StatusBonusService / X2BonusService
  ├─ BonusService → WalletService
  └─ ReferralBonusBaseResolver

CheckoutOrderService
  ├─ OrderPaymentSplitService
  ├─ PvService
  ├─ WalletService (deposit-only order)
  └─ BonusService (только deposit cashback)

ScheduledBinaryBonusService
  ├─ BinaryBonusPeriodResolver
  └─ BonusService::calculateBinaryBonus
       ├─ BinaryTreeSideResolver
       ├─ DashboardBranchVolumeService
       └─ WalletService

WithdrawalService / PartnerTransferService / InternalWalletTransferService
  └─ WalletService or direct locked wallet ledger
```

## PackageService

Path: `app/Services/PackageService.php`
Namespace: `App\Services`

Purpose: фактическая реализация package activation, upgrade и ручного назначения. Отдельных `PackageActivationService` и `PackageUpgradeService` нет.

Called by: package activation/upgrade controllers, `TipTopPayService` после paid package payment, admin `PartnerController`.

Dependencies: `PvService`, `BonusService`, `ReferralBonusBaseResolver`, `StatusBonusService`, `WalletService` — все передаются через constructor injection.

### `upgradePackage(User $user, Package $package, ?Order $sourceOrder = null): User`

Input: пользователь без пакета и START/VIP package (controller ограничивает public activation). Метод работает в `DB::transaction`, но сам не перезагружает user с `lockForUpdate()`.

Flow:

1. Назначает `current_package_id`.
2. Добавляет пользователю activity PV через `PvService::addUserPv()`.
3. Передаёт `turnover_pv` вверх через `PvService::accrueTurnoverToUplines()`; source выбирается `package_activation`/package-specific helper.
4. Если есть sponsor, `ReferralBonusBaseResolver::resolveForPackageActivation()` даёт base: `activityPv × 500` для START/VIP; ELITE base — zero.
5. `BonusService::accrueReferralBonus()` начисляет sponsor 10% при positive base.
6. Создаёт в main wallet audit-only `wallet_transactions` package operation: `affects_balance=false`, balance не меняется, amount равен цене package.
7. Для ELITE проверяет missed status bonuses.

Database: `users`, `pv_transactions`, upline PV columns, `wallets`, `wallet_transactions`, при bonus — `bonus_transactions`, `notifications`; status/X2 tables возможны через status recalc.

Side effects: personal/team PV, status recalculation, referral money, mail/database notification, package audit. Внутри реализации nullable `sourceOrder` принят, но не захвачен closure и не передаётся ниже; привязка activation к order в этом методе фактически отсутствует.

### `canUpgrade(User, Package): bool`

Разрешает точную цепочку START → VIP → ELITE. Same/lower/skipped transition запрещён.

### `assignPackageManually(User, Package, bool $applyBusinessEffects = true, ?User $actor = null): User`

Admin flow. Lock-ит user, рассчитывает delta activity/turnover между current и target. При `applyBusinessEffects=false` (или same package) только назначает package и сразу возвращает model: audit transaction и другие effects не создаются. При `true` добавляет positive personal delta, передаёт turnover delta, создаёт referral при eligible base и пишет package audit только при positive user PV. ELITE turnover считается non-bonusable и referral base zero. Metadata содержит actor, old/new package и business deltas.

### `upgradeExistingPackage(User, Package): array`

Проверяет exact next package, lock-ит user, считает `price_difference`, `activity_pv_difference` и turnover effect. START→VIP bonusable; VIP→ELITE turnover non-bonusable. Referral base равен price difference для upgrade, кроме ELITE, где resolver возвращает 0. Записывает audit-only `package_upgrade` transaction и возвращает user/differences.

## PvService

Path: `app/Services/PvService.php`

Purpose: фактический `PvAccrualService`: personal PV, propagation по ancestor chain, branch counters и immutable audit rows.

Called by: `PackageService`, `PartnerRegistrationService`, `CheckoutOrderService`; tests вызывают непосредственно.

Dependencies: `StatusService`.

### `accrueTurnoverToUplines(User $buyer, float|string $pv, string $source, array $meta = [], ?Order $sourceOrder = null, bool $isBonusable = true): void`

и `accruePvUpTree(...)` делегируют private `propagateToUplines()`.

Flow:

1. Reject PV `<=0`; загружает active `BinaryNode` buyer.
2. Идёт от buyer node к parent до root.
3. Position текущего child относительно parent (`L`/`R`) определяет branch upline.
4. `addBranchPv()` увеличивает у upline `left_pv` или `right_pv` и `total_pv`.
5. Только при `is_bonusable=true` увеличивает соответствующий `remaining_*_pv`.
6. Создаёт `pv_transactions`: buyer, upline, source order, source, branch, pv, bonusable, metadata.
7. Для каждого upline немедленно вызывает `StatusService::recalculate()`.

Transaction boundary приходит от caller; сам service не оборачивает полный public method в отдельную transaction.

### `addUserPv(User $user, $pv): void`

Увеличивает `users.total_pv`, затем status recalc. Важно: это же поле увеличивается branch propagation, поэтому `total_pv` в текущей записи является кэшированным смешанным показателем; dashboard отдельно вычисляет personal/branch через package/PV transactions.

### Audit

`recordPvTransaction()` хранит каждое upline событие. `recordTurnoverAudit()` добавляет order metadata. Void поддерживается полями `voided_at/voided_by` из migration и используется partner deletion/rollback, но обычный checkout cancellation его не вызывает.

## BinaryTreeService

Path: `app/Services/BinaryTreeService.php`

Purpose: placement и поиск слота в materialized-path binary tree.

Public methods: `placeUser`, `placeUnderSponsor`, `findSpilloverPosition`, `findDeepestSlotBySelectedSide`, `hasFreePosition`, `findFirstAvailableSlotInBranch`.

Called by: `PartnerRegistrationService`, referral preview controller, demo command/tests.

Flow `placeUser()`:

1. В transaction lock-ит/проверяет, что active node пользователя отсутствует.
2. Без sponsor создаёт root: parent/position null, depth 0, path = user ID.
3. Со sponsor требует `L`/`R`, active eligible sponsor и его node; если sponsor node отсутствует, создаёт root для sponsor.
4. `findDeepestSlotBySelectedSide()` начинает с sponsor и всё время идёт по выбранному child (`L` для left или `R` для right), пока слот на этой side-chain не свободен. Это не breadth-first spillover.
5. Создаёт child с той же position, depth+1, path `parent.path.user_id`.

Soft-deleted/archived nodes проверяются `withTrashed` при занятости слота: удаление не освобождает прежнюю физическую позицию.

Database: `binary_nodes`, read `users`. Side effects кроме node отсутствуют.

## BinaryTreeSideResolver

Path: `app/Services/BinaryTreeSideResolver.php`

Public: `getRootSideForDescendant(rootUserId, descendantUserId)`, `getRootSideMapForDescendants(rootUserId)`.

Идёт по parents или анализирует path, чтобы определить, через какого direct binary child (`L`/`R`) descendant входит в subtree root. Используется binary eligibility/X2/volume calculations. Read-only: `binary_nodes`.

## DashboardBranchVolumeService

Path: `app/Services/DashboardBranchVolumeService.php`

Purpose: единый read/rebuild слой фактических branch volumes и counts.

Public methods:

- `getVolumesForRoot`, `getBranchVolumes` — left/right/total/weak leg и counts;
- `getUserTurnoverPvForBranch`, `getUserPersonalPv`;
- `calculateDirectionalBranchPv`, `calculateNodeBranchVolumes`;
- `syncCachedBranchVolumes` — обновляет `users.left_pv/right_pv/total_pv`, но не remaining;
- `buyerBelongsToDirectionalBranch`.

Источники в порядке reconciliation: non-voided `pv_transactions`; при отсутствии transaction history — active tree + package PV fallback; cached user values могут участвовать как fallback. Исключаются inactive/deleted users/nodes. Called by dashboard/admin structure, status, binary preview/recalc and commands.

## BonusService

Path: `app/Services/BonusService.php`

Purpose: фактическая реализация referral, binary и deposit-purchase cashback. Отдельных `BinaryBonusService` и `ReferralBonusService` нет.

Dependencies: `WalletService`, `BinaryTreeSideResolver`, `DashboardBranchVolumeService`.

Constants: referral 10%, cashback 20%, PV money rate 500 KZT/PV, default binary period 15 days.

### `accrueReferralBonus(User $sponsor, User $referral, float|string $baseAmount, array $metadata = [], ?string $idempotencyKey = null): ?BonusTransaction`

Flow:

1. Sponsor/referral должны быть active accounts; base > 0.
2. Если explicit `$idempotencyKey` передан и referral bonus с тем же metadata key найден, возвращает его.
3. `amount = base × 10 / 100`; `packages.referral_percent` не читается.
4. `WalletService::credit()` кредитует main wallet transaction type `referral_bonus`; morph source — сам `BonusTransaction`, а referral user ID находится в wallet metadata и `bonus_transactions.source_user_id`.
5. Создаёт completed `bonus_transactions`: type `referral`, `source_user_id=referral`, amount и metadata с base/percent/package context. Current callers не передают/не записывают `source_order_id`.
6. Устанавливает wallet_transaction_id и отправляет `BonusAccruedNotification` mail+database.

Tables: `users`, `wallets`, `wallet_transactions`, `bonus_transactions`, `notifications`.

### `calculateBinaryBonus(User, ?periodStart, ?periodEnd, array $metadata): ?BonusTransaction`

Полный flow:

1. Transaction lock active MLM user с active START/VIP/ELITE.
2. Duplicate guard: exact custom period или active future-ending default run; DB unique period constraint дополнительно защищает scheduler.
3. Проверяет минимум одного personally sponsored active MLM referral в каждой корневой binary ветке. Referral может быть глубже direct binary child; root side определяет resolver.
4. Строит PV snapshot. При наличии `pv_transactions` суммирует total, voided, inactive/deleted buyer, non-bonusable и available bonusable. Used PV прошлых completed/pending runs вычитается отдельно для каждой ветки. Если PV transaction history отсутствует, применяет dashboard volume fallback минус prior used.
5. `matched = min(left_available, right_available)`; zero → no bonus.
6. Берёт `currentPackage.binary_percent`. `money_base = matched × 500`; `amount = money_base × percent / 100`.
7. Делит `main = amount × 90%`, `deposit = amount - main`.
8. Carry/remaining = available − matched; обновляет `users.remaining_left_pv/right_pv`.
9. Создаёт completed `binary_bonus_runs`, completed `bonus_transactions` type `binary`, затем credits `binary_bonus_main` и `binary_bonus_deposit`.
10. Связывает run/bonus/primary main wallet transaction; создаёт `binary_bonus_calculations` snapshot и notification.

Metadata включает period, total/available/used/carry PV, excluded components, buyer counts, rate, money base, split, package и source/batch identifiers.

### `recalculateBinaryBonus(User, ?User $admin, ?periodStart, ?periodEnd): array`

Находит current run. Preview игнорирует PV самого редактируемого run в prior-used. Если run отсутствует, создаёт его обычным calculate при eligibility. Если существует:

- сравнивает новую сумму с фактическими активными wallet legs;
- credit/debit-ит разницу main/deposit через `binary_bonus_recalculation_*` transactions;
- обновляет run и BonusTransaction, remaining PV и связи;
- добавляет новую `BinaryBonusCalculation` audit row;
- пишет `AdminActionLog`.

Debit adjustment способен завершиться validation error, если текущего wallet balance недостаточно.

### `recalculateBinaryBonusesForAllPartners(?User $admin, string $source): array`

Итерирует active MLM partners, вызывает previous method, считает processed/skipped/failed и пишет aggregate admin audit. Admin API endpoints используют этот mutable recalculation, scheduler — другой immutable service.

### `accrueDepositPurchaseCashback(User $user, float|string $purchaseAmount, ?WalletTransaction $sourceTransaction = null, ?string $idempotencyKey = null): ?BonusTransaction`

Idempotency key передаётся явно либо строится из source transaction (`deposit_purchase_cashback:{order_id}`, с fallback по transaction id). Credits main wallet на 20%, создаёт completed bonus type `cashback`; `source_order_id` заполняется только для morph source `Order`, metadata сохраняет сумму, процент, key и source transaction. Затем связывает wallet transaction и отправляет notification. Deposit order PV = 0.

## BinaryBonusPeriodResolver

Path: `app/Services/BinaryBonusPeriodResolver.php`

`resolve(?CarbonInterface $scheduledFor): array` определяет полумесячный интервал в `Asia/Tashkent`: границы 1-го 03:00 и 15-го 03:00, затем возвращает local/UTC timestamps/labels. Используется scheduler service и его unique period identity.

## ScheduledBinaryBonusService

Path: `app/Services/ScheduledBinaryBonusService.php`

`calculateForAllPartners()`:

1. Resolve current half-month period.
2. Берёт cache lock на 1 час для period.
3. Выбирает active MLM users role=user.
4. Пропускает exact existing period; вызывает `BonusService::calculateBinaryBonus` с period/batch metadata.
5. Возвращает total/processed/skipped/failed/errors и пишет `AdminActionLog` batch summary.

Повторный scheduler run защищён service check, cache lock и DB unique constraint `(user_id, period_start, period_end)`. Это основной production binary path.

## StatusService

Path: `app/Services/StatusService.php`

Dependencies: `DashboardBranchVolumeService`; через container — `StatusBonusService`, `X2BonusService`.

Public:

- `publicStatuses()` — 9 localized definitions;
- `statusForPv()` — highest threshold `<= weak leg PV`;
- `weakLegPv()` — min(current left/right volume);
- `rankForStatus()`;
- `recalculate(User)`;
- `recalculateAll(chunk=500)`.

`recalculate()` ignores trashed/non-active account, рассчитывает weak leg, меняет status даже вниз, а notification отправляет только при upward rank. После этого всегда вызывает status bonus sync; при наличии sponsor проверяет X2 sponsor. Следовательно status recalc имеет финансовые side effects.

Database: reads PV/tree/package; writes `users.status`, notifications, potentially status/X2/bonus/wallet ledgers.

## StatusBonusService

Path: `app/Services/StatusBonusService.php`

Purpose: qualification, once-only markers и repair денежных/неденежных status rewards.

Public methods: `awardEligible`, `syncForUser`, `seedDefaultDefinitions`, `definitionsHealth`, `checkMissedStatusBonuses`, `awardManualStatusBonus(es)`, `repairForUser`.

Rules:

- только current package `ELITE`;
- eligibility определяется current weak-leg PV и active definition threshold;
- unique `(user_id, status_bonus_definition_id)` защищает повторную award;
- gift definition создаёт только `user_status_bonuses` marker;
- cash definition credit-ит main via WalletService, создаёт `bonus_transactions` type `status_bonus`, marker и notification;
- manual admin award пишет actor/source metadata;
- repair mode умеет seed definitions, missing marker, missing bonus/wallet ledger и normalization; write требует command `--force`.

`cash_amount` используется как выплата. `compensation_amount/compensation_available` сохраняются в definitions/metadata, но workflow выбора компенсации не найден.

## X2BonusService

Path: `app/Services/X2BonusService.php`

`awardEligible(User): Collection` lock-ит active user, берёт active definitions и для каждой проверяет:

1. Только first-line referrals (`users.sponsor_id = sponsor.id`).
2. Referral active и его status rank не ниже required status.
3. Определяется root binary side каждого referral.
4. Нужно `required_count` (seeded 5) и distribution минимум 2 слева и 2 справа.
5. Existing `user_x2_bonuses` marker исключает duplicate.
6. Non-cash reward создаёт marker; cash также создаёт `bonus_transactions` type `bonus_x2`, credit main type `x2_bonus` и notification.

Tables: users/tree/definitions/user markers/bonus/wallet/notifications. Trigger: status recalculation достигшего status referral вызывает award sponsor.

## ReferralBonusBaseResolver

Path: `app/Services/ReferralBonusBaseResolver.php`

- `resolveForPackageActivation`: START/VIP `activityPv × 500`; ELITE 0.
- `resolveForPackageUpgrade`: positive price difference, но target ELITE 0.
- `resolveForProductOrder`: order `total_amount`; current production caller не найден.

Это base amount, к которому `BonusService` применяет 10%.

## ReferralService

Path: `app/Services/ReferralService.php`

Содержит `assignSponsor()` и `accrueReferralBonus()` без реализации. Callers не найдены. **Не является фактическим bonus path**; реальные вызовы идут в `PartnerRegistrationService`/`BonusService`. Использовать этот stub нельзя без дополнительной проверки.

## PartnerRegistrationService

Path: `app/Services/PartnerRegistrationService.php`

Dependencies: `BinaryTreeService`, `WalletService`, `PvService`, `BonusService`.

Public flow `register(data, actor?, payReferralBonus, source, notifyRegisteredUser)`:

1. Transaction; resolve sponsor по explicit ID или referral code (login/id и optional legacy columns, если schema содержит их).
2. Sponsor — active account role user/super_admin; public referral source дополнительно требует active public package.
3. Создаёт `User` (`current_package_id=null` первоначально) и `UserProfile`.
4. Создаёт main/bonus/deposit wallets.
5. Со sponsor и explicit branch помещает через BinaryTreeService. Без sponsor root node не создаётся этим service.
6. Initial package разрешён только START/VIP. Назначается только для sources с `admin`/`bulk`; public selection остаётся неназначенным.
7. Admin package assignment может добавить personal/turnover PV, audit transaction и optional referral.
8. Optional `UserRegisteredNotification` mail.

Public helpers: `resolveSponsorByReferralCode`, `createUser`, `placeInBinaryTree`, `assignInitialPackage`, `accrueTurnoverToUplines`, `createPackageTransaction`, `createReferralBonusIfNeeded`.

## CheckoutOrderService

Path: `app/Services/CheckoutOrderService.php`

Dependencies: `OrderPaymentSplitService`, `PvService`, `BonusService`, `WalletService`. BonusService здесь используется только для deposit-purchase cashback; regular referral call отсутствует.

`create(User, validated): Order`:

1. Transaction, lock selected active products; validates positive stock.
2. Запрещает смешивать deposit и regular products.
3. Для regular `unit_pv = Product::turnoverPv() = price / 500`; поле `products.pv` в расчёте order turnover не используется. Для deposit product PV=0.
4. Проверяет payment strategy. Deposit products требуют `deposit_100`; regular запрещают `deposit_100`. `card_50_deposit_50` заранее проверяет deposit balance.
5. Создаёт Order/OrderItems со snapshots, delivery, totals/card/deposit split.
6. Уменьшает `products.stock_quantity` сразу.
7. Deposit-only: сразу debit deposit, order paid/completed provider=deposit, начисляет 20% cashback main; MLM PV/referral не создаёт.
8. Regular: создаёт unpaid/pending order и немедленно передаёт total PV вверх. Referral bonus из regular order не начисляется. PV propagation происходит до TipTopPay success.

Tables: products/orders/items, wallets/transactions, pv_transactions/upline users, bonuses/notifications. Potential issue pre-payment PV описан в главном документе.

## OrderPaymentSplitService

Path: `app/Services/OrderPaymentSplitService.php`

`split(total, strategy)` возвращает normalized strategy, card/deposit amounts:

- `card_100`: всё card;
- `deposit_100`: всё deposit;
- `card_50_deposit_50`: card = ceil(total / 2) до 2 decimals, deposit = remainder.

Read/calculation only.

## DepositPurchaseService

Path: `app/Services/DepositPurchaseService.php`

Dependencies: WalletService, BonusService.

Public:

- `purchase(User, amount)` — generic arbitrary deposit spending + cashback; controller route его напрямую не использует;
- `purchaseProduct(User, Product, quantity)`;
- `purchaseProducts(User, items, orderData)`.

Product flows lock stock/deposit wallet, требуют `is_deposit_product=true`, создают paid deposit order with zero PV, decrement stock, debit type `deposit_purchase`, then 20% cashback main. Idempotency основывается на debit transaction. Mixed/non-deposit/out-of-stock/insufficient balance → validation error.

## PackageAutoUpgradeFromPaidOrdersService

Path: `app/Services/PackageAutoUpgradeFromPaidOrdersService.php`

`handlePaidOrder(Order): PackageUpgradeResult` вызывается TipTopPay pay/confirm и admin manual payment-status transition. Eligibility: paid non-cancelled regular product order. Суммирует все paid regular product orders пользователя:

- 60 000 → START;
- 180 000 → VIP;
- 300 000 → ELITE.

Если target rank выше current, напрямую меняет `current_package_id`, гарантирует `users.total_pv >= package.activityPv`, создаёт database notification и audit-only `package_auto_upgrade` wallet transaction amount 0. Он **не вызывает** PvService, referral bonus, binary propagation или StatusService. Duplicate защищается current rank и metadata/notification checks.

## PackagePurchaseAvailabilityService

Path: `app/Services/PackagePurchaseAvailabilityService.php`

Public `actionFor()` строит dashboard action payload, `transitionForPayment()` строго проверяет package purchase transition. При `SAFI_USER_PACKAGE_PURCHASES_ENABLED=false` действия informational/locked. При включении: без package доступен START; далее exact START→VIP→ELITE; требуется active package и configured TipTopPay terminal/currency. Read-only, кроме validation exception.

## PackageUpgradeResult / DeleteResult

Paths: `app/Services/PackageUpgradeResult.php`, `app/Services/DeleteResult.php`.

Небольшие immutable result objects. `PackageUpgradeResult` хранит previous/current package и reason, `upgraded()`; `DeleteResult` serializes partner deletion result. Таблицы не затрагивают.

## WalletService

Path: `app/Services/WalletService.php`

Подробно: [WALLET.md](./WALLET.md#walletservice).

Public:

- `createUserWallets(User)` — `firstOrCreate` main/bonus/deposit KZT;
- `credit(Wallet, amount, type, source?, metadata=[], description?)` — positive amount, balance increment, completed credit row with before/after and `affects_balance=true`;
- `debit(Wallet, amount, type, source?, metadata=[], description?)` — positive amount, sufficient balance, decrement and debit row;
- `recordNonBalanceOperation(Wallet, amount, type, source?, metadata=[], description?, direction='neutral')` — unchanged balance, `affects_balance=false`.

Caller должен обеспечить подходящую transaction/lock discipline. Source сохраняется morph (`source_type/source_id`).

## WithdrawalService

Path: `app/Services/WithdrawalService.php`

Public `requestWithdrawal`, `approveWithdrawal`, `rejectWithdrawal`. Полный state machine: [WALLET.md](./WALLET.md#withdrawal).

- request: lock main wallet, balance check, `balance -= amount`, `hold += amount`, pending withdrawal, affecting debit `withdrawal_hold`, mail.
- approve: pending/user active/hold check, `hold -= amount`, status approved/processed, neutral audit `withdrawal_approved`; balance уже был уменьшен при request.
- reject: `hold -= amount`, `balance += amount`, rejected/comment, affecting credit `withdrawal_rejected`.

## PartnerTransferService

Path: `app/Services/PartnerTransferService.php`

`transfer(sender, recipientUserId, amount, comment, idempotencyKey)`:

1. Active recipient role=user, not self; positive amount.
2. Optional idempotency key trimmed; existing sender transfer возвращается.
3. Transaction и deterministic locks sender/recipient main wallets.
4. Debit sender `partner_transfer_out`, credit recipient `partner_transfer_in`.
5. Создаёт `partner_transfers` UUID, links обеих transactions и metadata.
6. Database notifications обеим сторонам.

Unique sender+idempotency key защищает supplied keys; без key повторный HTTP request создаёт новый transfer.

## InternalWalletTransferService

Path: `app/Services/InternalWalletTransferService.php`

`transfer(user, from, to, amount, comment)` допускает только `main → deposit`; controller дополнительно разрешает только admin/super_admin. Lock wallets, debit/credit pair с общим transfer UUID, no PartnerTransfer model, no reverse path. Database: wallets/transactions.

## AdminWalletTransactionService

Path: `app/Services/AdminWalletTransactionService.php`

`updateAmount()` и `void()` обслуживают admin transaction mutation.

- lock transaction/wallet; reject already reversed/voided/cancelled;
- financial effect определяется `affects_balance` и direction;
- update применяет delta к current wallet, меняет amount/balance snapshot, linked BonusTransaction amount, notification payload;
- void reverses financial effect, marks transaction voided, linked bonus voided, удаляет notification;
- каждое действие пишет `transaction_admin_audits` old/new payload и reason.

Potential issue: private `applyWalletDelta()` не запрещает отрицательный resulting balance. Route permission допускает accountant к route, но update FormRequest разрешает только admin/super_admin; delete — только super_admin.

## BonusAdminAdjustmentService

Path: `app/Services/BonusAdminAdjustmentService.php`

`updateAmount()`/`delete()` имеют внутренний super-admin assertion. Для ordinary bonus корректируется primary wallet; для binary пересчитываются 90/10 legs. Создаются adjustment/reversal wallet transactions, синхронизируются linked `BinaryBonusRun` amounts, bonus notifications и `AdminActionLog`. Delete voids bonus/run and reverses active wallet effects. HIGH RISK.

## EarningsSummaryService

Path: `app/Services/EarningsSummaryService.php`

`forUser(User)` читает completed bonuses by type, main/deposit balances, pending binary и withdrawal totals. Возвращается dashboard bonus/earnings endpoints. Потенциальное несоответствие: status total читает key `status`, тогда как ledger создаёт `status_bonus`.

## TipTopPayService

Path: `app/Services/Payments/TipTopPayService.php`
Namespace: `App\Services\Payments`

Purpose: payment intent, callback verification и state transitions для Orders и Packages.

Called by: `TipTopPayIntentController`, `TipTopPayWebhookController`.

Dependencies: `PackageService`, `PackagePurchaseAvailabilityService`, `PackageAutoUpgradeFromPaidOrdersService`, `OrderPaymentSplitService`, `WalletService`.

### Intent methods

- `createIntent(Order, actor)` alias к order intent.
- `createOrderPaymentIntent(User, Order)` проверяет owner/admin, config enabled + terminal + KZT, nonempty/positive/nonclosed order и delivery. Для 50/50 один раз debit-ит deposit part. Создаёт pending polymorphic Payment с UUID external ID, сохраняет sanitized intent/payment meta и возвращает public widget data.
- `createPackagePaymentIntent(User, Package, upgradeFrom)` доступен только при feature flag через controller; строгий transition, amount = price или difference, pending Payment type package.
- `publicStatus()` публикует только enabled/test/currency/public-terminal-present.

Intent содержит terminal ID, schema, KZT amount, external ID, account/email, success/fail redirects, userInfo, items и metadata. API password/backend secret в frontend не передаются.

### Callback methods

`handleCheck/Pay/Confirm/Fail/Refund/Cancel(Request)` сначала проверяют HMAC, затем передают payload methods:

- check: payment exists, amount/currency exact, status payable;
- pay/confirm: amount/currency exact; duplicate paid only append event; mark Payment paid; Order paid/confirmed, nonbalance `order_payment` audit и auto package upgrade; Package payment вызывает `PackageService` activation/upgrade и привязывает payment к latest package transaction;
- fail: non-paid Payment становится failed, Order payment_status — failed, 50/50 deposit release; order fulfillment status/stock/PV не меняются; amount/currency check здесь не выполняется;
- refund/cancel: terminal handler меняет Payment status и для Order — payment/fulfillment status на refunded-or-cancelled/cancelled, release deposit. Он не вызывает stock restoration или PV reversal. Для package Payment меняется только Payment: уже выданный package/PV/referral/status effect не отменяется. Amount/currency check также не выполняется.

Provider responses и payable/order `payment_meta` держат последние 20 events. Payload sanitizer удаляет известные card/token/name/IP поля перед audit, но его completeness зависит от реального provider payload.

### Webhook authentication

Header `X-Content-HMAC` или `Content-HMAC`, expected = Base64(HMAC-SHA256(raw body, webhook secret)). Если secret пуст, `validWebhookSignature()` возвращает true; production обязан задать `TIPTOPPAY_WEBHOOK_SECRET`.

### Idempotency/state protection

External ID unique. Paid callbacks lock Payment and ignore repeat paid transition. 50/50 deposit metadata фиксирует debit/release transaction IDs. Package/order effects вызываются только при first transition to paid. Legacy fallback может найти Order по external ID/order id и создать Payment record для compatibility.

## SupportTicketService

Path: `app/Services/SupportTicketService.php`

Public: create ticket, partner/staff message, close, reopen, load. Создаёт initial/message rows, sender role/is_staff, обновляет ticket status/assignment/reply/last-message timestamps. Closed ticket нельзя продолжать без reopen. Database: support tickets/messages/users.

## SupportAttachmentStorage

Path: `app/Services/SupportAttachmentStorage.php`

`store(message, UploadedFile)` пишет unique file на configured local disk под support path и создаёт attachment row с original name/MIME/size/disk. `download()` проверяет existence и возвращает streamed response. Authorization выполняется controller: dashboard owner; staff permission route.

## TransactionNotificationTextFactory

Path: `app/Services/TransactionNotificationTextFactory.php`

Создаёт multilingual title/message/data для bonus и wallet transaction types, преобразует bonus type в notification type. Используется `BonusAccruedNotification` и admin mutation notification sync. Read/format only.

## PartnerDeletionService

Path: `app/Services/PartnerDeletionService.php`

Public: preview, delete, release identity, recalculate after delete, cleanup operational data, recalculate affected/all users.

`deletePartner()` — super-admin-only transaction:

1. Проверяет staff/self/root/children constraints и optionally subtree.
2. Собирает affected uplines.
3. Void-ит PV.
4. Reverses бонусы и wallet transactions, status/X2 markers.
5. Void-ит package audit transactions.
6. Cancels pending withdrawals и active orders, восстанавливая нужные balances/stock.
7. Archives binary nodes, closes wallets.
8. Rewrites login/email/phone identity техническими unique значениями.
9. Soft-deletes users и пишет admin log.
10. Rebuild affected upline left/right/remaining/total/status напрямую без выдачи новых bonuses.

`voidDeletedUserOperationalData()` используется cleanup command для уже deleted/archived users. HIGH RISK, затрагивает большинство финансовых таблиц.

## BinaryRecalculationRollbackService

Path: `app/Services/BinaryRecalculationRollbackService.php`

Public:

- `discover(startedAt, endedAt, scope, runIds, controlUserId, reconciliation, checksum)` строит immutable manifest, operations, blockers, snapshots и wallet replay plans;
- `fingerprint(manifest)` canonical SHA fingerprint;
- `execute(manifest, confirmedFingerprint, reconciliation, checksum)` повторно валидирует fingerprint/blockers, выполняет PV deltas, restore historical runs/delete newly created runs, deletes/restores bonuses/calculations/notifications и replay wallet ledger/balances;
- `hasCompletedRollback(fingerprint)` ищет completion audit для duplicate guard.

Scope: `adjustments-only` или `entire-batch`. Service рассчитан только на command с approved manifest. Tables: binary runs/calculations, bonus/wallet/PV transactions, users, notifications, admin logs; reconciliation может затрагивать transfers/manual adjustments. Это самый опасный maintenance service.

## BinaryIncidentReconciliationService

Path: `app/Services/BinaryIncidentReconciliationService.php`

`plan()` валидирует approved JSON для manual adjustments и partner transfers, IDs/amounts/directions/ledger state; `withWalletPlans()` объединяет replay; `apply()` void-ит manual adjustment и reverses partner transfers в строго вычисленном dependency order. Используется rollback service; independent callers не найдены.

## Сводная таблица всех сервисов

| Class | Main callers | Main tables | Side effects |
|---|---|---|---|
| `AdminWalletTransactionService` | admin TransactionController | wallets, wallet_transactions, bonuses, notifications, audits | mutable money |
| `BinaryBonusPeriodResolver` | ScheduledBinaryBonusService | — | none |
| `BinaryIncidentReconciliationService` | rollback service | wallets, tx, transfers, audits | reversals |
| `BinaryRecalculationRollbackService` | rollback command | binary/PV/bonus/wallet/notifications | restore/delete/replay |
| `BinaryTreeService` | registration/referral/demo | binary_nodes | placement |
| `BinaryTreeSideResolver` | Bonus/X2 | binary_nodes | read only |
| `BonusAdminAdjustmentService` | admin BonusController | bonus/binary/wallet/audit | mutable money |
| `BonusService` | package/order/binary/admin | bonus/binary/PV/wallet | credits/debits |
| `CheckoutOrderService` | OrderController | products/orders/PV/bonus/wallet | stock, PV, money |
| `DashboardBranchVolumeService` | dashboard/status/binary/commands | tree/PV/users/packages | read/cache sync |
| `DeleteResult` | PartnerDeletionService | — | value object |
| `DepositPurchaseService` | DepositPurchaseController | products/orders/wallet/bonus | deposit debit/cashback |
| `EarningsSummaryService` | dashboard | bonus/wallet/withdrawal/run | read only |
| `InternalWalletTransferService` | dashboard internal endpoint | wallets/transactions | main→deposit |
| `OrderPaymentSplitService` | checkout/payment | — | calculation |
| `PackageAutoUpgradeFromPaidOrdersService` | TipTopPay/admin order | orders/users/notifications/wallet tx | direct package update |
| `PackagePurchaseAvailabilityService` | dashboard/package payment | packages/users/config | read/validate |
| `PackageService` | package/payment/admin | users/PV/bonus/wallet | package + MLM |
| `PackageUpgradeResult` | auto-upgrade | — | value object |
| `PartnerDeletionService` | admin/commands | most operational tables | reversals/delete |
| `PartnerRegistrationService` | Auth/admin | users/profile/tree/wallet/PV/bonus | create partner |
| `PartnerTransferService` | dashboard | wallets/transactions/transfers/notifications | user→user money |
| `PvService` | package/order | users/PV | PV + status effects |
| `ReferralBonusBaseResolver` | `PackageService`; product-order helper сейчас не вызывается | — | calculation |
| `ReferralService` | no callers found | — | stub |
| `ScheduledBinaryBonusService` | command/scheduler | binary/bonus/wallet/audit | immutable awards |
| `StatusBonusService` | StatusService/admin/command | definitions/markers/bonus/wallet | awards/repair |
| `StatusService` | PvService/commands/admin | users + downstream ledgers | status/awards |
| `SupportAttachmentStorage` | support controllers | attachments/files | private file IO |
| `SupportTicketService` | support controllers | tickets/messages | support state |
| `TransactionNotificationTextFactory` | notifications/admin mutation | — | format only |
| `WalletService` | financial services | wallets/transactions | balance ledger |
| `WithdrawalService` | withdrawal controllers | withdrawals/wallet tx | hold/approve/reject |
| `X2BonusService` | StatusService/tests | definitions/markers/bonus/wallet | X2 awards |
| `Payments\TipTopPayService` | payment controllers | payments/orders/packages/wallet | payment state/business effects |

## Полный public method index

Этот индекс сверён с объявлениями `public function` во всех 35 файлах. Paths по умолчанию `app/Services/<File>`; TipTopPay находится в `app/Services/Payments/TipTopPayService.php`. Constructors не перечислены; dependencies указаны в детальных разделах выше.

| File/class | Public methods |
|---|---|
| `AdminWalletTransactionService.php` | `updateAmount()`, `void()` |
| `BinaryBonusPeriodResolver.php` | `resolve()` |
| `BinaryIncidentReconciliationService.php` | `plan()`, `withWalletPlans()`, `apply()` |
| `BinaryRecalculationRollbackService.php` | `discover()`, `fingerprint()`, `execute()`, `hasCompletedRollback()` |
| `BinaryTreeService.php` | `placeUser()`, `placeUnderSponsor()`, `findSpilloverPosition()`, `findDeepestSlotBySelectedSide()`, `hasFreePosition()`, `findFirstAvailableSlotInBranch()` |
| `BinaryTreeSideResolver.php` | `getRootSideForDescendant()`, `getRootSideMapForDescendants()` |
| `BonusAdminAdjustmentService.php` | `updateAmount()`, `delete()` |
| `BonusService.php` | `accrueReferralBonus()`, `calculateBinaryBonus()`, `recalculateBinaryBonus()`, `recalculateBinaryBonusesForAllPartners()`, `accrueDepositPurchaseCashback()` |
| `CheckoutOrderService.php` | `create()` |
| `DashboardBranchVolumeService.php` | `getVolumesForRoot()`, `getBranchVolumes()`, `getUserTurnoverPvForBranch()`, `getUserPersonalPv()`, `calculateDirectionalBranchPv()`, `calculateNodeBranchVolumes()`, `syncCachedBranchVolumes()`, `buyerBelongsToDirectionalBranch()` |
| `DeleteResult.php` | `toArray()` |
| `DepositPurchaseService.php` | `purchase()`, `purchaseProduct()`, `purchaseProducts()` |
| `EarningsSummaryService.php` | `forUser()` |
| `InternalWalletTransferService.php` | `transfer()` |
| `OrderPaymentSplitService.php` | `split()` |
| `PackageAutoUpgradeFromPaidOrdersService.php` | `handlePaidOrder()` |
| `PackagePurchaseAvailabilityService.php` | `actionFor()`, `transitionForPayment()` |
| `PackageService.php` | `upgradePackage()`, `canUpgrade()`, `assignPackageManually()`, `upgradeExistingPackage()` |
| `PackageUpgradeResult.php` | `upgraded()` |
| `PartnerDeletionService.php` | `previewDelete()`, `deletePartner()`, `releaseDeletedUserIdentity()`, `recalculateAfterDelete()`, `voidDeletedUserOperationalData()`, `recalculateAffectedUplines()`, `recalculateAllActiveUsers()` |
| `PartnerRegistrationService.php` | `register()`, `resolveSponsorByReferralCode()`, `createUser()`, `placeInBinaryTree()`, `assignInitialPackage()`, `accrueTurnoverToUplines()`, `createPackageTransaction()`, `createReferralBonusIfNeeded()` |
| `PartnerTransferService.php` | `transfer()` |
| `Payments/TipTopPayService.php` | `createIntent()`, `createOrderPaymentIntent()`, `createPackagePaymentIntent()`, `publicStatus()`, `handleCheck()`, `handlePay()`, `handleConfirm()`, `handleFail()`, `handleRefund()`, `handleCancel()`, `handleCheckWebhook()`, `handlePayWebhook()`, `handleFailWebhook()`, `findPaymentByExternalId()` |
| `PvService.php` | `accrueTurnoverToUplines()`, `accruePvUpTree()`, `addUserPv()` |
| `ReferralBonusBaseResolver.php` | `resolveForPackageActivation()`, `resolveForPackageUpgrade()`, `resolveForProductOrder()` |
| `ReferralService.php` | `assignSponsor()`, `accrueReferralBonus()`; оба stub, production callers не найдены |
| `ScheduledBinaryBonusService.php` | `calculateForAllPartners()` |
| `StatusBonusService.php` | `awardEligible()`, `syncForUser()`, `seedDefaultDefinitions()`, `definitionsHealth()`, `checkMissedStatusBonuses()`, `awardManualStatusBonus()`, `awardManualStatusBonuses()`, `repairForUser()` |
| `StatusService.php` | `publicStatuses()`, `statusForPv()`, `weakLegPv()`, `rankForStatus()`, `recalculate()`, `recalculateAll()` |
| `SupportAttachmentStorage.php` | `store()`, `download()` |
| `SupportTicketService.php` | `createTicket()`, `sendPartnerMessage()`, `sendAdminMessage()`, `closeTicket()`, `reopenTicket()`, `loadTicket()` |
| `TransactionNotificationTextFactory.php` | `makeForBonusTransaction()`, `makeForWalletTransaction()`, `notificationTypeFromBonusType()` |
| `WalletService.php` | `createUserWallets()`, `credit()`, `debit()`, `recordNonBalanceOperation()` |
| `WithdrawalService.php` | `requestWithdrawal()`, `approveWithdrawal()`, `rejectWithdrawal()` |
| `X2BonusService.php` | `awardEligible()` |

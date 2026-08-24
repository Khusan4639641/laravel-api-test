# MLM / Bonus System

[Главная](./PROJECT_DOCUMENTATION.md) · [Services](./SERVICES.md) · [Wallet](./WALLET.md) · [Database](./DATABASE.md) · [Commands](./COMMANDS.md)

## Термины в текущем коде

| Термин | Реальное представление |
|---|---|
| Sponsor / inviter | `users.sponsor_id`; personal referral relationship, не обязательно binary parent |
| Referral | user, чей `sponsor_id` равен sponsor ID; first-line direct referral |
| Binary parent | `binary_nodes.parent_id`; placement parent, может отличаться от sponsor из-за spillover |
| Left/right branch | первый child `L`/`R` под рассматриваемым root node и весь его subtree |
| Personal/activity PV | `Package::activityPv()`; при package flow добавляется в `users.total_pv` |
| Turnover PV | `Package::turnoverPv()` или product order `price/500`; передаётся uplines |
| left/right PV | branch volume, кэш в users и event ledger в `pv_transactions` |
| remaining PV | `users.remaining_left_pv/right_pv`; обновляется accrual и binary consumption |
| Weak leg | `min(current left branch PV, current right branch PV)` |
| Matched PV | `min(left available after exclusions/prior use, right available ...)` |
| Bonus ledger | `bonus_transactions`; деньги отражаются отдельно в `wallet_transactions` |

Sponsor relationship и binary placement — две разные связи. Sponsor определяет referral bonus/X2 first line. Binary node path определяет сторону PV и binary qualification.

## Registration

```text
GET /api/ref/{sponsor}/{L|R}
  → active sponsor + active START/VIP/ELITE
  → selected side-chain spillover preview

POST /api/register
  → RegisterRequest
  → resolve sponsor by sponsor_id/referral code(login)
  → sponsor canInvitePartners()
  → PartnerRegistrationService::register
     ├─ users
     ├─ user_profiles
     ├─ wallets: main / bonus / deposit
     ├─ BinaryTreeService::placeUser(sponsor, L|R)
     └─ UserRegisteredNotification(mail)
  → Sanctum token
```

Rules:

- `name`, alpha-dash unique active `login`, unique active email/phone, password min 8 confirmed.
- Referral aliases: `referral_code`, `ref`, `sponsor_code`; branch aliases `left/right` нормализуются в `L/R`.
- Sponsor exists only if non-deleted, active, role user/super_admin. Для public referral controller/service нужен active public package.
- Общая public registration disabled by default. Registration по валидной sponsor link остаётся доступной.
- Public `package_id` может пройти validation/resolve, но `PartnerRegistrationService` не назначает initial package для public source. Package у нового пользователя остаётся null.
- Без sponsor service не создаёт binary root node. Admin/bulk source способен назначить START/VIP и optionally запустить business effects.

## Sponsor, referral и binary placement

```text
Sponsor U (personal relationship)
  ├─ Referral A: sponsor_id = U
  └─ Referral B: sponsor_id = U

Binary tree U
  ├─ L: Partner X                 ← placement may be A, B or another downline
  │    └─ L: next L placement    ← selected-side chain
  └─ R: Partner Y
       └─ R: next R placement
```

`BinaryTreeService` не ищет первый свободный слот breadth-first. Для выбранной ветки он идёт по одноимённой цепи: при `L` U.L.L.L…, при `R` U.R.R.R… до свободного child. `path` — dot-separated user IDs, `depth` увеличивается. Unique `(parent_id, position)` и unique user node гарантируют бинарность.

Удалённый node не освобождает слот: occupancy проверяет rows с trashed. Operational views исключают `is_active=false`, deleted users/nodes.

## Package system

### Model/fields

`Package` → `packages`: code/name/slug/descriptions/translations, `price`, legacy `pv`, `activity_pv`, `turnover_pv`, referral/binary percents, order/status flags. `activityPv()` fallback-ит на `pv`; `turnoverPv()` fallback-ит на activity; `volumeAmount()` = activity PV × 500.

Public scopes:

- `active()` — status active + `is_active`;
- `activeStarter()` — active START/VIP/ELITE;
- `activeRegistrationStarter()` — START/VIP;
- `Package::PUBLIC_CODES` = START, VIP, ELITE;
- `STARTER_CODES` = START, VIP.

### Seeded packages

| Code | Price | Legacy/activity PV | Turnover PV | Referral field | Binary | Activation/upgrade notes |
|---|---:|---:|---:|---:|---:|---|
| START | 60 000 KZT | 100 | 100 | 10% | 7% | first public activation |
| VIP | 180 000 KZT | 300 | 300 | 10% | 8% | first activation или START→VIP |
| ELITE | 300 000 KZT | 500 | 200 | 10% | 10% | только upgrade/paid auto threshold; unlocks status bonuses |

`BUSINESS` deactivates if present. All three seeded packages upgradeable.

### Activation

Direct user endpoint `POST /api/packages/{package}/activate` is disabled unless `SAFI_USER_PACKAGE_CHANGES_ENABLED=true`. When enabled:

```text
PackageActivationController
  → active START/VIP, user has no package
  → PackageService::upgradePackage
     ├─ current_package_id
     ├─ add activity PV to user
     ├─ turnover PV to all uplines
     ├─ referral base (activityPV × 500) × 10%
     ├─ non-balance package wallet audit
     └─ status/status-bonus/X2 side effects
```

### Upgrade

Exact chain START→VIP→ELITE. Direct endpoint guarded by same flag. START→VIP:

- price difference 120 000;
- activity delta 200;
- bonusable turnover delta по implementation;
- referral = 10% of positive upgrade price difference.

VIP→ELITE:

- price difference 120 000;
- activity delta 200;
- target ELITE flow отмечает turnover non-bonusable;
- referral base resolver returns zero;
- проверяются missed status awards.

Manual admin assignment может применять/не применять business effects. Paid product-order auto-upgrade — отдельный service: thresholds 60k/180k/300k cumulative paid orders; он напрямую меняет package/минимальный total PV, но не распространяет PV/referral.

### Restrictions

- Public user purchase (`SAFI_USER_PACKAGE_PURCHASES_ENABLED`) и direct change имеют два разных flags, оба false по default.
- Payment availability допускает first package START, затем exact chain.
- Status cash/gift awards требуют current ELITE.
- Binary процент берётся из current package row.
- Referral calculation **не использует** package referral field; применяет constant 10%.

## PV system

### Источники PV

1. Package activation/upgrade/manual admin assignment:
   - personal/activity PV: delta к пользователю;
   - turnover PV: package turnover/delta к uplines;
   - ELITE upgrade source non-bonusable.
2. Regular product order:
   - `unit_pv = unit_price / 500`, не `products.pv`;
   - total PV начисляется сразу при order creation;
   - deposit products всегда 0 PV.
3. Admin/demo/recalculation paths могут rebuild cached values, но не создают normal new PV events.

### Запись и propagation

```text
Source user/buyer node
  ↓ parent node: current child position = L/R
Upline #1
  ├─ increment left_pv/right_pv
  ├─ increment total_pv
  ├─ if bonusable: increment remaining side PV
  ├─ create PvTransaction(buyer,upline,branch,source,pv)
  └─ recalculate status
       ↓
next parent ... until root
```

`pv_transactions` — event/audit source: buyer, upline, order, source, branch, PV, bonusable, metadata, optional void. `users.left_pv/right_pv/remaining_*` — cached/current counters.

### Personal PV

Отдельного `personal_pv` column нет. Dashboard `getUserPersonalPv()` derives from current package activity PV; `UserResource` publishes aliases. `users.total_pv` увеличивается и personal add, и branch propagation; maintenance rebuild определяет total как own package activity + branches.

### Remaining и already used

During accrual bonusable PV increments remaining. Binary calculation не доверяет только remaining fields: оно строит available по PV ledger и вычитает `used_left_pv/used_right_pv` прошлых runs. Затем сохраняет carry в remaining fields. Таким образом authoritative history для binary — PV transactions + runs, а remaining columns — operational cache.

### Exclusions

Binary snapshot исключает:

- voided PV;
- PV buyer с deleted/inactive account;
- buyer без active public MLM package;
- `is_bonusable=false`, включая выделенный ELITE non-bonusable upgrade flow.

### Recalculation commands

- `safi:recalculate-branch-pv`: rebuild left/right/total cache; не remaining, no money.
- `safi:recalculate-mlm`: rebuild branch/remaining/total/status from active ledger; service path заявлен и реализован без new bonuses.
- `mlm:recalculate-statuses`: вызывает полноценный StatusService, поэтому может создать status/X2 money.
- deletion cleanup voids PV and rebuilds affected uplines.

Подробнее: [COMMANDS.md](./COMMANDS.md).

## Referral bonus

Кто sponsor: active `User` из `referral.sponsor_id`; current implemented callers — package activation/upgrade/manual registration-assignment flows.

Когда:

- START/VIP activation: base `activityPv × 500`;
- START→VIP upgrade: base price difference;
- ELITE activation/upgrade: base 0;
- admin initial assignment — только если `pay_referral_bonus`/business effects разрешены.

Расчёт: `base × 10%`, constant `BonusService::REFERRAL_PERCENT`.

```text
Referral source
  → eligible base resolver
  → BonusService::accrueReferralBonus
  → sponsor main Wallet credit(type=referral_bonus)
  → BonusTransaction(type=referral, source_user_id)
  → BonusAccruedNotification(mail + database)
```

Metadata: base amount, 10%, percent source, referral/sponsor package context, caller source и optional idempotency key. Referral wallet row использует morph source на созданный `BonusTransaction`; referral ID отдельно хранится в wallet metadata и `bonus_transactions.source_user_id`. Поле `source_order_id` есть в schema, но current referral callers его не устанавливают. `ReferralBonusBaseResolver::resolveForProductOrder()` существует без найденного caller.

Idempotency работает только когда caller передаёт key; service ищет existing referral bonus по metadata key.

## Binary bonus

### Eligibility

- User active MLM partner, active current START/VIP/ELITE.
- В каждой root binary branch есть минимум один **лично приглашённый** active MLM partner (`sponsor_id=user.id`). Он может располагаться глубже branch.
- Available bonusable PV > 0 в обеих ветках.
- Для requested/default period не существует блокирующего run.

### Calculation

```text
left pv_transactions                         right pv_transactions
  - voided                                     - voided
  - inactive/deleted buyers                    - inactive/deleted buyers
  - non-active package buyers                  - non-active package buyers
  - non-bonusable                              - non-bonusable
  - used PV prior runs                         - used PV prior runs
          │                                            │
          └──────── left_available / right_available ──┘
                              ↓
                 matched = min(left, right)
                              ↓
             money_base = matched × 500 KZT
                              ↓
        total = money_base × package.binary_percent
                              ↓
               ┌──────────────┴─────────────┐
               │                            │
          main 90%                     deposit 10%
```

Weak leg и matched в normal case совпадают по available PV. Raw weak leg volume может отличаться из-за excluded/prior-used components.

### Carry-over

`carry_left = left_available - matched`; аналогично right. Run хранит used/carry. User remaining columns обновляются этими carry values. Future run вычитает сумму used PV всех prior completed/pending runs из ledger-derived eligible total; carry остаётся available.

### Percent и PV value

- START 7%, VIP 8%, ELITE 10% из database package.
- Money value = fixed `500 KZT` за PV в `BonusService`, совпадает с `Product::PV_MONEY_RATE`.

### Реальный пример

START user; eligible left=300 PV, right=200 PV:

1. matched = 200 PV;
2. money base = 200 × 500 = 100 000 KZT;
3. binary = 100 000 × 7% = 7 000 KZT;
4. main = 6 300 KZT;
5. deposit = 700 KZT;
6. carry left=100 PV, right=0.

Created rows:

- `binary_bonus_runs`: period, weak/used/carry, total amount, completed;
- `bonus_transactions`: type binary, total/matched PV and metadata;
- main `wallet_transactions`: type `binary_bonus_main`, 6300;
- deposit transaction: `binary_bonus_deposit`, 700;
- `binary_bonus_calculations`: exact snapshot/components/rate/split;
- database+mail notification tied to primary transaction.

### Period/schedule

Period resolver формирует half-month boundaries вокруг 1-го/15-го 03:00 Asia/Tashkent. Scheduler запускается именно в эти моменты. Unique period index, cache lock, service lookup защищают duplicate. Manual `calculateBinaryBonus()` default period начинается `now` и заканчивается через 15 days; mutable admin recalc корректирует current run.

### Recalculation

Admin single/all recalc может изменить уже созданный amount. Service сравнивает ledger legs и creates adjustment credits/debits, updates existing run/bonus and adds calculation audit. Это не то же самое, что immutable scheduler run. Повторный recalc идёт от current actual state, но остаётся финансовой HIGH RISK operation.

### Commands

- `safi:binary-recalculate-all`: production scheduler path, dry-run/force/scheduled guards.
- `safi:binary-recalculation:rollback-batch`: manifest/fingerprint governed incident rollback; может restore/delete/replay ledger.

Подробные options/idempotency: [COMMANDS.md](./COMMANDS.md#php-artisan-safibinary-recalculate-all).

## Status system

Status определяется исключительно current weak leg branch PV, не `users.total_pv` и не personal PV.

| Status | Weak leg PV | Reward currently seeded |
|---|---:|---|
| user | <1 000 | — |
| manager | 1 000 | 2 products, noncash marker |
| leader | 2 500 | cosmetics set, noncash marker |
| director | 5 000 | 250 000 KZT cash |
| bronze_director | 10 000 | trip + 100 000; compensation field 400 000 |
| silver_director | 25 000 | foreign trip + 250 000; compensation field 750 000 |
| gold_director | 50 000 | 5 000 000 KZT |
| platinum_director | 100 000 | 6 000 000 KZT |
| emerald_director | 250 000 | 10 000 000 KZT |
| diamond_director | 500 000 | 20 000 000 KZT |

Status itself can move down when weak leg falls. Notification only on upward move. Threshold comparison is cumulative/current branch ledger, not per-period.

Status reward:

- requires current ELITE;
- all active definitions with threshold `<= weak leg` are eligible, so late ELITE can receive missed lower markers/bonuses;
- unique user+definition marker makes award once-only;
- cash credits main; noncash only stores marker/reward text;
- compensation alternate selection/payout is **Не найдено в текущей реализации**.

Tables: definitions, user markers, bonus/wallet transactions, users, notifications.

## X2 bonus

Seeded definitions:

| Code | Direct referrals condition | Distribution | Reward |
|---|---|---|---|
| `five_directors` | 5 first-line referrals rank ≥ director | ≥2 L and ≥2 R | trip, no cash |
| `five_gold_directors` | 5 rank ≥ gold_director | ≥2 L and ≥2 R | 5 000 000 KZT |
| `five_diamond_directors` | 5 rank ≥ diamond_director | ≥2 L and ≥2 R | 20 000 000 KZT |

Package requirement для самого sponsor в X2Service отдельно не проверяется; нужен active account. Qualification вызывается, когда status одного referral пересчитывается, и оценивает всех first-line referrals.

Direct means `sponsor_id`, branch side — actual binary root side. Примеры: 3L+2R qualifies; 4L+1R does not.

Storage/idempotency: unique `user_x2_bonuses(user_id, definition_id)`. Cash: `bonus_transactions.bonus_type=bonus_x2`, main wallet transaction `x2_bonus`. Noncash: marker only. Notification mail+database.

## Wallets, withdrawal, deposit purchase и cashback

### Wallet roles

- `main`: referral, binary 90%, status, X2, cashback, transfers, withdrawal.
- `deposit`: binary 10%, deposit products and 50/50 order deposit part; internal top-up main→deposit.
- `bonus`: создаётся при registration, но real credit/debit callers для bonus wallet не найдены.

### Deposit purchase

```text
deposit product(s)
  → validate deposit-only + stock
  → paid Order, PV=0
  → debit deposit wallet 100%
  → cashback = order amount × 20%
  → credit main wallet
```

Cashback bonus type `cashback`, wallet type обычно `deposit_purchase_cashback`; source/debit transaction и order IDs сохраняются. Idempotency использует source transaction key.

### Withdrawal

```text
main.balance → request: subtract + hold
  → pending withdrawal
     ├─ approve: hold decreases, balance unchanged, neutral audit
     └─ reject: hold decreases, balance restored, credit audit
```

Подробнее: [WALLET.md](./WALLET.md).

## Product order и MLM

Regular products:

```text
Order create
  → stock decrement
  → total PV = Σ(price/500 × quantity)
  → PvService to uplines
  → later TipTopPay intent/pay
```

Важное фактическое поведение: PV создаётся до payment success; referral bonus для regular product order не создаётся. Admin order cancellation возвращает stock, но не void-ит PV rows; TipTopPay cancel/refund также не вызывает stock/PV reversal. ProductSeeder `products.pv` (35/30/20) виден API, но turnover calculation фактически для seeded products равен цене/500: serum 42 PV, collagen 36 PV, Omega 25 PV. Deposit tea PV в order = 0 независимо от seeded 8.

Paid regular cumulative totals могут auto-upgrade package, но auto-upgrade не вызывает стандартный PV/referral package flow.

Фактические строки `ProductSeeder`:

| SKU / product | Price KZT | Stored `products.pv` | Stock | Type | PV used by order |
|---|---:|---:|---:|---|---:|
| `SAFI-FACE-SERUM` / Safi Face Serum | 21 000 | 35 | 75 | regular | 42 |
| `SAFI-COLLAGEN` / Safi Collagen | 18 000 | 30 | 90 | regular | 36 |
| `SAFI-OMEGA-3` / Safi Omega 3 | 12 500 | 20 | 140 | regular | 25 |
| `SAFI-DETOX-TEA` / Safi Detox Tea | 4 500 | 8 | 180 | deposit-only | 0 |

Seeder выполняет `updateOrCreate` по SKU и выставляет `status=active`; повтор обновляет seeded catalog values. Названия/описания/benefits/composition/usage имеют ru/kk/en/mn variants. Product admin CRUD при изменении цены рассчитывает informational `pv` из цены, однако checkout всё равно использует `price/500` для regular products.

## End-to-end diagrams

### Registration

```text
User submits referral link
  ↓
RegisterRequest normalization/validation
  ↓
Sponsor eligibility + active package
  ↓
User/Profile + 3 Wallets
  ↓
Binary placement on explicit L/R side-chain
  ↓
Package remains null for public registration
  ↓
Mail + Sanctum token
```

### Package activation

```text
User/Admin/paid package callback
  ↓
PackageService
  ├─ package assignment
  ├─ personal activity PV
  ├─ turnover PV → uplines → branch PV ledger
  ├─ START/VIP referral bonus → sponsor main wallet
  ├─ non-balance package audit
  └─ status → status bonus / sponsor X2 check
```

### Binary

```text
Eligible PV ledger L/R
  ↓ exclude voided/inactive/non-bonusable/prior used
Weak/Matched PV
  ↓ × 500 × current package rate
BonusTransaction + Run + Calculation
  ├─ 90% main
  └─ 10% deposit
  ↓
carry saved for next period
```

### Status/X2

```text
PV reaches upline
  → StatusService weak leg
  → status update/notification
  → if ELITE: StatusBonusService once-only award
  → sponsor X2BonusService
       → first-line statuses + binary distribution
       → once-only marker
       → optional main wallet cash
```

## Что отсутствует

- Multi-level/unilevel referral commissions: найден только single sponsor referral.
- Separate bonus wallet routing: не найдено.
- Automatic cash compensation selection for trip rewards: не найдено.
- Generalized commission rules engine: проценты/thresholds partly constants, partly seeded tables/packages.
- Events/listeners/jobs для MLM: не найдено; orchestration synchronous/direct.

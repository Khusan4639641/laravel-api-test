# MLM Balance Rule Update

Date: 2026-06-08
Scope: package purchase, package upgrade and manual package assignment transaction/balance behavior.

## Current Rule

Package purchase, package upgrade and manual package assignment must create a transaction that records the package operation amount, but this transaction does not credit the user's wallet balance.

Definitions:

- `PV` remains an activity/turnover metric and is not equal to package price.
- `package.price` is the package operation amount shown in transaction history.
- Package transactions have `affects_balance = false` and `direction = neutral`.
- Package transactions must not increase `balance`, `available_balance`, `withdrawable_balance` or `total_earned`.
- Wallet money is earned only from balance-affecting income such as referral, binary main, status, X2, cashback or explicit manual wallet credit.

## Package Matrix

| Package | package price / transaction amount | activity PV | upline turnover PV | user balance credit |
|---|---:|---:|---:|---:|
| START | 60 000 KZT | 100 PV | 100 PV | 0 KZT |
| VIP | 180 000 KZT | 300 PV | 300 PV | 0 KZT |
| ELITE | 300 000 KZT | 500 PV | 200 PV | 0 KZT |

## Activation And Upgrade

First package activation:

- START creates `package_activation` transaction for `60 000 KZT`.
- VIP creates `package_activation` transaction for `180 000 KZT`.
- ELITE remains unavailable as a first user activation unless a separate business rule allows it.

Package upgrade creates `package_upgrade` transaction for the price difference:

| Upgrade | transaction amount | additional activity PV | wallet credit |
|---|---:|---:|---:|
| START -> VIP | 120 000 KZT | 200 PV | 0 KZT |
| VIP -> ELITE | 120 000 KZT | 200 PV | 0 KZT |

## Manual Admin Assignment

`PATCH /api/admin/partners/{user}/package` supports `apply_business_effects`:

- `apply_business_effects=true`: change package, apply personal PV/upline turnover PV, create `package_assignment` transaction for full package price, do not credit wallet balance.
- `apply_business_effects=false`: change package only; do not apply PV, turnover or package transaction.
- Reassigning the same package does not duplicate transaction or PV effects.

## Transactions

Package operation transactions are stored as wallet transactions for history/audit:

- `package_assignment`
- `package_activation`
- `package_upgrade`

Transaction fields:

- `amount` = package price for activation/admin assignment, price difference for upgrade.
- `direction` = `neutral`.
- `affects_balance` = `false`.
- `balance_before` = current wallet balance.
- `balance_after` = same current wallet balance.

Transaction metadata includes:

- `package_id`
- `package_code`
- `package_name`
- `package_price`
- `activity_pv`
- `turnover_pv`
- `affects_balance = false`
- `source`
- `from_package_*` fields for upgrades or package changes where applicable
- `actor_id` / `actor_role` for admin assignment where available

## PV Structure Rule Remains Separate

Package operation transaction amount is money turnover/history only. It does not mean package price is PV.

Required behavior:

- Buyer receives personal/package PV.
- Buyer does not receive package transaction amount in main wallet.
- Buyer `left_pv` / `right_pv` do not increase from their own package purchase.
- Uplines receive turnover PV in the correct branch.
- ELITE contributes 200 turnover PV to uplines, while buyer personal PV is 500.

## API/UI Impact

`GET /api/admin/partners/{id}`, `GET /api/admin/partners`, dashboard overview and transaction endpoints must show:

- `available_balance` from main wallet money excluding package operations.
- `total_earned` from balance-affecting income only.
- `package_activity_pv` as package/personal PV.
- `left_pv` and `right_pv` as structure/team PV, not buyer's own package operation.
- Recent transactions include package operation rows with `affects_balance=false`.

After manual START assignment with effects and no bonuses:

- Current package: `START`.
- Personal/package PV: `100 PV`.
- Available balance: `0 KZT`.
- Total earned: `0 KZT`.
- Recent transactions include `package_assignment` for `60 000 KZT`.

## Admin Transactions Summary

`/api/admin/transactions` returns summary cards:

- `operation_turnover`: all completed transaction amounts, including package operations.
- `total_credited`: balance-affecting completed income only; excludes package operations.
- `total_paid`: approved/completed payout transactions.
- `pending`: pending withdrawals plus pending binary where applicable.
- `deferred_deposit`: deposit/deferred transactions such as binary deposit wallet entries.

## Related Files

- `app/Services/PackageService.php`
- `app/Services/PvService.php`
- `app/Services/WalletService.php`
- `app/Http/Controllers/Api/Admin/TransactionController.php`
- `app/Http/Controllers/Api/Dashboard/TransactionController.php`
- `app/Http/Controllers/Api/Admin/PartnerController.php`
- `app/Http/Resources/UserResource.php`
- `app/Http/Resources/WalletTransactionResource.php`
- `app/Support/SystemLabel.php`
- `resources/js/safi/pages/admin/AdminTransactions.tsx`
- `resources/js/safi/pages/dashboard/Transactions.tsx`
- `resources/js/safi/lib/systemLabels.ts`

## Related Tests

- `tests/Feature/Admin/AdminPackageAssignmentTransactionTest.php`
- `tests/Feature/PackageActivationTransactionTest.php`
- `tests/Feature/AdminTransactionsSummaryTest.php`
- `tests/Feature/DashboardTransactionsTest.php`
- `tests/Feature/Admin/AdminManualPackageAssignmentTest.php`
- `tests/Feature/Admin/AdminPartnerDetailBalanceTest.php`
- `tests/Feature/AdminPartnersApiTest.php`
- `tests/Feature/PackageActivationTest.php`
- `tests/Feature/PackageUpgradeTest.php`
- `tests/Feature/DashboardOverviewBalanceTest.php`
- `tests/Feature/DashboardEarningsSummaryTest.php`

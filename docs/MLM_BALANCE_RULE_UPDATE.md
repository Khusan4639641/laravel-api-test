# MLM Balance Rule Update

Date: 2026-06-08
Scope: package purchase, package upgrade and manual package assignment balance behavior.

## Current Rule

Package purchase or package change credits the buyer's main wallet by package activity PV converted to money:

```text
credit_amount = credited_activity_pv * 500 KZT
```

Definitions:

- `PV` remains an activity/turnover metric and is not equal to package price.
- `package.price` must not be used as PV or as the wallet credit amount.
- `balance`, `available_balance`, `withdrawable_balance` and `total_earned` are real wallet money.
- Package credit is real wallet money and must appear in transaction history.
- The old transaction type `package_activity_credit` is not used; current transaction types are listed below.

## Package Matrix

| Package | price | activity PV | buyer credit | upline turnover PV |
|---|---:|---:|---:|---:|
| START | 60 000 KZT | 100 PV | 50 000 KZT | 100 PV |
| VIP | 180 000 KZT | 300 PV | 150 000 KZT | 300 PV |
| ELITE | 300 000 KZT | 500 PV | 250 000 KZT | 200 PV |

## Activation And Upgrade

First package activation:

- START credits `100 PV * 500 = 50 000 KZT`.
- VIP credits `300 PV * 500 = 150 000 KZT`.
- ELITE remains unavailable as a first user activation unless a separate business rule allows it.

Package upgrade credits only the delta between old and new activity PV:

| Upgrade | delta PV | credit |
|---|---:|---:|
| START -> VIP | 200 PV | 100 000 KZT |
| VIP -> ELITE | 200 PV | 100 000 KZT |
| START -> ELITE, if allowed | 400 PV | 200 000 KZT |

Delta credit prevents repeated full-package money credits on package changes.

## Manual Admin Assignment

`PATCH /api/admin/partners/{user}/package` supports `apply_business_effects`:

- `apply_business_effects=true`: change package, apply personal PV/upline turnover PV, credit main wallet by credited activity PV * 500, create wallet transaction.
- `apply_business_effects=false`: change package only; do not apply PV, wallet credit or package credit transaction.
- Reassigning the same package does not duplicate credit.

## Transactions

Package money credits are stored as wallet transactions:

- `package_activation_credit`
- `package_upgrade_credit`
- `admin_package_assignment_credit`

Transaction metadata must include:

- `package_id`
- `package_code`
- `package_name`
- `package_price`
- `activity_pv`
- `credited_pv`
- `pv_rate = 500`
- `credit_amount`
- `source`
- `from_package_*` fields for upgrades or package changes where applicable
- `actor_id` / `actor_role` for admin assignment where available

## PV Structure Rule Remains Separate

Money is credited to the buyer's main wallet, but the buyer's own purchase still must not add PV to their own left/right branches.

Required behavior:

- Buyer receives personal/package PV.
- Buyer receives main wallet credit from credited activity PV * 500.
- Buyer `left_pv` / `right_pv` do not increase from their own package purchase.
- Uplines receive turnover PV in the correct branch.
- ELITE contributes 200 turnover PV to uplines, while buyer personal PV is 500.

## API/UI Impact

`GET /api/admin/partners/{id}`, `GET /api/admin/partners`, dashboard overview and transaction endpoints must show:

- `available_balance` from main wallet money including package credits.
- `total_earned` including package credit wallet transactions.
- `package_activity_pv` as package/personal PV.
- `package_activity_amount` / `pv_amount` as `activity_pv * 500`.
- `left_pv` and `right_pv` as structure/team PV, not buyer's own package credit.
- Recent transactions include the package activation/upgrade/admin assignment credit.

After manual START assignment with effects and no other bonuses:

- Personal/package PV: `100 PV`.
- Available balance: `50 000 KZT`.
- Total earned: `50 000 KZT`.
- Recent transactions include `admin_package_assignment_credit` for `50 000 KZT`.

## Related Files

- `app/Services/PackageService.php`
- `app/Services/PvService.php`
- `app/Services/EarningsSummaryService.php`
- `app/Http/Controllers/Api/Admin/PartnerController.php`
- `app/Http/Resources/UserResource.php`
- `app/Support/SystemLabel.php`
- `resources/js/safi/lib/systemLabels.ts`

## Related Tests

- `tests/Feature/PackageActivationBalanceCreditTest.php`
- `tests/Feature/Admin/AdminManualPackageAssignmentTest.php`
- `tests/Feature/Admin/AdminPartnerDetailBalanceTest.php`
- `tests/Feature/AdminPartnersApiTest.php`
- `tests/Feature/PackageActivationTest.php`
- `tests/Feature/PackageUpgradeTest.php`
- `tests/Feature/DashboardOverviewBalanceTest.php`
- `tests/Feature/DashboardEarningsSummaryTest.php`

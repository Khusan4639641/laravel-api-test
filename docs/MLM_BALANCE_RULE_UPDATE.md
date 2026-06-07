# MLM Balance Rule Update

Date: 2026-06-07
Scope: package purchase/manual assignment balance behavior.

## Current Rule

Package purchase or manual package assignment does not credit money to the buyer's wallet balance.
Package PV and money balance are separate:

- `PV` is an activity/turnover metric.
- `balance`, `available_balance`, `withdrawable_balance` and `total_earned` are real money only.
- `activity_pv * 500` must not be used as buyer withdrawable balance.
- `package.price` must not be used as PV.
- Do not create buyer wallet transactions with type `package_activity_credit`.

## Package Matrix

| Package | price | personal/package PV | upline turnover PV | buyer balance without bonuses | buyer total earned without bonuses |
|---|---:|---:|---:|---:|---:|
| START | 60 000 KZT | 100 PV | 100 PV | 0 KZT | 0 KZT |
| VIP | 180 000 KZT | 300 PV | 300 PV | 0 KZT | 0 KZT |
| ELITE | 300 000 KZT | 500 PV | 200 PV | 0 KZT | 0 KZT |

## Superseded Rule

The 2026-06-05 rule that manual package assignment could create `package_activity_credit = activity_pv * 500 KZT` is superseded.
That behavior was removed because it mixed package PV with buyer wallet money.

## API/UI Impact

`GET /api/admin/partners/{id}` and `GET /api/admin/partners` must show:

- `available_balance` from real wallet money only.
- `total_earned` from real wallet money only.
- `package_activity_pv` as package/personal PV.
- `left_pv` and `right_pv` as structure/team PV.
- No package-derived 50 000/150 000/250 000 KZT in finance cards.

After manual START assignment with effects and no bonuses:

- Personal/package PV: `100 PV`.
- Available balance: `0 KZT`.
- Total earned: `0 KZT`.
- Recent transactions do not include `package_activity_credit`.

## Related Files

- `app/Services/PackageService.php`
- `app/Services/PvService.php`
- `app/Http/Controllers/Api/Admin/PartnerController.php`
- `app/Http/Resources/UserResource.php`
- `resources/js/safi/pages/admin/AdminPartners.tsx`
- `resources/js/safi/pages/admin/AdminPartnerDetail.tsx`

## Related Tests

- `tests/Feature/Admin/AdminManualPackageAssignmentTest.php`
- `tests/Feature/Admin/AdminPartnerDetailBalanceTest.php`
- `tests/Feature/AdminPartnersApiTest.php`
- `tests/Feature/PackageActivationTest.php`
- `tests/Feature/PackageUpgradeTest.php`
- `tests/Feature/DashboardOverviewBalanceTest.php`

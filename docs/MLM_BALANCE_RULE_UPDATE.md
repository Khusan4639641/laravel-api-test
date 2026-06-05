# MLM Balance Rule Update

Date: 2026-06-05  
Scope: admin manual package assignment and partner detail balances.

## Changed Rule

Previous rule:
- Package PV was not added to wallet balance.
- `activity_pv * 500` was treated only as a calculated PV amount, not as available money.

Current rule:
- When Super Admin manually assigns START/VIP/ELITE with `apply_business_effects = true`, the system creates a real wallet credit transaction for the package activity amount.
- The credit is included in partner detail cards `Доступно` and `Всего заработал` because it is stored as a completed wallet transaction.
- If `apply_business_effects = false`, only `current_package_id` changes. PV, turnover, wallet balance and transactions do not change.

## Formula

`package_activity_credit = activity_pv * 500 KZT`

| Package | price | activity_pv | credit amount |
|---|---:|---:|---:|
| START | 60 000 KZT | 100 PV | 50 000 KZT |
| VIP | 180 000 KZT | 300 PV | 150 000 KZT |
| ELITE | 300 000 KZT | 500 PV | 250 000 KZT |

## Non-Negotiable Constraints

- PV is still not equal to package price.
- `package.price` must not be used as PV or as the package activity credit.
- START price is 60 000 KZT, but the credit amount is 50 000 KZT.
- VIP price is 180 000 KZT, but the credit amount is 150 000 KZT.
- ELITE price is 300 000 KZT, but the credit amount is 250 000 KZT.
- Credit transaction type: `package_activity_credit`.
- Transaction description: `Manual package assignment: START|VIP|ELITE`.
- Transaction status: `completed`.

## API/UI Impact

`GET /api/admin/partners/{id}` returns wallet-derived values:
- `available_balance`
- `total_earned`
- `wallet_balance`
- `package_activity_pv`
- `package_activity_amount`
- `recent_transactions`

`/admin/partners/{id}` cards must display:
- `Доступно` -> `available_balance`
- `Всего заработал` -> `total_earned`

After manual START assignment with effects and zero existing balance:
- Personal PV: `100 PV`
- Available: `50 000 KZT`
- Total earned: `50 000 KZT`
- Recent transactions include `package_activity_credit` for `50 000 KZT`.

## Related Files

- `app/Services/PackageService.php`
- `app/Services/WalletService.php`
- `app/Http/Controllers/Api/Admin/PartnerController.php`
- `app/Http/Resources/UserResource.php`
- `app/Http/Resources/WalletTransactionResource.php`
- `resources/js/safi/pages/admin/AdminPartnerDetail.tsx`
- `docs/MLM_TZ_FINAL_CHECKLIST.md`

## Related Tests

- `tests/Feature/Admin/AdminManualPackageAssignmentTest.php`
- `tests/Feature/Admin/AdminPartnerDetailBalanceTest.php`
- `tests/Feature/Admin/AdminPartnerTransactionsTest.php`
- `tests/Feature/AdminPartnersApiTest.php`

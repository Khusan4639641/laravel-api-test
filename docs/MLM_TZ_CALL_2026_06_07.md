# MLM TZ Call Requirements - 2026-06-07

Project: Safi Life  
Date: 2026-06-07  
Source: https://tldv.io/app/meetings/6a225f06bf0478001332fb52/?transcript=true&video=true  
Scope: requirements clarified after the latest MLM call.

## Priority

This document records the latest business decisions from the 2026-06-07 call.
If older project documents or implementation notes conflict with this file, this file has priority for the next implementation stage.

Important conflict to resolve:
- 2026-06-08 business update supersedes the 2026-06-07 package balance rule.
- Current rule: buying, upgrading or assigning a package with business effects credits the buyer's main wallet by credited activity PV * 500 KZT.

## 1. Package Purchase And Assignment

Buying, upgrading or assigning a package with business effects credits the buyer's main wallet.
The credit is calculated from activity PV, not from package price:

```text
credit_amount = credited_activity_pv * 500 KZT
```

| Package | personal/package PV | first activation/admin assignment credit |
|---|---:|---:|
| START | 100 PV | 50 000 KZT |
| VIP | 300 PV | 150 000 KZT |
| ELITE | 500 PV | 250 000 KZT |

Required behavior:
- START purchase or assignment gives `personal/package PV = 100 PV` and credits `50 000 KZT`.
- VIP purchase or assignment gives `personal/package PV = 300 PV` and credits `150 000 KZT`.
- ELITE admin assignment gives `personal/package PV = 500 PV` and credits `250 000 KZT`; ELITE remains unavailable as a first user activation unless separately allowed.
- Upgrade credits only delta PV * 500: START -> VIP credits `100 000 KZT`, VIP -> ELITE credits `100 000 KZT`.
- `apply_business_effects=false` for admin assignment changes only the package and does not credit money or apply PV effects.
- Create package credit wallet transactions: `package_activation_credit`, `package_upgrade_credit` or `admin_package_assignment_credit`.

## 2. PV And Balance Must Be Separate

PV and wallet balance are different entities and must not be mixed.

Definitions:
- `PV` is a turnover/activity metric.
- `balance` is real wallet money, including package credits and bonuses.

Implementation implication:
- Package PV can affect personal activity, upline turnover, statuses and binary calculation inputs.
- Wallet balance can change through package credits, bonus payouts, approved financial operations, cashback or other explicitly monetary flows.
- UI cards must display actual wallet credits, but must not treat package price as PV or money.

## 3. Package And Product PV Accrual

PV from a package or product purchase must be propagated to uplines, not to the buyer's own left/right branches.

Required behavior:
- Buyer receives own personal/package PV where applicable.
- Buyer receives package money credit where applicable.
- Buyer does not receive the purchase PV into their own `left_pv` or `right_pv`.
- Upline partners receive the purchase PV in the correct left/right branch according to binary placement.
- The same separation applies to package purchases, package assignments with business effects, and product orders that carry PV.

## 4. Status And Progress Calculation

Status and progress must be calculated by the weak leg, not by total PV.

Required behavior:
- Use `min(left_pv, right_pv)` as the status/progress base.
- Do not use `left_pv + right_pv` for status qualification.
- Do not let a strong leg alone qualify a user for a higher status.
- Dashboard/admin progress indicators must visually reflect weak-leg progress.

## 5. Withdrawal Confirmation

Fix double deduction when confirming a withdrawal request.

Required behavior:
- Money must be reserved/deducted only once in the withdrawal lifecycle.
- Confirming an already reserved withdrawal must not subtract the amount a second time.
- Repeated confirmation attempts must be idempotent or rejected safely.
- Tests must cover the withdrawal confirmation flow and prevent double deduction regression.

## 6. Binary Bonus Calculation Button

The binary bonus calculation button must be available for Super Admin.

Required behavior:
- Super Admin can trigger binary bonus calculation from the admin interface.
- The action must be protected from non-authorized roles.
- The UI must make it clear which period is being calculated or was last calculated.

## 7. Binary Bonus Rules

Binary bonus rules clarified by the call:
- Calculation base: weak leg only, `min(left_pv, right_pv)`.
- Period length: 15 days.
- Already used PV from previous periods must not be recalculated again.
- Binary payout split: `90%` to main wallet and `10%` to deposit wallet.

Required behavior:
- Store used PV per binary calculation period.
- Carry unused strong-leg PV forward if business logic requires carryover.
- Prevent duplicate calculation for the same period.
- Create separate wallet transactions for main wallet and deposit wallet portions.
- Binary bonus must move to available balance only after the period calculation is confirmed/processed.

## 8. Support And Floating Contacts

Support through the site must remain available, but floating external contact buttons must be removed.

Required behavior:
- Keep or add on-site support functionality.
- Remove floating WhatsApp button from all pages.
- Remove floating Telegram button from all pages.
- Remove floating phone button from all pages.
- Ensure there are no duplicate floating contact widgets in public, auth, dashboard or admin layouts.

## 9. Orders

Orders need more delivery and customer information.

Required fields:
- Delivery address.
- Recipient phone.
- User data in order details.
- Clear order statuses.

Required behavior:
- Checkout/order creation must collect delivery address and recipient phone where delivery is required.
- Admin order detail must show user/customer data clearly.
- Order status labels must be understandable to admins and users.
- Order status changes must remain auditable and should not rely on ambiguous raw enum names in the UI.

## 10. Products

Admin product management must support full product data and file image upload.

Required product fields:
- Product photo uploaded as a file by admin.
- Name.
- Description.
- Price.
- PV.
- Stock quantity.
- Active/inactive flag.

Translation requirement:
- `inactive` must be translated as `неактивно` in Russian UI.

Required behavior:
- Do not require admins to paste image URLs for normal product photo upload.
- Product photo upload must store and display the uploaded file correctly.
- Inactive products must not be presented as available for normal purchase unless explicitly intended by admin behavior.

## 11. Profile Photo Upload

Fix the profile photo upload button.

Required behavior:
- User can choose and upload a profile photo from the profile page.
- The selected image must be sent to the backend and persisted.
- UI should show success/error feedback.
- Existing avatar/profile image display must update after upload.

## 12. Status Translations

Status names must be translated by language.

Required behavior:
- Do not show raw internal status codes to users.
- Status names must come from i18n dictionaries or another localization-safe source.
- Russian, Kazakh and other supported locales must display the correct localized status names.
- Admin and dashboard pages must use the same translation source where possible.

## 13. Header Navigation

For an authenticated user, clicking `Главная` must not send the user to login.

Allowed behavior:
- Route `Главная` to dashboard for authenticated users.
- Or show `Личный кабинет` instead of `Главная` for authenticated users.

Required behavior:
- Guest users can still navigate to the public home/login flow as intended.
- Authenticated users must not be forced through login when they already have a valid session.

## 14. Binary Text Copy

Replace the old text about bonus payouts in 14 days with binary-specific wording.

Required wording meaning:
- `Расчёт бинарного бонуса каждые 15 дней`

Required behavior:
- Remove or replace wording like `выплата бонусов 14 дней` where it describes binary bonus timing.
- Use the 15-day binary calculation period consistently in UI and documentation.

## 15. Pending Binary Metric

Add a block or metric for pending binary bonus.

Suggested label:
- `Бинар в ожидании`

Required behavior:
- Show the estimated/accrued binary bonus amount before it becomes available balance.
- The pending amount must not be mixed with available wallet balance.
- After the period calculation is confirmed/processed, move the amount into available balance according to the 90/10 wallet split.
- The dashboard should make the difference between pending binary and available balance clear.

## Implementation Checklist

- Update package assignment and purchase logic so package PV never becomes buyer wallet money by itself.
- Update balance cards and partner details to separate PV metrics from wallet money.
- Verify buyer PV does not increment buyer's own left/right branches.
- Verify uplines receive PV in the correct branch.
- Recheck status/progress services and UI for weak-leg calculation.
- Fix withdrawal confirmation idempotency and double deduction risk.
- Ensure Super Admin binary calculation action exists and is permission-protected.
- Recheck binary period, used PV tracking, duplicate prevention and 90/10 wallet split.
- Remove floating WhatsApp, Telegram and phone buttons from all layouts/pages.
- Keep on-site support available.
- Extend orders with delivery address, recipient phone, user data and clear statuses.
- Extend admin products with file upload, PV, stock and active/inactive state.
- Fix profile photo upload flow.
- Localize statuses and product active/inactive labels.
- Fix authenticated header navigation.
- Replace 14-day bonus payout copy with 15-day binary calculation copy.
- Add pending binary bonus metric/block.

## Suggested Regression Tests

- Package purchase START/VIP/ELITE sets personal/package PV and leaves buyer balance at `0 KZT` without bonuses.
- Manual package assignment START/VIP/ELITE sets personal/package PV and leaves buyer balance at `0 KZT` without bonuses.
- Buyer purchase PV does not increase buyer `left_pv` or `right_pv`.
- Upline branch PV increases on the correct side.
- Status qualification uses weak leg only.
- Withdrawal confirmation cannot deduct the same amount twice.
- Super Admin can run binary calculation; unauthorized roles cannot.
- Binary calculation uses weak leg, 15-day period, used PV exclusion and 90/10 split.
- Floating external contact buttons are absent from public, auth, dashboard and admin layouts.
- Order creation and admin order detail include delivery address and recipient phone.
- Admin product photo upload persists and displays the uploaded file.
- Product `inactive` label is translated as `неактивно`.
- Profile photo upload updates the displayed avatar.
- Status names are localized in supported languages.
- Authenticated `Главная` navigation does not redirect to login.
- Pending binary metric is separate from available balance and moves after period calculation.

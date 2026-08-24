# I18N Admin And Dashboard Checklist

## Scope

Implemented language handling for:

- RU: Russian
- KZ: Kazakh
- KG: Kyrgyz
- EN: English
- MN: Mongolian

The language switcher now uses `RU | KZ | KG | EN | MN`, persists the selected language in `localStorage`, and normalizes legacy `kk` to `kz`.

## Pages Checked In Code

Public pages:

- `/`
- `/about`
- `/products`
- `/business`
- `/marketing`
- `/how-to-start`
- `/faq`
- `/contacts`
- `/news`
- `/login`
- `/register`

Partner dashboard:

- `/dashboard`
- `/dashboard/structure`
- `/dashboard/transactions`
- `/dashboard/bonuses`
- `/dashboard/package`
- `/dashboard/products`
- `/dashboard/news`
- `/dashboard/profile`

Admin panel:

- `/admin`
- `/admin/partners`
- `/admin/structure`
- `/admin/transactions`
- `/admin/withdrawals`
- `/admin/bonuses`
- `/admin/packages`
- `/admin/statuses`
- `/admin/products`
- `/admin/news`
- `/admin/reports`
- `/admin/settings`

## Fixed

- Added `kz` frontend locale and kept `kk` as a legacy alias.
- Added `kg` support in language normalization, i18next resources, language switcher, API headers, and backend localization.
- Updated frontend `Accept-Language` requests to send normalized `ru/kz/kg/en/mn`.
- Updated backend localized value helper to accept `ru/kz/kg/en/mn`, with `kk -> kz` compatibility.
- Updated permissions API labels and menu labels for `KZ` and `KG`.
- Updated runtime UI translation map for dashboard/admin hardcoded strings, including:
  - Admin Dashboard
  - Quick actions
  - Withdrawal requests
  - Transactions
  - Reports
  - Settings system
  - Total PV
  - Dashboard summary cards
  - Partner structure page labels
  - Bonuses and withdrawals labels
  - Transaction table labels
  - Profile form labels
  - Product/news dashboard empty/loading states
- Updated status service translations for KZ/KG/EN/MN.
- Added backend feature coverage for:
  - `Accept-Language: kz`
  - `Accept-Language: kg`
  - legacy `Accept-Language: kk`
  - localized permissions menu labels.

## Remaining TODO

- Seeded backend content still contains many legacy `kk` translation keys. Runtime and API fallback support them for KZ, but future seed updates should add explicit `kz` and `kg` JSON keys for products, news, FAQ, packages, settings, and any new catalog content.
- Some JSX still contains Russian source strings. They are translated at runtime through the central runtime localizer to avoid broad component rewrites and design regressions. New frontend code should use explicit i18n keys instead of relying on runtime translation.

## Verification

- `npm run build` passed after language changes.
- `php artisan test` must pass before commit.


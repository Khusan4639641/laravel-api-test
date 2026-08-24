# React/Vite frontend

## Runtime architecture

Frontend — одна React 19 SPA в `resources/js/safi`. Blade `resources/views/app.blade.php` содержит `#root` и подключает `resources/js/safi/main.tsx` через `@vite`. `vite.config.js` настраивает Laravel Vite plugin, React, Tailwind CSS 4 и alias `@` → `resources/js/safi`.

```text
resources/views/app.blade.php
  -> main.tsx (StrictMode, i18n, CSS)
  -> App.tsx
  -> CartProvider
  -> AppRouter (BrowserRouter + lazy pages)
     ├─ MainLayout       public site
     ├─ DashboardLayout  role=user
     └─ AdminLayout      support/admin/accountant/super_admin
        -> page
        -> lib/api.ts
        -> Laravel /api route
```

Vite production artifacts уже присутствуют в `public/build`; исходный entry `resources/js/app.js` пуст и не участвует в `vite.config.js`.

## Source map

| Path | Ответственность |
|---|---|
| `main.tsx` | React root, `StrictMode`, i18n и global CSS |
| `App.tsx` | `CartProvider` + router |
| `router/routes.tsx` | все browser paths, lazy imports, redirects and layouts |
| `pages/` | 16 public page modules |
| `pages/dashboard/` | 11 partner dashboard page modules |
| `pages/admin/` | 20 admin/support page modules |
| `components/` | 22 shared/layout/domain component modules |
| `lib/api.ts` | HTTP client, payload/response types, API methods and normalizers |
| `lib/endpoints.ts` | central relative endpoint catalog |
| `lib/permissions.ts` | frontend path/menu permission normalization |
| `context/CartContext.tsx` | only application-wide domain state; localStorage cart |
| `hooks/useTipTopPayWidget.ts` | TipTopPay widget loading/lifecycle |
| `lib/tiptoppay.ts` | external widget script and `window.tiptop` adapter |
| `i18n.ts`, `locales/*.json` | ru/kk/ky/en/mn translations |
| `data/*.ts` | static/fallback/demo content used by selected presentational pages |
| `resources/js/safi/config/features.ts` | `support=true`, `floatingExternalContacts=false` |

Redux/Zustand/MobX или иной store library **не найдены в текущей реализации**. Server state загружается локальными `useEffect`/`useState`; cart хранится в React context.

Отдельные frontend directories `stores/`, `services/` и `types/` отсутствуют: API service functions и TypeScript interfaces объединены в `lib/api.ts`, routes — в `router/routes.tsx`, единственный custom hook — TipTopPay hook.

## API client and authentication

`apiRequest()` добавляет `/api`, `Accept: application/json`, текущий `Accept-Language` и при `auth !== false` bearer token. Token хранится в `localStorage` под ключом `safi_token`; login/register извлекают token из ответа. На `401` token удаляется и browser перенаправляется на `/login` (кроме вызовов с `redirectOnUnauthorized=false`). `FormData` не получает ручной `Content-Type`, чтобы browser сформировал boundary.

`lib/api.ts` содержит нормализаторы snake_case/camelCase для products, packages, statuses, orders, transactions, news, FAQ, transfers и TipTopPay intent. Поэтому page не обязательно видит backend JSON в исходной форме.

Potential issue (MEDIUM): bearer token в `localStorage` доступен любому исполняемому JavaScript. XSS в same origin или скомпрометированный внешний widget способен прочитать token. Фактические CSP headers из server/deployment config **не удалось определить**.

## Public pages

| Browser path | React page | API / backend |
|---|---|---|
| `/` | `HomePage` | public news/packages/products → соответствующие `PublicApi` controllers |
| `/about` | `AboutPage` | статический page content; API не вызывает |
| `/products` | `ProductsPage` | `GET /api/public/products` → `PublicApi\ProductController` |
| `/cart` | `CartPage` | public products/deposit products, TipTopPay status; после auth `OrderController::store`, `TipTopPayIntentController` |
| `/business` | `BusinessPage` | статический content |
| `/marketing`, `/marketing-plan` | `MarketingPlanPage` | public packages/statuses; calculator работает client-side |
| `/how-to-start` | `HowToStartPage` | статический content |
| `/news` | `NewsPage` | public news → `PublicApi\NewsController` |
| `/faq` | `FAQPage` | public FAQs → `PublicApi\FaqController` |
| `/contacts` | `ContactsPage` | public legal settings → `PublicApi\LegalSettingsController` |
| `/login` | `LoginPage` | login, forgot-password request, `/me/permissions`; перенаправление по role config |
| `/register-ref-branch` | `RegisterPage` | `POST /api/register`; sponsor/referral/branch parameters |
| `/register`, `/registration`, `/sign-up` | `Navigate` | redirect to `/login`; самостоятельная форма по этим URL не открывается |
| `/legal` | `LegalPage` | статический navigation page |
| `/payment`, `/legal/{offer,privacy,delivery,refund,requisites}` | `LegalInfoPage` | public legal settings; aliases `/offer`, `/privacy`, `/refund`, `/requisites` redirect here |
| `/payment/success`, `/payment/fail` | `PaymentResultPage` | отображает result; authoritative payment status приходит backend webhook-ом, page его не подтверждает |
| `/admin-preview` | `AdminPreviewPage` | публичная статическая preview, не admin auth |

Public registration endpoint существует, но `SAFI_PUBLIC_REGISTRATION_ENABLED=false` по умолчанию разрешает регистрацию только через валидный sponsor/referral flow. Public package selection в форме не активирует пакет. Подробнее: [MLM.md](./MLM.md#registration).

## Dashboard pages

| Page module | Browser path | Main API calls | Backend/data |
|---|---|---|---|
| `Overview` | `/dashboard` | dashboard overview, public statuses | package/status/PV/wallet/earnings/referrals summaries |
| `Structure` | `/dashboard/structure` | dashboard structure with pagination | `Dashboard\StructureController`, `BinaryNode`, descendants; `StructureTreeCanvas` |
| `Transactions` | `/dashboard/transactions` | dashboard transactions | own `wallet_transactions` filters and pagination |
| `Bonuses` | `/dashboard/bonuses` | overview, earnings, withdrawals, partner transfers, statuses | wallet/bonus/withdrawal/transfer services and tables |
| `PackageStatus` | `/dashboard/package`, `/dashboard/package-status` | overview, dashboard packages, statuses | current package/status and progress; package mutation is disabled by flags by default |
| `Products` | `/dashboard/products` | regular/deposit products, earnings | product catalog and available deposit balance |
| `Orders` | `/dashboard/orders` | own orders, create TipTopPay intent | `orders`, `order_items`, `payments` |
| `OrderDetail` | `/dashboard/orders/:id` | own order, create intent | order ownership + payment fields |
| `News` | `/dashboard/news` | public news | localized `news` rows |
| `Profile` | `/dashboard/profile` | avatar POST with `_method=PATCH` | `Dashboard\ProfileController`, public filesystem |
| `Support` | `/dashboard/support` | tickets/show/create/reply/close/download | support service, messages and attachments |

Dashboard layout requires token, `/me`, `/me/permissions`, normalized role exactly `user`, permitted path and active account response. It also loads six dashboard notifications and renders unread count. API details: [ADMIN.md](./ADMIN.md#user-dashboard-pages).

## Admin/support pages

| Page module | Browser path | Main API |
|---|---|---|
| `AdminHome` | `/admin` | admin overview, or support ticket UI for support role |
| `AdminOverview` | child rendered by home | overview aggregates |
| `AdminPartners` | `/admin/partners` | partner list/search/create and registration packages |
| `AdminPartnersBulkCreate` | `/admin/partners/bulk-create` | bulk-create partners |
| `AdminPartnerDetail` | `/admin/partners/:id` | partner/tree/transactions and status/package/identity/balance/password/delete/binary actions |
| `AdminForgotPassword` | `/admin/forgot-password` | forgot-password requests/reset |
| `AdminStructure` | `/admin/structure` | tree and root orphans |
| `AdminTransactions` | `/admin/transactions` | list/edit/delete wallet transaction |
| `AdminWithdrawals` | `/admin/withdrawals` | list/approve/reject |
| `AdminBonuses` | `/admin/bonuses` | transaction list/edit/delete and binary recalculation |
| `AdminPackages` | `/admin/packages` | package CRUD |
| `AdminStatuses` | `/admin/statuses` | status definitions read |
| `AdminProducts` | `/admin/products` | product CRUD/image upload |
| `AdminOrders` | `/admin/orders` | orders and status mutation |
| `AdminNews` | `/admin/news` | news CRUD/image upload |
| `AdminSupport` | `/admin/support`, `/support`, `/support/tickets` | staff ticket workflow |
| `AdminReports` | `/admin/reports` | report summary; browser-generated CSV export |
| `AdminSettings` | `/admin/settings` | system settings GET/PUT |
| `AdminPaymentReadiness` | `/admin/payment-readiness` | TipTopPay/legal readiness checks |
| `AdminProfile` | `/support/profile` | read-only layout user data |

Exact permissions, controllers and services: [ADMIN.md](./ADMIN.md#admin-pages).

## Components

| Component | Responsibility / consumers |
|---|---|
| `AsyncPartnerSelect` | debounced partner search and selection for transfer/partner forms |
| `admin/AdminLayout` | backoffice auth, role/path guard, header, sidebar, outlet context |
| `admin/AdminSidebar` | permission-derived backoffice navigation/logout |
| `admin/AdminPagination` | reusable admin pagination |
| `admin/ui` | admin-specific badges/cards/helpers |
| `dashboard/DashboardLayout` | user auth guard, current-user normalization, notification dropdown and outlet context |
| `dashboard/Sidebar` | dashboard menu, package/user details and logout |
| `dashboard/PartnerTransferForm` | partner search, validation and transfer submit |
| `dashboard/ui` | dashboard-specific UI helpers |
| `layout/MainLayout` | public Header + outlet + Footer |
| `layout/Header` | public navigation, auth/cart/mobile controls |
| `layout/Footer` | public legal/contact/navigation footer |
| `structure/StructureTreeCanvas` | interactive binary-tree node/connector rendering and sizing |
| `ui/AsyncState` | loading/error/empty presentation |
| `ui/Button`, `Card`, `Container`, `SectionTitle` | design-system primitives |
| `ui/LanguageSwitcher` | changes i18next/current request language |
| `ui/MobileData` | responsive data display primitives |
| `ui/NoTranslate` | protects names/identifiers from browser translation |
| `ui/Toast` | transient feedback |

## Cart and order UI

`CartContext` persists `safi_cart_items`; computes quantities/total; rejects inactive/out-of-stock products, stock overflow, and mixing deposit with regular products. This is client validation only. `CheckoutOrderService` repeats authoritative product/status/stock/payment-strategy checks in a DB transaction.

```text
ProductsPage/Dashboard Products
  -> CartContext (localStorage)
  -> CartPage
  -> POST /api/orders
  -> OrderController
  -> CheckoutOrderService
  -> orders + order_items + stock reservation + MLM effects
  -> POST payment intent
  -> TipTopPay widget
  -> public webhook
  -> payments/order payment state
```

The MLM timing risk in this flow is documented in [MLM.md](./MLM.md#product-order-и-mlm) and [PROJECT_DOCUMENTATION.md](./PROJECT_DOCUMENTATION.md#potential-issues-найденные-чтением-кода).

## TipTopPay frontend

`lib/tiptoppay.ts` dynamically loads `https://widget.tiptoppay.kz/bundles/widget.js`. `useTipTopPayWidget` prevents duplicate callback handling inside one invocation, exposes loading state, creates `new window.tiptop.Widget()` and calls `widget.start(intent)`. The frontend never receives API password or webhook secret; only public terminal id and intent data are returned. See [SERVICES.md](./SERVICES.md#tiptoppayservice).

## Localization

- i18next languages: Russian, Kazakh, Kyrgyz, English, Mongolian.
- API language comes from `Accept-Language`; `SetLocaleFromRequest` resolves it backend-side.
- `LocalizedValue` is used by public/resources/controllers to select localized JSON fields.
- `systemLabels.ts` localizes package/status/order/product system codes.
- Static `data/*.ts` and `adminText.ts` provide page copy/fallbacks; presence of a fallback does not prove matching DB data.

## Styling and assets

Tailwind 4 is loaded through the Vite plugin and `index.css`. Icons: `lucide-react`; animations: `framer-motion`/`motion`; utility merge: `clsx` + `tailwind-merge`. Product/news/avatar/support images are URLs returned by Laravel storage. `public/images/product-placeholder.svg` is the local product fallback.

## Frontend verification commands

```bash
npm run build
```

There is no frontend test runner, ESLint script or standalone `tsc` script in `package.json`; frontend behavior is partially asserted by Laravel Feature tests that inspect endpoints and built/source content. This documentation task did not modify or rebuild frontend artifacts.

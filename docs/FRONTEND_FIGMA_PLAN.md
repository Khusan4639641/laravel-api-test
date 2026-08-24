# Safi Life Frontend/Figma Alignment Plan

## Stage 1 Scope

Goal: audit the current React frontend in `resources/js/safi` and define the screen-by-screen plan for aligning it with the SafiLife Figma design.

Figma reference:
`https://www.figma.com/design/UVDBZB2E6iP1eL7kPsvRPt/SafiLife?node-id=31-87&p=f`

Access note: the Figma file contents were not available through the current tool context. The Figma plugin install flow did not expose Figma tools in this session, and the public URL did not provide inspectable layer/frame data. The plan below is therefore based on the existing code structure and the required screen list. Exact Figma frame names, measurements, colors, assets, typography values, and per-frame deviations must be verified once Figma inspect/export access is available.

## Current Frontend Map

- Entry point: `resources/js/safi/main.tsx`
- App shell: `resources/js/safi/App.tsx`
- Router: `resources/js/safi/router/routes.tsx`
- Global styles/tokens: `resources/js/safi/index.css`
- Public layout: `resources/js/safi/components/layout`
- Shared UI: `resources/js/safi/components/ui`
- Dashboard layout/components: `resources/js/safi/components/dashboard`
- Admin layout/components: `resources/js/safi/components/admin`
- Mock/static data: `resources/js/safi/data`
- Laravel host view: `resources/views/app.blade.php`

Current frontend data state:

- No frontend API calls were found in `resources/js/safi` (`fetch`, `axios`, `/api` are not used).
- Public product/news/package/status/FAQ content is local mock/static data.
- Dashboard pages use `resources/js/safi/data/dashboardMock.ts`.
- Admin pages use `resources/js/safi/data/adminMock.ts` or local page arrays.
- Backend API routes exist for auth, products, orders, package activation/upgrade, referrals, withdrawals, binary bonus calculation, admin users, and admin withdrawals. The design pass should not connect or modify APIs unless a later task explicitly asks for it.

## Reusable Components To Preserve/Refine

- Public: `Header`, `Footer`, `MainLayout`, `FloatingContactButtons`
- UI: `Button`, `Container`, `Card`, `SectionTitle`, `LanguageSwitcher`
- Dashboard: `DashboardLayout`, `Sidebar`, `StatCard`, `ProgressBar`, `Badge`
- Admin: `AdminLayout`, `AdminSidebar`, `AdminStatCard`, `AdminTable`, `AdminBadge`

Design alignment should first centralize shared Figma tokens in `index.css` and component primitives, then update pages in small groups while keeping `npm run build` green after each stage.

## Public Pages

| Screen/section | Current file | What exists now | Figma changes needed | Reuse components | API/mock |
|---|---|---|---|---|---|
| Home | `resources/js/safi/pages/HomePage.tsx` | Full landing page with hero, product visual, mini news feed, benefits, featured products, packages, CTA. Uses large rounded cards, serif headings, gold/green palette, external images. | Match Figma section order, hero composition, product imagery, typography scale, spacing, button styles, decorative elements, responsive behavior, and any Figma-specific home sections among the ~32 objects. Replace generic stock-like imagery only after assets are confirmed. | `MainLayout`, `Header`, `Footer`, `Container`, `Button`; later extract `ProductCard`, `PackageCard`, `NewsCard`, `BenefitCard`. | Mock: `products`, `packages`, `newsArticles`. `GET /api/products` exists but is not connected. |
| About | `resources/js/safi/pages/AboutPage.tsx` | Mission/path copy, team image, values grid. | Match Figma about hero/content blocks, image placement, value cards, page spacing, and any brand story/mission sections. | `Container`, shared section heading/card primitives. | Static/mock. No frontend API. |
| Products | `resources/js/safi/pages/ProductsPage.tsx` | Product catalog grid, category chips, product detail modal. | Match Figma catalog cards, filters, modal/detail layout, product image ratios, price/PV badges, mobile grid. Keep detail data until API connection task. | `Container`, `Button`; extract shared `ProductCard` and `ProductDetailModal`. | Mock: `data/products.ts`. API available: `GET /api/products`, `GET /api/products/{product}`. |
| Business / Возможности | `resources/js/safi/pages/BusinessPage.tsx` | Partner opportunity page with target audience list, why Safi block, disclaimer. | Match Figma opportunity blocks, iconography, contrast bands, copy rhythm, and CTA placement. | `Container`, shared heading/card components. | Static/mock. No frontend API. |
| Marketing plan | `resources/js/safi/pages/MarketingPlanPage.tsx` | Bonus cards, income calculator, statuses table, disclaimer. Uses local `packages` and `statuses`. | Match Figma marketing-plan layout, bonus taxonomy, calculator controls, status table/cards, legal disclaimers, and mobile behavior. | `Container`, `SectionTitle`, calculator controls, table/card primitives. | Mock: `packages`, `statuses`, local calculator state. API available: `POST /api/bonuses/binary/calculate`, not connected. |
| How to start | `resources/js/safi/pages/HowToStartPage.tsx` | Seven-step onboarding list and CTA. | Match Figma stepper/timeline style, numbers/icons, spacing, CTA and mobile stacking. | `Container`, `Button`, shared step card. | Static/mock. No frontend API. |
| FAQ | `resources/js/safi/pages/FAQPage.tsx` | FAQ groups rendered as native `details` accordions. | Match Figma accordion styling, categories, open/closed states, spacing, final disclaimer block. | `Container`; extract `AccordionItem` if repeated elsewhere. | Mock: `data/faq.ts`. No frontend API. |
| News | `resources/js/safi/pages/NewsPage.tsx` | News list with image/content cards. | Match Figma news card layout, category/date badges, image proportions, empty/loading states if present. | `Container`; shared `NewsCard`. | Mock: `data/news.ts`. No backend news API route visible. |
| Contacts | `resources/js/safi/pages/ContactsPage.tsx` | Contact info and consultation form; form prevents submit. | Match Figma contact layout, form controls, contact cards, map/social blocks if present. Keep form static unless API task is added. | `Container`, `Button`, shared form field styles. | Static/mock. No contact API visible. |
| Legal | `resources/js/safi/pages/LegalPage.tsx` | Legal/disclaimer content in one card. | Match Figma legal typography, content width, list styling, and section dividers. | `Container`, shared prose/card style. | Static. No API. |
| Login | `resources/js/safi/pages/LoginPage.tsx` | Login card; button links to `/dashboard` demo. | Match Figma auth card, input styles, background treatment, helper links, validation visual states. Do not wire auth yet unless requested. | `Container`, `Button`, shared auth field primitives. | Static/demo. API available: `POST /api/login`, `POST /api/logout`, `GET /api/me`, not connected. |
| Register | `resources/js/safi/pages/RegisterPage.tsx` | Registration form with package select; button links to `/dashboard` demo. | Match Figma registration flow, package selector, referral input, agreement block, responsive layout. Do not remove mock packages. | `Container`, `Button`, shared auth/form components. | Mock: `packages`. API available: `POST /api/register`, `GET /api/ref/{user_id}/{branch}`, not connected. |

## Dashboard Pages

| Screen/section | Current file | What exists now | Figma changes needed | Reuse components | API/mock |
|---|---|---|---|---|---|
| Dashboard layout | `resources/js/safi/components/dashboard/DashboardLayout.tsx`, `Sidebar.tsx` | Fixed sidebar, top bar, language switcher, user summary, mobile drawer. | Match Figma dashboard shell dimensions, sidebar density, topbar controls, active states, responsive drawer, and content max-width. | `DashboardLayout`, `Sidebar`, `LanguageSwitcher`, `Badge`. | Mock user from `dashboardMock.ts`. `GET /api/me` exists but is not connected. |
| Overview | `resources/js/safi/pages/dashboard/Overview.tsx` | Welcome card, stats, status progress, quick actions, referral links, recent transactions. | Match Figma dashboard overview cards, metric hierarchy, progress style, quick-action icons, referral link component, transaction preview. | `StatCard`, `ProgressBar`, `Badge`, dashboard layout. | Mock: `user`, `balance`, `structure`, `transactions`. Partial API exists but no dashboard summary endpoint visible. |
| Profile | `resources/js/safi/pages/dashboard/Profile.tsx` | Profile card, personal/payment/security/notification forms, static toggles. | Match Figma profile layout, avatar block, form density, toggles, save actions, mobile stacking. | `Badge`, shared form field/toggle components. | Mock: `user`. API candidate: `GET /api/me`; update endpoints not visible. |
| Package status | `resources/js/safi/pages/dashboard/PackageStatus.tsx` | Package cards and status ladder cards with local arrays. | Match Figma package/status progression, current/locked states, upgrade CTA, rewards display. | `Badge`, `ProgressBar`, package/status cards. | Mock/local arrays. APIs available: `POST /api/packages/{package}/activate`, `POST /api/packages/{package}/upgrade`, not connected. |
| Products | `resources/js/safi/pages/dashboard/Products.tsx` | In-cabinet product shop grid with product cards. | Match Figma cabinet shop cards, cart CTA, PV/price badges, product image ratios. | Shared `ProductCard`, dashboard layout. | Mock: `data/products.ts`. APIs: `GET /api/products`, `POST /api/orders`, not connected. |
| Bonuses | `resources/js/safi/pages/dashboard/Bonuses.tsx` | Finance page with bonus tab, withdrawal tab, mini cards, details, withdrawal form/history. | Match Figma finance/bonuses tabs, wallet summary, form layout, bonus breakdown cards, history table/mobile state. | `Badge`, `ProgressBar`, `StatCard`, table/form primitives. | Mock: `dashboardMock.ts`. APIs: `GET/POST /api/withdrawals`, `POST /api/bonuses/binary/calculate`, not connected. |
| Transactions | `resources/js/safi/pages/dashboard/Transactions.tsx` | Stat cards, filters, export button, responsive table/list. | Match Figma transaction filters, table columns, status badges, mobile transaction cards. | `StatCard`, `Badge`, table primitives. | Mock: `transactions`, `balance`. No dedicated transaction API route visible. |
| Structure | `resources/js/safi/pages/dashboard/Structure.tsx` | Structure stats, placeholder tree, partners table, referral/user info. | Match Figma binary tree/structure visualization, branch cards, filters, partner rows, empty/loading states. | `StatCard`, `Badge`, table/tree node components. | Mock: `structure`, `partners`, `user`. API candidate: `GET /api/ref/{user_id}/{branch}`; no tree API visible. |
| News | `resources/js/safi/pages/dashboard/News.tsx` | In-cabinet news list using public news mock. | Match Figma cabinet news card style and spacing, category/date badges. | Shared `NewsCard`, dashboard layout. | Mock: `newsArticles`. No backend news API visible. |
| Support | `resources/js/safi/pages/dashboard/Support.tsx` | Manager contacts, FAQ shortcuts, support form, ticket history table. | Match Figma support layout, contact cards, file upload, form fields, ticket status styles. | `Badge`, form/table primitives. | Mock: `supportTickets`. No support API visible. |

## Admin Pages

| Screen/section | Current file | What exists now | Figma changes needed | Reuse components | API/mock |
|---|---|---|---|---|---|
| Admin layout | `resources/js/safi/components/admin/AdminLayout.tsx`, `AdminSidebar.tsx` | Dark green sidebar, topbar search/bell/user, mobile drawer. | Match Figma admin shell, sidebar width, nav density, active states, topbar controls, page gutters. | `AdminLayout`, `AdminSidebar`, `AdminBadge`. | Static admin user. No auth wiring. |
| Admin overview | `resources/js/safi/pages/admin/AdminOverview.tsx` | Stats grid, chart placeholder, quick actions. | Match Figma admin dashboard stats, chart card, quick actions, data density. | `AdminStatCard`, shared admin cards. | Mock: `adminStats`. No admin stats API visible. |
| Partners | `resources/js/safi/pages/admin/AdminPartners.tsx` | Search/filter bar, partners table, actions, pagination. | Match Figma partner table, filters, row actions, badges, pagination, responsive table behavior. | `AdminTable`, `AdminBadge`. | Mock: `adminMock.partners`. API available: `GET /api/admin/users`. |
| Partner detail | `resources/js/safi/pages/admin/AdminPartnerDetail.tsx` | Detail profile, account action, admin note, finance stats, structure summary, transaction preview. | Match Figma detail page tabs/sections, partner header, status/action controls, notes, recent activity. | `AdminStatCard`, `AdminBadge`, `AdminTable`. | Mock: `adminMock.partners`; no specific admin user detail route visible. |
| Products | `resources/js/safi/pages/admin/AdminProducts.tsx` | Product admin table with add/edit/delete UI only. | Match Figma product management table/cards, image thumbnails, stock/status controls, add/edit modal if present. | `AdminTable`, `AdminBadge`, shared admin form controls. | Mock: `adminMock.products`. Public products API exists; admin product CRUD not visible. |
| Packages | `resources/js/safi/pages/admin/AdminPackages.tsx` | Three package cards with local package data. | Match Figma package management cards/table, edit controls, bonus/price display. | `AdminBadge`, package card primitive. | Local mock array. No admin package CRUD API visible. |
| Bonuses | `resources/js/safi/pages/admin/AdminBonuses.tsx` | Bonus list table from admin mock. | Match Figma bonus ledger, filters, totals, row details/statuses. | `AdminTable`, `AdminBadge`. | Mock: `adminMock.bonuses`. No admin bonus list API visible. |
| Transactions | `resources/js/safi/pages/admin/AdminTransactions.tsx` | Summary stat boxes, search/filter, transactions table. Uses `Math.random()` for stat values. | Match Figma finance transaction admin view, deterministic totals, filters, export, badges. Replace random display with mock constants before visual QA. | `AdminTable`, `AdminBadge`, stat card primitive. | Mock: `adminMock.transactions`. No admin transaction API visible. |
| Withdrawals | `resources/js/safi/pages/admin/AdminWithdrawals.tsx` | Warning block, status stat boxes, search, withdrawals table with approve/reject buttons. | Match Figma withdrawal approval queue, status counters, action button states, detail/reject modal if present. | `AdminTable`, `AdminBadge`, admin action buttons. | Mock: `adminMock.withdrawals`. APIs available: `GET /api/admin/withdrawals`, `PATCH approve/reject`. |
| Structure | `resources/js/safi/pages/admin/AdminStructure.tsx` | Admin binary tree mock and list/tree segmented control. | Match Figma admin tree view, search, zoom/branch controls, node cards, list view. | `AdminBadge`, tree node component. | Local mock tree. No admin structure API visible. |
| Reports | `resources/js/safi/pages/admin/AdminReports.tsx` | Three report cards with download buttons and chart placeholder. | Match Figma analytics/report cards, charts, export controls, date filters. | `AdminStatCard`, report card/chart primitives. | Mock: `adminStats`. No reports API visible. |
| News | `resources/js/safi/pages/admin/AdminNews.tsx` | Local CRUD-like news manager mutating `newsArticles` in memory. | Match Figma news management page, editor form/modal, list cards/table, publish/delete states. Keep local mock mutation until backend task. | `AdminBadge`, admin form/list primitives. | Mock: `data/news.ts`, in-memory mutation. No backend news API visible. |
| Support | `resources/js/safi/pages/admin/AdminSupport.tsx` | Support ticket table with status badges and open action. | Match Figma support inbox, filters, ticket detail/reply state, status queue. | `AdminTable`, `AdminBadge`. | Mock: `adminMock.supportTickets`. No support API visible. |
| Settings | `resources/js/safi/pages/admin/AdminSettings.tsx` | Basic settings form for company name, withdrawal minimum, withdrawal methods. | Match Figma settings groups, switches/checkboxes, save states, audit/log sections if present. | Shared admin form primitives. | Static local values. No settings API visible. |
| Statuses | `resources/js/safi/pages/admin/AdminStatuses.tsx` | Status ladder table with local status array and edit action. | Match Figma status management table/cards, rewards, conditions, edit controls. | `AdminTable`, `AdminBadge`. | Local mock array. No statuses API visible. |

## Cross-Cutting Figma Tasks

1. Extract exact Figma frames/sections and map them to the routes above.
2. Export or identify required real assets: logo variants, product images, icons, photos, background imagery.
3. Normalize tokens in `index.css`: colors, font families, type scale, spacing, radii, shadows, border colors.
4. Align shared primitives first: `Button`, `Container`, cards, badges, form fields, tables, sidebars, headers.
5. Replace one-off repeated card/table/form styling with small local reusable components only when it reduces duplication and matches existing patterns.
6. Preserve mock data until an explicit API integration task is opened.
7. After each implementation stage, run `npm run build` and keep `public/build/manifest.json` generated.

## Proposed Implementation Stages After This Audit

1. Figma extraction and design tokens: confirm frames, visual assets, colors, typography, spacing, radii.
2. Shared shell/components: public header/footer, buttons, cards, badges, form controls, tables.
3. Public marketing pages: Home, About, Business, Marketing plan, How to start.
4. Public content/forms: Products, FAQ, News, Contacts, Legal, Login, Register.
5. Dashboard shell and overview/finance: layout, Overview, Bonuses, Transactions.
6. Dashboard secondary pages: Profile, Package status, Products, Structure, News, Support.
7. Admin shell and core lists: Admin layout, Overview, Partners, Partner detail, Withdrawals.
8. Admin secondary pages: Products, Packages, Bonuses, Transactions, Structure, Reports, News, Support, Settings, Statuses.

Each stage should be a small visual pass with no backend changes and a successful `npm run build` before moving on.

# Frontend/backend data audit

Дата аудита: 2026-05-14.

Scope: места во `resources/js/safi`, где frontend импортирует mock data из `resources/js/safi/data/*`.

Проверенные команды:

```bash
grep -R "from '@/data" resources/js/safi -n
grep -R "from '../data" resources/js/safi -n
grep -R "dashboardMock\|adminMock\|products\|packages\|news\|faq\|statuses" resources/js/safi -n
```

Backend routes сейчас есть для: auth, `/api/me`, public `/api/products`, user orders/withdrawals, package activate/upgrade, binary bonus calculate, admin users, admin withdrawals.

| Page / Component | File path | Сейчас использует mock или API | Mock file | Нужный backend endpoint | Статус endpoint: exists / need create | Таблица базы данных |
|---|---|---|---|---|---|---|
| HomePage: product highlight | `resources/js/safi/pages/HomePage.tsx` | Mock only | `resources/js/safi/data/products.ts` | `GET /api/products` | exists | `products` |
| HomePage: packages | `resources/js/safi/pages/HomePage.tsx` | Mock only | `resources/js/safi/data/packages.ts` | `GET /api/packages` | need create | `packages` |
| ProductsPage: public catalog | `resources/js/safi/pages/ProductsPage.tsx` | Mock only | `resources/js/safi/data/products.ts` | `GET /api/products`, `GET /api/products/{product}` | exists | `products` |
| BusinessPage: start packages | `resources/js/safi/pages/BusinessPage.tsx` | Mock only | `resources/js/safi/data/packages.ts` | `GET /api/packages` | need create | `packages` |
| MarketingPlanPage: package calculator | `resources/js/safi/pages/MarketingPlanPage.tsx` | Mock only | `resources/js/safi/data/packages.ts` | `GET /api/packages` | need create | `packages` |
| MarketingPlanPage: statuses table | `resources/js/safi/pages/MarketingPlanPage.tsx` | Mock only | `resources/js/safi/data/statuses.ts` | `GET /api/statuses` | need create | no table; currently `users.status` + `StatusService` thresholds |
| HowToStartPage: packages | `resources/js/safi/pages/HowToStartPage.tsx` | Mock only | `resources/js/safi/data/packages.ts` | `GET /api/packages` | need create | `packages` |
| RegisterPage: package selector | `resources/js/safi/pages/RegisterPage.tsx` | API for registration, mock for package options | `resources/js/safi/data/packages.ts` | `GET /api/packages`, `POST /api/register` | `POST /api/register` exists; `GET /api/packages` need create | `users`, `packages`, `user_profiles`, `wallets`, `binary_nodes` |
| FAQPage | `resources/js/safi/pages/FAQPage.tsx` | Mock only | `resources/js/safi/data/faq.ts` | `GET /api/faq` | need create | need create: `faq_categories`, `faq_items` |
| NewsPage: public news | `resources/js/safi/pages/NewsPage.tsx` | Mock only | `resources/js/safi/data/news.ts` | `GET /api/news`, `GET /api/news/{article}` | need create | need create: `news_articles` |
| DashboardLayout: current user fallback | `resources/js/safi/components/dashboard/DashboardLayout.tsx` | API `/api/me` with mock fallback | `resources/js/safi/data/dashboardMock.ts` | `GET /api/me`, optionally `GET /api/dashboard/summary` for aggregates | `/api/me` exists; summary need create | `users`, `user_profiles`, `wallets`, `packages`, `binary_nodes`, `bonus_transactions` |
| Dashboard Overview | `resources/js/safi/pages/dashboard/Overview.tsx` | Hybrid: user from context API; balance/structure/transactions from mock | `resources/js/safi/data/dashboardMock.ts` | `GET /api/dashboard/summary`, `GET /api/wallet-transactions` | need create | `wallets`, `wallet_transactions`, `bonus_transactions`, `users`, `binary_nodes` |
| Dashboard Structure | `resources/js/safi/pages/dashboard/Structure.tsx` | Mock only | `resources/js/safi/data/dashboardMock.ts` | `GET /api/dashboard/structure` | need create | `users`, `binary_nodes`, `packages` |
| Dashboard Transactions | `resources/js/safi/pages/dashboard/Transactions.tsx` | Mock only | `resources/js/safi/data/dashboardMock.ts` | `GET /api/wallet-transactions` | need create | `wallets`, `wallet_transactions`, `bonus_transactions`, `withdrawal_requests` |
| Dashboard Bonuses and withdrawal | `resources/js/safi/pages/dashboard/Bonuses.tsx` | Hybrid: withdrawals/binary API; balance/bonus summary/structure from mock | `resources/js/safi/data/dashboardMock.ts` | `GET /api/withdrawals`, `POST /api/withdrawals`, `POST /api/bonuses/binary/calculate`, `GET /api/bonuses`, `GET /api/dashboard/summary` | withdrawal/binary exists; bonus summary need create | `wallets`, `withdrawal_requests`, `bonus_transactions`, `wallet_transactions`, `binary_nodes` |
| Dashboard PackageStatus: packages | `resources/js/safi/pages/dashboard/PackageStatus.tsx` | API for activate/upgrade, mock for package list | `resources/js/safi/data/packages.ts` | `GET /api/packages`, `POST /api/packages/{package}/activate`, `POST /api/packages/{package}/upgrade` | activate/upgrade exists; package list need create | `packages`, `users`, `orders`, `order_items` |
| Dashboard PackageStatus: statuses | `resources/js/safi/pages/dashboard/PackageStatus.tsx` | Mock only | `resources/js/safi/data/statuses.ts` | `GET /api/statuses` or include in `GET /api/dashboard/summary` | need create | no table; currently `users.status` + `StatusService` thresholds |
| Dashboard Products | `resources/js/safi/pages/dashboard/Products.tsx` | API `/api/products` with mock fallback; create order calls API | `resources/js/safi/data/products.ts` | `GET /api/products`, `POST /api/orders` | exists | `products`, `orders`, `order_items` |
| Dashboard News | `resources/js/safi/pages/dashboard/News.tsx` | Mock only | `resources/js/safi/data/news.ts` | `GET /api/news` | need create | need create: `news_articles` |
| Dashboard Support | `resources/js/safi/pages/dashboard/Support.tsx` | Mock only | `resources/js/safi/data/dashboardMock.ts` | `GET /api/support-tickets`, `POST /api/support-tickets` | need create | need create: `support_tickets`, `support_messages` |
| AdminOverview | `resources/js/safi/pages/admin/AdminOverview.tsx` | Hybrid: users/withdrawals API; admin stats from mock | `resources/js/safi/data/adminMock.ts` | `GET /api/admin/users`, `GET /api/admin/withdrawals`, `GET /api/admin/overview` | users/withdrawals exists; overview need create | `users`, `wallets`, `withdrawal_requests`, `orders`, `order_items`, `bonus_transactions` |
| AdminPartners | `resources/js/safi/pages/admin/AdminPartners.tsx` | API `/api/admin/users` with mock fallback | `resources/js/safi/data/adminMock.ts` | `GET /api/admin/users` with profile/package/wallet/tree aggregates | exists, but response is incomplete for current UI | `users`, `user_profiles`, `packages`, `wallets`, `binary_nodes` |
| AdminPartnerDetail | `resources/js/safi/pages/admin/AdminPartnerDetail.tsx` | Mock only | `resources/js/safi/data/adminMock.ts` | `GET /api/admin/users/{user}`, `PATCH /api/admin/users/{user}` | need create | `users`, `user_profiles`, `packages`, `wallets`, `binary_nodes`, `wallet_transactions`, `bonus_transactions` |
| AdminWithdrawals | `resources/js/safi/pages/admin/AdminWithdrawals.tsx` | API with mock fallback | `resources/js/safi/data/adminMock.ts` | `GET /api/admin/withdrawals`, `PATCH /api/admin/withdrawals/{withdrawal}/approve`, `PATCH /api/admin/withdrawals/{withdrawal}/reject` | exists | `withdrawal_requests`, `wallets`, `users`, `wallet_transactions` |
| AdminReports | `resources/js/safi/pages/admin/AdminReports.tsx` | Mock only | `resources/js/safi/data/adminMock.ts` | `GET /api/admin/reports` or `GET /api/admin/analytics` | need create | `users`, `orders`, `order_items`, `packages`, `withdrawal_requests`, `bonus_transactions`, `wallet_transactions` |
| AdminBonuses | `resources/js/safi/pages/admin/AdminBonuses.tsx` | Mock only | `resources/js/safi/data/adminMock.ts` | `GET /api/admin/bonuses` | need create | `bonus_transactions`, `wallet_transactions`, `users` |
| AdminTransactions | `resources/js/safi/pages/admin/AdminTransactions.tsx` | Mock only | `resources/js/safi/data/adminMock.ts` | `GET /api/admin/transactions` | need create | `wallet_transactions`, `bonus_transactions`, `withdrawal_requests`, `users` |
| AdminProducts | `resources/js/safi/pages/admin/AdminProducts.tsx` | Mock only | `resources/js/safi/data/adminMock.ts` | `GET /api/admin/products`, `POST /api/admin/products`, `PATCH /api/admin/products/{product}`, `DELETE /api/admin/products/{product}` | need create | `products` |
| AdminSupport | `resources/js/safi/pages/admin/AdminSupport.tsx` | Mock only | `resources/js/safi/data/adminMock.ts` | `GET /api/admin/support-tickets`, `PATCH /api/admin/support-tickets/{ticket}` | need create | need create: `support_tickets`, `support_messages` |
| AdminNews | `resources/js/safi/pages/admin/AdminNews.tsx` | Mock only; mutates imported `newsArticles` in memory | `resources/js/safi/data/news.ts` | `GET /api/admin/news`, `POST /api/admin/news`, `PATCH /api/admin/news/{article}`, `DELETE /api/admin/news/{article}` | need create | need create: `news_articles` |

## Дополнительные наблюдения

- `resources/js/safi/data/adminMock.ts` exports `adminLogs`, но во frontend этот export сейчас не используется.
- `resources/js/safi/pages/admin/AdminPackages.tsx` и `resources/js/safi/pages/admin/AdminStatuses.tsx` содержат inline mock arrays. Они не импортируются из `resources/js/safi/data/*`, но тоже потребуют backend endpoints, если эти admin screens будут подключаться к API.
- `RegisterPage` отправляет `package_id` из frontend mock как строку (`business`, `vip`, `elite`), а backend validation ожидает integer id из таблицы `packages`.
- `RegisterPage` отправляет branch values `left` / `right`, а backend validation ожидает `L` / `R`.
- `Dashboard Products` вызывает `createOrder({ product_id, quantity })`, но backend `POST /api/orders` ожидает `items: [{ product_id, quantity }]`.
- `Dashboard Bonuses` вызывает `createWithdrawal({ amount, method })`, но backend ожидает `payment_method` и optional `payment_details`.
- `ProductController` возвращает поля таблицы `products`; frontend mock product model ожидает также `category`, `shortDescription`, `benefits`, `composition`, `usage`, `image`. Эти поля сейчас могут быть только в `products.metadata` или потребуют расширения schema/resource.
- Статусы сейчас описаны в `StatusService` как thresholds и в `users.status` как строка; отдельной таблицы статусов/вознаграждений нет.

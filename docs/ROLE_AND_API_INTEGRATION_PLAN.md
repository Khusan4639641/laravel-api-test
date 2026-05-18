# Role and API Integration Plan

Дата аудита: 2026-05-17

Аудит выполнен по текущему рабочему дереву. В проекте есть незакоммиченные изменения; вывод ниже описывает именно текущее состояние файлов.

## Backend Audit

| Область | Текущее состояние |
| --- | --- |
| `routes/api.php` | Есть public API (`/api/public/products`, `/packages`, `/news`, `/faqs`, `/statuses`), auth API, dashboard API, admin API. Admin routes разделены на `admin:support,admin,super_admin` для support tickets и `admin:admin,super_admin` для полного admin-доступа. |
| `app/Models/User.php` | Модель содержит `role` в `Fillable`, связи `supportTickets`, `binaryNode`, `wallets`, `orders`, `bonusTransactions`, `withdrawalRequests`, `currentPackage`, `sponsor`, `referrals`. |
| Users migrations | Базовая `users` таблица создается в `0001_01_01_000000_create_users_table.php`; MLM-поля добавляются в `2026_05_04_150624_add_mlm_fields_to_users_table.php`; поле `role` добавляется в `2026_05_04_162659_add_role_to_users_table.php`. |
| Поле `role` | Есть: `users.role`, `string`, default `user`, index. |
| Middleware | Есть один alias `admin` в `bootstrap/app.php`, указывает на `App\Http\Middleware\EnsureAdmin`. Middleware принимает список ролей через параметры (`admin:support,admin,super_admin`). Отдельных классов `EnsureSuperAdmin` или `EnsureSupport` нет. |
| Таблица `news` | Есть migration `2026_05_14_000001_create_news_table.php`; есть model `News`, resource `NewsResource`, public/admin controllers. |
| Таблица `faqs` | Есть migration `2026_05_14_000002_create_faqs_table.php`; есть model `Faq`, resource `FaqResource`, public/admin controllers. |
| Таблица `support_tickets` | Есть migration `2026_05_14_000003_create_support_tickets_table.php`; есть model `SupportTicket`, resource `SupportTicketResource`, dashboard/admin controllers. |
| Таблица `products` | Есть migration `2026_05_04_150629_create_products_table.php`; есть model `Product`, resource `ProductResource`, public/admin/dashboard controllers. |
| Таблица `packages` | Есть migration `2026_05_04_150623_create_packages_table.php`; есть model `Package`, resource `PackageResource`, public/admin/dashboard controllers. |
| Таблица `binary_nodes` | Есть migration `2026_05_04_150626_create_binary_nodes_table.php`; есть model `BinaryNode`, resource `BinaryNodeResource`, services/controllers for structure. |
| Таблица `system_settings` | Есть migration `2026_05_15_000004_create_system_settings_table.php`; есть model `SystemSetting`, resource `SystemSettingResource`, admin settings controller. |
| Statuses | Отдельной таблицы `statuses` нет. Статусы захардкожены в `App\Services\StatusService::publicStatuses()`, admin/public API читают их из сервиса. |
| Admin products/news/settings/support controllers | Есть: `Admin\ProductController`, `Admin\NewsController`, `Admin\SettingsController`, `Admin\SupportTicketController`. |
| Resources для admin products/news/settings/support | Есть: `ProductResource`, `NewsResource`, `SystemSettingResource`, `SupportTicketResource`. |
| FormRequest classes для admin products/news/settings/support | Отдельных request-классов нет. Валидация выполняется inline внутри controllers. |

## Frontend Audit

| Область | Текущее состояние |
| --- | --- |
| `resources/js/safi/router/routes.tsx` | Используется `BrowserRouter`. Есть public routes, dashboard routes и admin routes. Route-level role guards находятся не в router, а в `DashboardLayout`/`AdminLayout`. |
| `DashboardLayout.tsx` | Загружает текущего пользователя через `/api/me`. Если роль `admin`, `super_admin` или `support`, редиректит в admin area. Для user/partner открывает dashboard. |
| `AdminLayout.tsx` | Загружает текущего пользователя через `/api/me`. Разрешает `admin`, `super_admin`, `support`. Для `support` редиректит на `/admin/support`. |
| `NewsPage.tsx` | Берет новости через `getPublicNews()` (`/api/public/news`), mock data не импортирует. |
| `resources/js/safi/pages/admin/*` | Все admin pages используют API helpers из `resources/js/safi/lib/api.ts`; mock imports не обнаружены. |
| `resources/js/safi/pages/dashboard/*` | Основные dashboard pages используют API helpers. `Profile.tsx` использует данные из `DashboardLayout` context, отдельного profile update API UI пока нет. |
| `resources/js/safi/data/*` | Файлы mock data остаются в проекте (`adminMock.ts`, `dashboardMock.ts`, `faq.ts`, `news.ts`, `packages.ts`, `products.ts`, `statuses.ts`), но прямых imports из них не обнаружено. |

## Mock Imports Check

| Команда | Результат |
| --- | --- |
| `grep -R "from '@/data" resources/js/safi -n` | Совпадений нет. |
| `grep -R "from '../data\\|from '../../data\\|from '@/data\\|dashboardMock\\|adminMock" resources/js/safi -n` | Совпадений нет. |
| `grep -R "dashboardMock\\|adminMock\\|products\\|news\\|faq" resources/js/safi -n` | Есть совпадения по route names, endpoint names, i18n keys, state names и export names внутри `resources/js/safi/data/*`. Импортов mock data в components/pages не найдено. |

## A) Role Permissions

| Role | Разрешено | Запрещено / ограничения |
| --- | --- | --- |
| `user` / `partner` | Dashboard: overview, structure, transactions, bonuses/withdrawals, package status, products/order, news, profile, support. Support tickets: создать, список своих, просмотр своего, редактировать свое если не `closed`, закрыть свое. | Admin area. Просмотр чужих support tickets. Удаление support tickets. Product CRUD, News CRUD, Settings. |
| `support` | Admin support tickets: список всех обращений, просмотр обращения, ответ, смена статуса, закрытие. | Admin overview, users, structure, transactions, withdrawals, bonuses, packages, statuses, products CRUD, news CRUD, reports, settings. Dashboard редиректит support в `/admin/support`. |
| `super_admin` | Полный admin API и admin menu: overview, users/partners, structure, transactions, withdrawals, bonuses, packages, statuses, products CRUD, news CRUD, support tickets, reports, settings. | Нет специальных ограничений в текущей реализации. |

Примечание: роль `admin` также существует и сейчас имеет тот же полный admin-доступ, что `super_admin`, для совместимости с текущими тестами и seed data.

## B) Menu Visibility

| Menu | Role | Visible items | Source file |
| --- | --- | --- | --- |
| Dashboard menu | `user` / `partner` | Обзор, Структура, Транзакции, Бонусы, Пакет, Продукты, Новости, Профиль, Поддержка | `resources/js/safi/components/dashboard/Sidebar.tsx` |
| Support menu | `support` | Support tickets only | `resources/js/safi/components/admin/AdminSidebar.tsx` |
| Admin menu | `super_admin` | Overview, Partners / Users, Structure, Transactions, Withdrawals, Bonuses, Packages, Statuses, Products CRUD, News CRUD, Support tickets, Reports, Settings | `resources/js/safi/components/admin/AdminSidebar.tsx` |

## C) Pages and Data Source

| Page | Component file | Сейчас mock или API | API нужен / используется | Таблица / источник |
| --- | --- | --- | --- | --- |
| `/` | `pages/HomePage.tsx` | API | `GET /api/public/products`, `GET /api/public/packages`, `GET /api/public/news` | `products`, `packages`, `news` |
| `/about` | `pages/AboutPage.tsx` | Static/i18n | Нет текущего API; при CMS нужен public page/settings endpoint | `system_settings` или будущая `pages` |
| `/products` | `pages/ProductsPage.tsx` | API | `GET /api/public/products` | `products` |
| `/business` | `pages/BusinessPage.tsx` | Static/i18n | Нет текущего API; при CMS нужен public page/settings endpoint | `system_settings` или будущая `pages` |
| `/marketing` | `pages/MarketingPlanPage.tsx` | API + static text | `GET /api/public/packages`, `GET /api/public/statuses` | `packages`, `StatusService` (таблицы `statuses` нет) |
| `/how-to-start` | `pages/HowToStartPage.tsx` | Static/i18n | Нет текущего API; при CMS нужен public page/settings endpoint | `system_settings` или будущая `pages` |
| `/faq` | `pages/FAQPage.tsx` | API | `GET /api/public/faqs` | `faqs` |
| `/contacts` | `pages/ContactsPage.tsx` | Static form, submit disabled (`preventDefault`) | Нужен endpoint для lead/contact request, если форма должна сохраняться | Нет таблицы contact requests |
| `/news` | `pages/NewsPage.tsx` | API | `GET /api/public/news` | `news` |
| `/login` | `pages/LoginPage.tsx` | API | `POST /api/login`, then role redirect | `users`, Sanctum tokens |
| `/register` | `pages/RegisterPage.tsx` | API | `GET /api/public/packages`, `POST /api/register` | `users`, `user_profiles`, `wallets`, `binary_nodes`, `packages` |
| `/dashboard` | `pages/dashboard/Overview.tsx` | API | `GET /api/dashboard/overview` | `users`, `wallets`, `bonus_transactions`, `binary_nodes`, related dashboard data |
| `/dashboard/structure` | `pages/dashboard/Structure.tsx` | API | `GET /api/dashboard/structure` | `binary_nodes`, `users`, `packages` |
| `/dashboard/transactions` | `pages/dashboard/Transactions.tsx` | API | `GET /api/dashboard/transactions` | `wallet_transactions` |
| `/dashboard/bonuses` | `pages/dashboard/Bonuses.tsx` | API | `GET /api/dashboard/bonuses`, `GET/POST /api/dashboard/withdrawals`, `POST /api/bonuses/binary/calculate` | `bonus_transactions`, `withdrawal_requests`, `wallets`, `wallet_transactions` |
| `/dashboard/package-status` | `pages/dashboard/PackageStatus.tsx` | API | `GET /api/dashboard/packages`, `GET /api/public/statuses`, `POST /api/packages/{package}/activate`, `POST /api/packages/{package}/upgrade` | `packages`, `users`, `StatusService` |
| `/dashboard/products` | `pages/dashboard/Products.tsx` | API | `GET /api/dashboard/products`, `POST /api/orders` | `products`, `orders`, `order_items` |
| `/dashboard/news` | `pages/dashboard/News.tsx` | API | `GET /api/public/news` | `news` |
| `/dashboard/profile` | `pages/dashboard/Profile.tsx` | Context from `/api/me`, no save API wired | Uses `GET /api/me`; profile update endpoint needed for save | `users`, `user_profiles` |
| `/dashboard/support` | `pages/dashboard/Support.tsx` | API | `GET /api/dashboard/support-tickets`, `POST /api/dashboard/support-tickets`, `GET/PUT/PATCH /api/dashboard/support-tickets/{ticket}` | `support_tickets` |
| `/admin` | `pages/admin/AdminOverview.tsx` | API | `GET /api/admin/overview` | users/wallets/orders/withdrawals/bonus aggregate |
| `/admin/partners` | `pages/admin/AdminPartners.tsx` | API | `GET /api/admin/users` | `users`, `user_profiles`, `packages` |
| `/admin/partners/:id` | `pages/admin/AdminPartnerDetail.tsx` | API | `GET /api/admin/users/{user}` | `users`, related resources |
| `/admin/structure` | `pages/admin/AdminStructure.tsx` | API | `GET /api/admin/structure` | `binary_nodes`, `users` |
| `/admin/transactions` | `pages/admin/AdminTransactions.tsx` | API | `GET /api/admin/transactions` | `wallet_transactions` |
| `/admin/withdrawals` | `pages/admin/AdminWithdrawals.tsx` | API | `GET /api/admin/withdrawals`, `PATCH approve/reject` | `withdrawal_requests`, `wallets`, `wallet_transactions` |
| `/admin/bonuses` | `pages/admin/AdminBonuses.tsx` | API | `GET /api/admin/bonuses` | `bonus_transactions` |
| `/admin/packages` | `pages/admin/AdminPackages.tsx` | API | `GET/POST/PUT /api/admin/packages` | `packages` |
| `/admin/statuses` | `pages/admin/AdminStatuses.tsx` | API | `GET /api/admin/statuses` | `StatusService`, `users.status`; no `statuses` table |
| `/admin/products` | `pages/admin/AdminProducts.tsx` | API | `GET/POST/PUT/DELETE /api/admin/products` | `products` |
| `/admin/news` | `pages/admin/AdminNews.tsx` | API | `GET/POST/PUT/DELETE /api/admin/news` | `news` |
| `/admin/support` | `pages/admin/AdminSupport.tsx` | API | `GET /api/admin/support-tickets`, `GET /api/admin/support-tickets/{ticket}`, `PATCH reply/status/close` | `support_tickets`, `users` |
| `/admin/reports` | `pages/admin/AdminReports.tsx` | API | `GET /api/admin/reports/summary` | aggregate over users/orders/transactions/withdrawals/bonuses |
| `/admin/settings` | `pages/admin/AdminSettings.tsx` | API | `GET /api/admin/settings`, `PUT /api/admin/settings` | `system_settings` |

## D) Missing Backend Endpoints

| Missing / incomplete endpoint | Needed for | Current workaround / status |
| --- | --- | --- |
| `PUT/PATCH /api/dashboard/profile` | Saving profile form in `/dashboard/profile` | UI shows inputs and Save button, but no API call is wired. |
| Password change endpoint, e.g. `PUT /api/dashboard/password` | Security section in `/dashboard/profile` | UI exists, no backend endpoint/wiring found. |
| Notification preferences endpoint | Notification toggles in `/dashboard/profile` | UI static; no table/endpoint found. |
| Contact/lead submit endpoint, e.g. `POST /api/public/contact-requests` | `/contacts` form persistence | Form currently prevents default and does not send data. No contact requests table found. |
| Public CMS page/settings endpoint | Dynamic `/about`, `/business`, `/how-to-start`, static public texts | Pages are static/i18n. Could use `system_settings` or a future `pages` table if content must be editable. |
| Status CRUD endpoints/table | Admin status management beyond read-only list | `GET /api/admin/statuses` exists, but statuses are hardcoded in `StatusService`; no `statuses` table or CRUD. |
| `DELETE /api/admin/packages/{package}` | Full package CRUD if delete is required | Admin package UI supports create/update; route has GET/POST/PUT only. |
| Admin user write endpoints | Editing users/roles/statuses from admin UI if needed | Current admin users endpoints are list/show only. |
| Dedicated FormRequest classes for admin product/news/settings/support | Cleaner validation/contracts | Controllers validate inline; no request classes for these admin resources. |

## Immediate Integration Notes

1. Real data path is mostly in place: current public, dashboard, and admin pages use `resources/js/safi/lib/api.ts`.
2. Mock data files can be deleted later only after confirming no external imports depend on them; current grep found no page/component imports.
3. Support role has backend and menu separation, but router still defines all admin routes. Protection is enforced by `AdminLayout` redirect and backend middleware.
4. `super_admin` is supported by middleware/menu, while legacy `admin` remains equivalent to preserve existing tests and demo compatibility.
5. Statuses are the main domain object still not stored in MySQL; they come from `StatusService`.

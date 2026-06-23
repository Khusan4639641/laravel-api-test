# Safi Life Developer Handover

## 1. Краткая карточка проекта

| Параметр | Значение |
| --- | --- |
| Project URL | https://safilife.kz |
| Local project path | `/var/www/project7` |
| Production server path | `/var/www/project7` |
| Branch for Safi work | `safi-frontend-integration` |
| Backend | Laravel 13 |
| Frontend | React/Vite |

> [!WARNING]
> Главный production-принцип: никогда не запускать разрушительные команды без свежего backup и явного подтверждения. Особенно это касается миграций с пересозданием таблиц, seed-команд, перерасчетов MLM, cleanup-команд и `php artisan tinker`.

## 2. Обзор проекта

Safi Life - MLM/e-commerce платформа с партнерской регистрацией, реферальной системой, бинарной структурой, пакетами START/VIP/ELITE, кошельками, заказами, товарами, админ-панелью, личным кабинетом, платежами TipTopPay, заявками на вывод, support tickets/chat и уведомлениями.

В проекте есть две зоны повышенного риска:

- бизнес-критичные MLM-расчеты: PV, бинар, статусы, X2, бонусы;
- денежная логика: кошельки, транзакции, выводы, cashback, депозитный баланс, платежи.

Любая логика, которая меняет деньги, PV, статусы, бонусы, структуру или доступный баланс, должна изменяться осторожно и покрываться тестами. Нельзя считать визуальное исправление достаточным, если backend может начислить неправильные деньги.

## 3. Repository / architecture overview

### Backend

| Директория | Назначение |
| --- | --- |
| `app/Models` | Eloquent-модели: пользователи, пакеты, кошельки, PV, бонусы, заказы, support, платежи. |
| `app/Services` | Основная бизнес-логика: `PackageService`, `PvService`, `BonusService`, `StatusService`, `X2BonusService`, `WithdrawalService`, `SupportTicketService`, `TipTopPayService` и др. |
| `app/Http/Controllers/Api` | API-контроллеры для public, dashboard, admin, payments, support. |
| `app/Http/Requests` | FormRequest-валидация, включая support attachments до 5 MB. |
| `app/Console/Commands` | Artisan-команды Safi/MLM/deletion cleanup. |
| `database/migrations` | Схема БД: users, packages, binary nodes, wallets, orders, bonuses, support, payments и др. |
| `database/seeders` | Пакеты, demo-данные, статусы, X2, товары, FAQ/news. |
| `routes/api.php` | Основные API endpoints. |
| `routes/web.php` | SPA fallback на React-приложение. |
| `tests` | Feature/Unit тесты по MLM, пакетам, выплатам, TipTopPay, support, admin/dashboard. |
| `config` | Laravel config, Safi flags, TipTopPay, роли/permissions. |

### Frontend

Фронтенд находится в `resources/js/safi`.

| Файл/директория | Назначение |
| --- | --- |
| `resources/js/safi/main.tsx` | Vite entry point. |
| `resources/js/safi/App.tsx` | Корневой React component. |
| `resources/js/safi/router/routes.tsx` | Основные public/dashboard/admin маршруты. |
| `resources/js/safi/pages` | Public pages. |
| `resources/js/safi/pages/dashboard` | Личный кабинет пользователя. |
| `resources/js/safi/pages/admin` | Админ-панель. |
| `resources/js/safi/components` | Общие компоненты, dashboard/admin layouts. |
| `resources/js/safi/lib` | API-клиент, endpoints, permissions, TipTopPay helper, форматирование. |
| `resources/js/safi/locales` | Локализации. |
| `resources/js/safi/index.css` | Основные стили Safi frontend. |

Vite настроен в `vite.config.js`, вход: `resources/js/safi/main.tsx`, alias `@` указывает на `resources/js/safi`.

### Важные экраны

Admin:

- `/admin/partners`
- `/admin/partners/{id}`
- `/admin/structure`
- `/admin/transactions`
- `/admin/bonuses`
- `/admin/packages`
- `/admin/statuses`
- `/admin/orders`
- `/admin/products`
- `/admin/support`
- `/admin/withdrawals`
- также есть `/admin/reports`, `/admin/settings`, `/admin/payment-readiness`, `/admin/news`, `/admin/partners/bulk-create`.

User dashboard:

- `/dashboard`
- `/dashboard/structure`
- `/dashboard/transactions`
- `/dashboard/bonuses`
- `/dashboard/package`
- `/dashboard/orders`
- `/dashboard/support`
- `/dashboard/profile`
- `/dashboard/products`
- `/cart`
- `/products`

Public:

- `/`
- `/login`
- `/register` redirects to `/login`
- `/register-ref-branch`
- `/legal`
- `/payment`
- `/payment/success`
- `/payment/fail`
- `/legal/offer`
- `/legal/privacy`
- `/legal/delivery`
- `/legal/refund`
- `/legal/requisites`

## 4. Local development commands

Backend:

```bash
cd /var/www/project7

php artisan optimize:clear
php artisan route:list
php artisan migrate
php artisan test
```

Frontend:

```bash
cd /var/www/project7

npm install
npm run build
npm run dev
```

Docker:

- В корне репозитория не найдено `Dockerfile` или `docker-compose*.yml`.
- Уточнить локальный Docker setup.
- Не придумывать сервисы и контейнеры без фактических файлов/инструкций.

## 5. Production safety rules

> [!WARNING]
> NEVER run on production without explicit confirmation and fresh backup:

```bash
php artisan migrate:fresh
php artisan db:seed --force
php artisan safi:cleanup-deleted-user-effects
php artisan safi:release-deleted-user-identities
php artisan safi:recalculate-mlm --all
php artisan tinker
```

Почему это опасно:

| Команда | Риск |
| --- | --- |
| `php artisan migrate:fresh` | Удаляет таблицы и пересоздает схему. На production это потеря данных без restore. |
| `php artisan db:seed --force` | Может перезаписать или добавить production-данные demo/seed значениями. |
| `php artisan safi:cleanup-deleted-user-effects` | Влияет на deleted/archived пользователей, PV, бонусы, кошельки, заявки, заказы, структуру. |
| `php artisan safi:release-deleted-user-identities` | Освобождает login/email/phone/referral identities у удаленных/архивных пользователей. |
| `php artisan safi:recalculate-mlm --all` | Массово меняет branch PV, weak leg, статусы и связанные эффекты. |
| `php artisan tinker` | Может напрямую мутировать production-данные без audit trail. |

Safe deploy checklist:

1. Проверить текущую ветку/commit.
2. Создать свежий DB backup.
3. Если миграция рискованная, включить maintenance mode.
4. Запускать `php artisan migrate --force` только после backup.
5. Очистить и пересобрать Laravel cache.
6. Перезапустить queues.
7. Проверить логи.
8. Вернуть сайт из maintenance mode.

Минимальный safe deploy pattern:

```bash
cd /var/www/project7

git status --short
php artisan down --secret=deploy-safi
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Maintenance mode не заменяет backup.

## 6. Production backup and restore procedure

### Backup command pattern

Не вставлять реальные DB passwords в документацию. Команда ниже читает значения из `.env` на сервере и не должна логировать секреты.

```bash
cd /var/www/project7

DB_HOST=$(grep '^DB_HOST=' .env | cut -d= -f2- | tr -d '"')
DB_PORT=$(grep '^DB_PORT=' .env | cut -d= -f2- | tr -d '"')
DB_DATABASE=$(grep '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"')
DB_USERNAME=$(grep '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"')
DB_PASSWORD=$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"')

mkdir -p /root/backups

mysqldump --no-tablespaces \
  -h "${DB_HOST:-127.0.0.1}" \
  -P "${DB_PORT:-3306}" \
  -u "$DB_USERNAME" \
  -p"$DB_PASSWORD" \
  "$DB_DATABASE" > /root/backups/safilife_before_CHANGE_NAME_$(date +%F_%H-%M-%S).sql
```

После backup:

```bash
ls -lh /root/backups/safilife_before_CHANGE_NAME_*.sql
```

Файл должен существовать и быть не пустым.

### Restore checklist

Use only during emergency restore.

1. `php artisan down --secret=restore-safi`
2. Проверить, что backup file существует и не пустой.
3. Сделать backup текущего broken state.
4. Drop and recreate DB.
5. Import backup.
6. `php artisan optimize:clear`
7. `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`
8. `php artisan queue:restart`
9. `supervisorctl restart ...`, если queues управляются supervisor.
10. `php artisan up`
11. Проверить admin pages и logs.

### Exact restore command pattern

> [!WARNING]
> Use only during emergency restore. Перед выполнением команды должен быть выбран корректный backup: `/root/backups/safilife_before_<change>_<date>.sql`.

```bash
cd /var/www/project7

php artisan down --secret=restore-safi

DB_HOST=$(grep '^DB_HOST=' .env | cut -d= -f2- | tr -d '"')
DB_PORT=$(grep '^DB_PORT=' .env | cut -d= -f2- | tr -d '"')
DB_DATABASE=$(grep '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"')
DB_USERNAME=$(grep '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"')
DB_PASSWORD=$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"')
BACKUP_FILE="/root/backups/safilife_before_<change>_<date>.sql"

test -s "$BACKUP_FILE"

mysqldump --no-tablespaces \
  -h "${DB_HOST:-127.0.0.1}" \
  -P "${DB_PORT:-3306}" \
  -u "$DB_USERNAME" \
  -p"$DB_PASSWORD" \
  "$DB_DATABASE" > /root/backups/safilife_broken_state_$(date +%F_%H-%M-%S).sql

mysql \
  -h "${DB_HOST:-127.0.0.1}" \
  -P "${DB_PORT:-3306}" \
  -u "$DB_USERNAME" \
  -p"$DB_PASSWORD" \
  -e "DROP DATABASE \`$DB_DATABASE\`; CREATE DATABASE \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

mysql \
  -h "${DB_HOST:-127.0.0.1}" \
  -P "${DB_PORT:-3306}" \
  -u "$DB_USERNAME" \
  -p"$DB_PASSWORD" \
  "$DB_DATABASE" < "$BACKUP_FILE"

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

# Если используется Supervisor:
supervisorctl status
# supervisorctl restart <program-name>

php artisan up
```

Post-restore checks:

```bash
cd /var/www/project7

php artisan route:list
tail -n 200 storage/logs/laravel.log
```

Проверить в браузере:

- `/admin/partners`
- `/admin/structure`
- `/dashboard`
- payment/legal pages

## 7. Environment and secrets policy

- `.env` никогда не commit-ить.
- Не вставлять секреты в docs, issues, pull requests, screenshots, логи и сообщения.
- Все tokens в скриншотах/логах маскировать.
- TipTopPay public terminal id можно упоминать только masked.
- Private keys/secrets нельзя хранить в документации.
- Telegram bot token, AWS keys, Sentry DSN, DB passwords, TipTopPay private keys должны быть redacted.

GOOD:

```dotenv
TIPTOPPAY_PUBLIC_TERMINAL_ID=pk_7b117...9078
```

BAD:

```dotenv
FULL_SECRET_TOKEN_PASTED_IN_MARKDOWN=do_not_do_this
```

TipTopPay env keys, которые можно обсуждать без значений:

```dotenv
TIPTOPPAY_ENABLED=
TIPTOPPAY_TEST_MODE=
TIPTOPPAY_PUBLIC_TERMINAL_ID=
TIPTOPPAY_SECRET_KEY=
TIPTOPPAY_CURRENCY=KZT
```

Текущий `config/tiptoppay.php` также читает provider-specific private values. Их названия можно увидеть в config, но значения нельзя копировать в документацию.

## 8. MLM glossary

| Термин | Значение |
| --- | --- |
| Partner | Зарегистрированный пользователь, участвующий в MLM. |
| Sponsor/referrer | Пользователь, который напрямую пригласил другого пользователя. |
| Binary parent | Родитель в бинарном дереве. |
| Left/right branch | Левая/правая сторона бинарной структуры. |
| Weak leg / малая ветка | `min(left PV, right PV)`. |
| Package PV / personal PV | PV, назначенный самому пакету. Это не деньги. |
| Team PV / branch PV | PV, накопленный из downline/ветки. |
| Turnover PV | PV, который поднимается вверх к upline. |
| Wallet balance | Реальные деньги, доступные по правилам кошелька. |
| Deposit balance | Отдельный депозитный кошелек. |
| Pending binary | Бинарный бонус, рассчитанный, но еще не выпущенный в кошелек. |
| Referral bonus | Бонус прямому sponsor за приглашенного партнера. |
| Status bonus | Бонус за достижение статусного порога. |
| X2 bonus | Бонус только по лично приглашенным партнерам, не по всей структуре. |

## 9. Package matrix

| Package | Price | Package PV | PV money base | Turnover to uplines | Referral bonus | Binary percent |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| START | 60 000 ₸ | 100 PV | 100 PV * 500 ₸ = 50 000 ₸ | 100 PV | 10% | 7% |
| VIP | 180 000 ₸ | 300 PV | 300 PV * 500 ₸ = 150 000 ₸ | 300 PV | 10% | 8% |
| ELITE | 300 000 ₸ | 500 PV | 500 PV * 500 ₸ = 250 000 ₸ | 200 PV при upgrade VIP -> ELITE | no for ELITE part | 10% after active |

ELITE specifics:

- Additional turnover when upgrading VIP -> ELITE: 200 PV.
- Referral bonus for ELITE part: no.
- Binary bonus for ELITE part: no.
- Binary percent after package active: 10%.

Important:

```php
// WRONG
$pv = $package->price;

// RIGHT
$pv = $package->activity_pv; // or package PV service method
```

PV is not package price. Never calculate `pv = package.price`.

## 10. Package purchase and upgrade rules

Registration:

- User can choose START or VIP.
- ELITE cannot be selected on first registration.
- ELITE is only upgrade.

START purchase:

- User package becomes START.
- Personal/package PV = 100.
- 100 PV goes up to uplines.
- Buyer does not receive own PV into left/right branch.

VIP purchase:

- User package becomes VIP.
- Personal/package PV = 300.
- 300 PV goes up to uplines.
- Buyer does not receive own PV into left/right branch.

START -> VIP upgrade:

- Final package PV = 300.
- Additional turnover to uplines = 200 PV.
- Do not add full 300 PV again.

VIP -> ELITE upgrade:

- Final package PV = 500.
- Additional turnover to uplines = 200 PV.
- ELITE part gives no referral bonus.
- ELITE part gives no binary bonus.

Manual admin package assignment:

- START gives 100 PV, not 60 000 PV.
- VIP gives 300 PV, not 180 000 PV.
- ELITE gives 500 package PV and 200 turnover PV, not 300 000 PV.
- Package assignment must not automatically add withdrawable money unless actual bonus exists.

Relevant backend areas:

- `app/Services/PackageService.php`
- `app/Services/PvService.php`
- `app/Services/ReferralBonusBaseResolver.php`
- `database/seeders/PackageSeeder.php`

## 11. PV and money separation

PV:

- используется для статусов, бинарного расчета, структуры и активности пакета;
- не является withdrawable money;
- не должен попадать в balance как деньги.

Money:

- баланс в ₸;
- может приходить из referral bonus, binary bonus, status bonus, X2, cashback, deposit mechanics.

Admin UI:

- PV column shows PV.
- Finance column shows real money.
- Не смешивать `PV * 500 ₸` с wallet balance, если это не фактически начисленный bonus.

Examples:

| Сценарий | Personal PV | Balance | Total earned |
| --- | ---: | ---: | ---: |
| START user with no bonuses | 100 PV | 0 ₸ | 0 ₸ |
| START user with referral bonus 5 000 ₸ | 100 PV | 5 000 ₸ | 5 000 ₸ |

## 12. Referral bonus rules

- Referral bonus goes to direct sponsor only.
- START/VIP purchase can create referral bonus.
- Rate: 10%.
- ELITE upgrade does not create referral bonus for ELITE part.
- Calculation base is still business-open:
  - full amount;
  - net amount;
  - PV base.
- Code should isolate calculation base in service/method so it can be changed later.

Current relevant service: `app/Services/ReferralBonusBaseResolver.php`.

Current behavior from code:

- START/VIP activation base uses `activityPv * 500`.
- ELITE upgrade returns `0.00` base.
- Product order base uses order total.

If business changes the base, update resolver + tests first.

## 13. Binary bonus rules

### Current rule

- Binary bonus uses weak leg.
- Example:

```text
left = 1000 PV
right = 2000 PV
weak leg = 1000 PV
money base = 1000 * 500 = 500 000 ₸
```

- Percent:
  - START: 7%
  - VIP: 8%
  - ELITE: 10%
- Period: every 15 days.
- Already used PV must not be counted again.
- Normal users must not see/run recalculate button.
- Recalculate should be admin/super_admin/system only.
- ELITE 200 PV part can be visible as turnover but must not produce binary payout.

Current relevant areas:

- `app/Services/BonusService.php`
- `app/Models/BinaryBonusRun.php`
- `app/Models/BinaryBonusCalculation.php`
- `database/migrations/2026_06_04_000001_create_binary_bonus_period_tables.php`
- `database/migrations/2026_06_07_000002_add_pending_amount_to_binary_bonus_runs_table.php`

### New recommended architecture

Binary should move to pending ledger model:

```text
PV event -> binary pending -> release to wallets
```

Pending binary:

- calculated when paid PV appears;
- not withdrawable immediately;
- shown as "Бинар ожидает";
- released every 15 days or by admin release action.

Release split:

- 90% to main balance;
- 10% to deposit balance.

Important:

- Старую тяжелую кнопку "calculate binary" нельзя давать обычным пользователям.
- Admin button should release pending binary, not rebuild everything.
- Full rebuild/recalculate должен быть отдельной maintenance/admin operation с backup и audit.

## 14. Binary income limits

| Status | 15 days/week limit | Month limit |
| --- | ---: | ---: |
| Manager | 500 000 ₸ / 15 days | 1 000 000 ₸ / month |
| Leader | 500 000 ₸ / 15 days | 1 000 000 ₸ / month |
| Director | 500 000 ₸ / week | 1 000 000 ₸ / month |
| Bronze Director | 800 000 ₸ / 15 days | 1 600 000 ₸ / month |
| Silver Director | 800 000 ₸ / 15 days | 1 600 000 ₸ / month |
| Gold Director | 800 000 ₸ / 15 days | 1 600 000 ₸ / month |
| Platinum Director and above | 2 500 000 ₸ / 15 days | 5 000 000 ₸ / month |

Implementation note:

- Store `gross_amount`.
- Store `payable_amount`.
- Store `capped_amount`.
- Tests must cover cap hit, partial cap, no cap, month limit and period limit.

## 15. Deposit account rules

When binary bonus is released:

- 90% main wallet;
- 10% deposit wallet.

Deposit catalog:

- separate products can be bought with deposit balance;
- if user buys deposit product, 20% of purchase amount returns to main balance as cashback/earning.

Example:

```text
Deposit purchase = 1 000 ₸
Cashback to main = 200 ₸
```

Cart rules:

- deposit-only products and normal products must not be mixed in one cart;
- deposit product purchase must check deposit balance when adding/increasing quantity;
- deposit checkout deducts deposit balance and creates order as paid/in process.

Relevant backend areas:

- `app/Services/DepositPurchaseService.php`
- `app/Services/BonusService.php`
- `app/Models/Product.php` (`is_deposit_product`)
- `app/Models/Order.php` payment strategy/deposit fields

## 16. Status rules

Status is calculated by weak leg, not total team PV.

Example 1:

```text
left = 60 000 PV
right = 50 000 PV
weak leg = 50 000 PV
Gold Director threshold 50 000 PV reached.
```

Example 2:

```text
left = 100 000 PV
right = 0 PV
weak leg = 0 PV
Status not reached.
```

Thresholds:

| Status | Weak-leg threshold |
| --- | ---: |
| Manager | 1 000 PV |
| Leader | 2 500 PV |
| Director | 5 000 PV |
| Bronze Director | 10 000 PV |
| Silver Director | 25 000 PV |
| Gold Director | 50 000 PV |
| Platinum Director | 100 000 PV |
| Emerald Director | 250 000 PV |
| Diamond Director | 500 000 PV |

Rules:

- PV is cumulative.
- Next status is calculated from total accumulated weak-leg turnover, not from zero.
- Only ELITE package users can receive status bonus.
- If user reaches status without ELITE, do not pay status bonus yet.
- When user later activates ELITE, system should check accumulated weak leg and pay missed status bonuses up to reached status.

Relevant backend areas:

- `app/Services/StatusService.php`
- `app/Services/StatusBonusService.php`
- `database/seeders/StatusBonusDefinitionSeeder.php`

## 17. X2 bonus rules

X2 bonus depends only on personally invited partners. It does not use whole structure.

Distribution requirement:

- 2 partners on one side and 3 on the other; or
- 3 partners on one side and 2 on the other.

`4/1` does not qualify.

Examples:

- X2 Director: 5 personally invited partners must be Director or above, distribution 2/3 or 3/2.
- X2 Gold: 5 personally invited partners must be Gold Director or above, distribution 2/3 or 3/2.
- X2 Diamond: 5 personally invited partners must be Diamond Director or above, distribution 2/3 or 3/2.

Do not limit personally invited partners to 5:

- user can invite 20, 50, 100, 140;
- X2 only checks whether at least 5 qualifying direct invites exist with correct distribution.

Relevant backend areas:

- `app/Services/X2BonusService.php`
- `database/seeders/X2BonusDefinitionSeeder.php`
- `app/Models/UserX2Bonus.php`

Implementation note:

- Existing code checks direct referrals and branch side.
- Make sure tests explicitly cover 2/3, 3/2, 4/1, more than 5 invites, inactive/deleted invite exclusion.

## 18. Withdrawals and accountant role

Accountant does not calculate MLM.

System calculates:

- referral bonus;
- binary bonus;
- statuses;
- X2;
- cashback;
- available balance.

Accountant only processes withdrawal requests.

Withdrawal process:

1. Partner creates withdrawal request.
2. Accountant sees request.
3. Accountant manually transfers money outside the system.
4. Accountant confirms or rejects request in system.

Important withdrawal accounting:

- On request, amount should move from available main balance to hold.
- On approve, do not deduct available balance again.
- On reject, return held amount to available balance.

> [!WARNING]
> There was a previous issue where withdrawal confirmation could deduct amount twice. Future changes around withdrawals must include tests for no double deduction.

Relevant backend area: `app/Services/WithdrawalService.php`.

## 19. Partner deletion policy

Do not hard delete partners casually.

Deletion affects:

- PV;
- binary structure;
- bonuses;
- statuses;
- wallets;
- transactions;
- orders;
- withdrawals;
- support/audit history.

If deletion is needed, do it as a separate feature:

- soft delete partner;
- rollback PV;
- rollback bonuses;
- recalculate tree;
- audit log;
- identity release only when explicitly approved;
- tests for deleted user exclusion from binary/status/PV.

For test period, safer approach:

- test completely;
- then clean/reset database before real launch.

Never run cleanup commands on production without backup and explicit confirmation.

Relevant backend areas:

- `app/Services/PartnerDeletionService.php`
- `app/Console/Commands/CleanupDeletedUserEffectsCommand.php`
- `app/Console/Commands/ReleaseDeletedUserIdentitiesCommand.php`
- `app/Console/Commands/SafiRecalculateMlmCommand.php`

## 20. Support chat requirements

Required behavior:

- Support should exist inside site.
- User can create support ticket.
- User can continue conversation in same ticket.
- User can upload file up to 5 MB.
- Admin can see ticket.
- Admin can reply.
- Admin can close/complete ticket.
- Telegram/WhatsApp floating external buttons should not replace in-site support.

Current support tables from migrations:

| Table | Purpose |
| --- | --- |
| `support_tickets` | Ticket header/status/assignment/close metadata. |
| `support_ticket_messages` | Conversation messages. |
| `support_message_attachments` | Files attached to messages. |

Current validation:

- `file` max: `5120` KB;
- allowed mimes: `jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt`.

Relevant backend areas:

- `app/Services/SupportTicketService.php`
- `app/Services/SupportAttachmentStorage.php`
- `app/Http/Requests/SupportTicket`
- `app/Http/Controllers/Api/Dashboard/SupportTicketController.php`
- `app/Http/Controllers/Api/Support/TicketController.php`

## 21. Admin UI notes

`/admin/structure`:

- Do not auto-open `root_id=1` by default.
- `/admin/structure` without `root_id` should show placeholder.
- Tree opens only after choosing partner or direct URL `?root_id=ID`.
- In root-orphan table, keep minimal columns only:
  - Partner;
  - Contacts;
  - Actions.

`/admin/partners`:

- Account column should be hidden.
- Partner active badge should depend on active package, not just account status.
- If user has no START/VIP/ELITE package, show package inactive.
- Search should work globally, not only current page.

Structure tree:

- Tree needs zoom, card size, density controls.
- Cards must show personal PV and left/right branch PV.
- PV values must not be hidden/trimmed.
- Tree card height should be auto if needed.

Relevant frontend files:

- `resources/js/safi/pages/admin/AdminStructure.tsx`
- `resources/js/safi/pages/admin/AdminPartners.tsx`
- `resources/js/safi/pages/admin/AdminPartnerDetail.tsx`

## 22. User dashboard notes

Dashboard should show:

- available balance;
- total earned;
- binary pending separately;
- deposit balance;
- package/status progress based on weak leg;
- referral links only if user has active package.

If user has no active package:

- no referral links;
- backend should reject referral registration through inactive referrer;
- UI should explain that referral links become available after package activation.

Do not mix unrelated values:

- personal PV;
- team PV;
- wallet balance;
- total earned;
- status progress;
- pending binary.

Remove duplicate/unnecessary info from pages where possible. Dashboard should be readable for a partner who is checking money and progress, not debugging backend state.

Relevant frontend files:

- `resources/js/safi/pages/dashboard/Overview.tsx`
- `resources/js/safi/pages/dashboard/Structure.tsx`
- `resources/js/safi/pages/dashboard/Transactions.tsx`
- `resources/js/safi/pages/dashboard/Bonuses.tsx`
- `resources/js/safi/pages/dashboard/PackageStatus.tsx`
- `resources/js/safi/pages/dashboard/Support.tsx`

Relevant backend areas:

- `app/Http/Controllers/Api/Dashboard/OverviewController.php`
- `app/Http/Controllers/Api/Dashboard/EarningsSummaryController.php`
- `app/Http/Controllers/Api/ReferralController.php`

## 23. Payments / TipTopPay

TipTopPay integration exists/expected.

Payment pages and site compliance must include:

- online payment information;
- dispute contacts: phone/email;
- offer agreement for Kazakhstan;
- prices in KZT;
- privacy policy;
- legal entity details;
- refund rules;
- delivery rules if delivery exists.

TipTopPay payment safety text:

- Visa/Mastercard cards;
- protected TipTopPay payment page;
- 3-D Secure;
- card data is not stored on site;
- support email from TipTopPay materials.

Do not include private terminal secret in docs.

Mention only config keys without values:

```dotenv
TIPTOPPAY_ENABLED
TIPTOPPAY_TEST_MODE
TIPTOPPAY_PUBLIC_TERMINAL_ID
TIPTOPPAY_SECRET_KEY
TIPTOPPAY_CURRENCY=KZT
```

Production checklist:

- test mode false only after TipTopPay approves;
- public_id/terminal id confirmed;
- Pay/Check/Fail/Confirm/Cancel callbacks configured;
- legal/payment pages visible on site;
- prices shown in KZT;
- callback handling covered by tests;
- no full private key or secret appears in frontend bundle, docs, logs or screenshots.

Relevant backend/frontend areas:

- `config/tiptoppay.php`
- `app/Services/Payments/TipTopPayService.php`
- `app/Http/Controllers/Api/Payments/TipTopPayIntentController.php`
- `app/Http/Controllers/Api/Payments/TipTopPayWebhookController.php`
- `resources/js/safi/hooks/useTipTopPayWidget.ts`
- `resources/js/safi/lib/tiptoppay.ts`
- `/payment`, `/payment/success`, `/payment/fail`, `/legal/*`

## 24. Notifications

Required notifications:

When new status reached:

- show notification;
- create notification record;
- create transaction/bonus record if payout exists;
- example: `Поздравляем! Вы достигли статуса Manager.`

When bonus credited:

- referral bonus;
- binary bonus;
- status bonus;
- X2 bonus;
- cashback.

Relevant backend areas:

- `database/migrations/2026_06_04_000003_create_notifications_table.php`
- `app/Notifications/StatusAchievedNotification.php`
- `app/Notifications/BonusAccruedNotification.php`
- `app/Notifications/X2BonusAwardedNotification.php`
- `app/Services/TransactionNotificationTextFactory.php`

## 25. Known critical commands and incidents

Recent production incident, generic summary:

- A destructive production command was accidentally run.
- Database was restored from backup.

Emergency rule:

Before any risky production command, create backup:

```bash
mysqldump --no-tablespaces \
  -h "${DB_HOST:-127.0.0.1}" \
  -P "${DB_PORT:-3306}" \
  -u "$DB_USERNAME" \
  -p"$DB_PASSWORD" \
  "$DB_DATABASE" > /root/backups/safilife_before_<change>_<date>.sql
```

Never run:

```bash
php artisan migrate:fresh
php artisan db:seed --force
```

without explicit confirmation and fresh backup.

Restore backup pattern:

```bash
BACKUP_FILE="/root/backups/safilife_before_<change>_<date>.sql"
mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < "$BACKUP_FILE"
```

Do not use one old backup filename as the only source of truth. Always verify the intended backup file by timestamp, size and change context.

## 26. Current open issues / TODO backlog

### Backend

- [ ] Finish binary pending ledger architecture.
- [ ] Add binary income limits.
- [ ] Ensure PV is not package price.
- [ ] Ensure buyer does not receive own PV into left/right.
- [ ] Ensure ELITE part excluded from binary/referral.
- [ ] Ensure status uses weak leg.
- [ ] Ensure status bonus requires ELITE.
- [ ] Ensure X2 checks direct invites only and 2/3 distribution.
- [ ] Protect dangerous production commands.
- [ ] Add audit logs for manual admin corrections.
- [ ] Add tests for withdrawal no double deduction.
- [ ] Review partner deletion flow.

### Frontend

- [ ] Admin partners: hide account column.
- [ ] Admin partners: active badge based on active package.
- [ ] Admin structure: no default root tree.
- [ ] Admin structure: root orphan table minimal columns.
- [ ] Dashboard: binary pending card.
- [ ] Dashboard: referral links hidden without active package.
- [ ] Profile photo upload button should work.
- [ ] Languages/status labels should be translated.
- [ ] Support chat needs full conversation flow and attachment upload.
- [ ] Payment/legal pages need TipTopPay compliance text.

### Payments

- [ ] Complete TipTopPay production readiness.
- [ ] Ensure KZT prices.
- [ ] Ensure privacy/offer/refund/delivery/legal pages are accessible.

## 27. Testing checklist

Backend smoke:

```bash
cd /var/www/project7

php artisan optimize:clear
php artisan route:list
php artisan test
```

Frontend:

```bash
cd /var/www/project7

npm run build
```

MLM-specific tests:

- [ ] Package PV tests.
- [ ] Manual package assignment tests.
- [ ] PV propagation tests.
- [ ] Referral bonus tests.
- [ ] Binary bonus tests.
- [ ] Deposit tests.
- [ ] Status tests.
- [ ] X2 tests.
- [ ] Withdrawal tests.
- [ ] Support chat tests.
- [ ] TipTopPay callback tests.

Manual QA:

- [ ] register partner with START;
- [ ] register partner with VIP;
- [ ] upgrade START to VIP;
- [ ] upgrade VIP to ELITE;
- [ ] check left/right structure;
- [ ] check weak leg status;
- [ ] check referral bonus;
- [ ] check binary pending;
- [ ] release binary;
- [ ] check main/deposit split;
- [ ] create withdrawal request;
- [ ] confirm withdrawal once;
- [ ] check no double deduction;
- [ ] create support ticket;
- [ ] admin reply;
- [ ] close support ticket;
- [ ] buy normal product;
- [ ] buy deposit product;
- [ ] check cart restrictions.

Existing test areas worth checking before touching business logic:

- `tests/Feature/BusinessRules`
- `tests/Feature/PackageUpgradeTest.php`
- `tests/Feature/PvAccrualTest.php`
- `tests/Feature/BinaryBonusCalculationTest.php`
- `tests/Feature/X2BonusServiceTest.php`
- `tests/Feature/WithdrawalApiTest.php`
- `tests/Feature/SupportChatTest.php`
- `tests/Feature/TipTopPayWebhookTest.php`
- `tests/Unit/PackageBusinessRulesTest.php`

## 28. Deployment checklist

### Before deploy

- [ ] `git status` clean.
- [ ] Tests/build pass.
- [ ] Backup created.
- [ ] Migrations reviewed.
- [ ] Env changes reviewed.
- [ ] Confirm no secrets in git.
- [ ] Confirm no accidental demo seeders for production.

### Deploy

```bash
cd /var/www/project7

# pull/deploy through approved process
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

# if Supervisor is used:
supervisorctl status
# supervisorctl restart <program-name>
```

### After deploy

- [ ] Check logs.
- [ ] Check `/admin/partners`.
- [ ] Check `/admin/structure`.
- [ ] Check `/dashboard`.
- [ ] Check payment page.
- [ ] Check queues.
- [ ] Check `failed_jobs`.
- [ ] Check support ticket create/reply flow if support changed.
- [ ] Check at least one package/status/balance screen if MLM changed.

## 29. Documentation quality requirements for future updates

Keep this handover useful for a new developer:

- Use headings, tables, code blocks, warning callouts and checklists.
- Do not write vague text like "fix later" without explaining what needs fixing.
- Where exact code class names are unknown, inspect repository first.
- If still unknown, write: `TODO: locate exact class/service.`
- Do not invent class names as facts.
- Do not paste secrets, tokens, DB passwords, private API keys or full Sentry DSNs.
- Update this file when business rules change, especially for MLM money logic.

## 30. Quick orientation for next developer

Start here:

1. Read `routes/api.php` and `resources/js/safi/router/routes.tsx`.
2. For package/PV changes, read `PackageService`, `PvService`, `PackageSeeder`.
3. For bonuses, read `BonusService`, `StatusBonusService`, `X2BonusService`.
4. For balances/withdrawals, read `WalletService`, `WithdrawalService`.
5. For support, read `SupportTicketService` and support migrations.
6. Before changing production-affecting logic, add or update tests.
7. Before any production command, create a backup and get explicit confirmation.


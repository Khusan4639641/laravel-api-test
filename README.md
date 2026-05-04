# MLM Binary Platform API

Laravel 13 API backend for an MLM binary platform. The project implements user registration, referral links, binary tree placement, package activation and upgrades, PV accrual, referral and binary bonuses, wallets, withdrawals, products, orders, admin endpoints, and notifications.

## Main Modules

- Auth with Laravel Sanctum
- Users, profiles, roles, and MLM statuses
- Referral links and sponsor relationships
- Binary tree with spillover placement
- Packages, activation, and upgrade chain
- PV accrual up the binary tree
- Referral bonus and binary bonus calculation
- Wallets and wallet transactions
- Withdrawal requests and admin approval/rejection
- Products catalog and user orders
- Minimal admin API
- Mail/log notifications for key events

## Requirements

- PHP `^8.3`
- Composer
- MySQL or SQLite
- Node/NPM only if frontend assets need to be built

## Installation

Install PHP dependencies:

```bash
composer install
```

Create environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Configure `.env` database values. Example for MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_tz
DB_USERNAME=root
DB_PASSWORD=root
```

For local SQLite:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

Mail is currently intended for log/array usage during development:

```env
MAIL_MAILER=log
```

Run migrations and seeders:

```bash
php artisan migrate:fresh --seed
```

Run tests:

```bash
php artisan test
```

Useful test command for isolated SQLite:

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: SESSION_DRIVER=array CACHE_STORE=array QUEUE_CONNECTION=sync LOG_CHANNEL=null php artisan test
```

## Main API Endpoints

Auth:

- `POST /api/register`
- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`

Referral:

- `GET /api/ref/{user_id}/{branch}`

Packages:

- `POST /api/packages/{package}/activate`
- `POST /api/packages/{package}/upgrade`

Bonuses:

- `POST /api/bonuses/binary/calculate`

Withdrawals:

- `POST /api/withdrawals`
- `GET /api/withdrawals`

Admin:

- `GET /api/admin/users`
- `GET /api/admin/withdrawals`
- `PATCH /api/admin/withdrawals/{withdrawal}/approve`
- `PATCH /api/admin/withdrawals/{withdrawal}/reject`

Products:

- `GET /api/products`
- `GET /api/products/{product}`

Orders:

- `POST /api/orders`
- `GET /api/orders`
- `GET /api/orders/{order}`

## Documentation

- Full API overview: [docs/API.md](docs/API.md)
- Backend architecture: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)

## Seeded Data

Package seed data:

- `START`
- `BUSINESS`
- `VIP`
- `ELITE`

Product seed data includes sample supplements and cosmetics.

## Artisan Commands

Recalculate MLM statuses for all users:

```bash
php artisan mlm:recalculate-statuses
```

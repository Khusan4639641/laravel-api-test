# Backend Architecture

This project is a Laravel 13 API backend for an MLM platform with binary tree placement, package activation/upgrades, bonuses, wallets, withdrawals, products, and orders.

## Main Domains

### Users

Model: `App\Models\User`

Important fields:

- `login`
- `email`
- `sponsor_id`
- `current_package_id`
- `role`: `user` or `admin`
- `status`: MLM status/rank derived from `total_pv`
- `left_pv`
- `right_pv`
- `remaining_left_pv`
- `remaining_right_pv`
- `total_pv`

Relations:

- sponsor/referrals
- current package
- profile
- binary node
- wallets
- orders
- bonus transactions
- withdrawal requests

### Packages

Model: `App\Models\Package`

Packages represent MLM product packages:

- `START`
- `BUSINESS`
- `VIP`
- `ELITE`

Important fields:

- `code`
- `price`
- `pv`
- `referral_percent`
- `binary_percent`
- `sort_order`
- `is_active`

Seed data is created in `Database\Seeders\PackageSeeder`.

### Binary Tree

Model: `App\Models\BinaryNode`

Each binary node links one user into the binary MLM structure:

- `user_id`
- `parent_id`
- `position`: `L` or `R`
- `depth`
- `path`

Service: `App\Services\BinaryTreeService`

Responsibilities:

- place a user under a sponsor;
- create sponsor root node if needed;
- find free positions;
- apply spillover when the requested branch is already occupied.

### Spillover

Spillover starts at the selected sponsor branch:

1. If sponsor has free `L` or `R` slot for requested branch, the user is placed directly.
2. If branch is occupied, the service traverses down that branch breadth-first.
3. The first node with a free child slot receives the new user.

This keeps placement deterministic and near the requested sponsor branch.

### PV Accrual

Service: `App\Services\PvService`

PV is added during package activation and package upgrade.

Flow:

1. Buyer receives personal `total_pv`.
2. If buyer has a `binary_nodes` entry, the service walks up to all parents.
3. For each parent:
   - if child is in `L`, parent receives `left_pv`, `remaining_left_pv`, and `total_pv`;
   - if child is in `R`, parent receives `right_pv`, `remaining_right_pv`, and `total_pv`.
4. After PV changes, `StatusService` recalculates user status.

### Statuses

Service: `App\Services\StatusService`

User status is derived from `users.total_pv`:

- `manager`: `1000`
- `leader`: `2500`
- `director`: `5000`
- `bronze_director`: `10000`
- `silver_director`: `25000`
- `gold_director`: `50000`
- `platinum_director`: `100000`
- `emerald_director`: `250000`
- `diamond_director`: `500000`

Below `1000`, status is `user`.

Artisan command:

```bash
php artisan mlm:recalculate-statuses
```

The command recalculates statuses for all users.

## Bonuses

### Referral Bonus

Service: `App\Services\BonusService`

Referral bonus is created when a referred user activates a package.

Logic:

1. Find sponsor by `users.sponsor_id`.
2. Read `referral_percent` from sponsor's current package.
3. Calculate bonus from activated package price.
4. Credit sponsor's `main` wallet.
5. Create:
   - `bonus_transactions`
   - `wallet_transactions`
6. Send `BonusAccruedNotification` to sponsor.

The percentage is based on sponsor package because bonus qualification should depend on the receiver's MLM level.

### Binary Bonus

Service: `App\Services\BonusService`

Endpoint:

```http
POST /api/bonuses/binary/calculate
```

Logic:

1. `base_pv = min(remaining_left_pv, remaining_right_pv)`.
2. Use `current_package.binary_percent`.
3. Calculate bonus amount.
4. Split:
   - `90%` to `main` wallet;
   - `10%` to `bonus` wallet as deposit wallet.
5. Subtract matched PV from both remaining branches.
6. Keep leftover PV in remaining branch fields.
7. Create:
   - one `bonus_transactions` row;
   - two `wallet_transactions` rows.
8. Send `BonusAccruedNotification` to user.

## Package Activation And Upgrade

Service: `App\Services\PackageService`

### Activation

Endpoint:

```http
POST /api/packages/{package}/activate
```

Activation:

- updates `current_package_id`;
- adds full package PV;
- accrues PV up binary tree;
- calculates referral bonus if sponsor exists;
- rejects direct ELITE activation.

### Upgrade

Endpoint:

```http
POST /api/packages/{package}/upgrade
```

Allowed chain:

- `START -> BUSINESS`
- `BUSINESS -> VIP`
- `VIP -> ELITE`

Rules:

- skipping package levels is rejected;
- user must already have a package;
- payment amount is `target_package.price - current_package.price`;
- additional PV is `target_package.pv - current_package.pv`;
- cashback is credited to `bonus` wallet except `VIP -> ELITE`;
- cashback transaction type is `package_upgrade_cashback`.

Current cashback rule:

- `10%` of payment amount.

This is implemented in service code because the current schema has no dedicated cashback percent column.

## Wallets

Models:

- `App\Models\Wallet`
- `App\Models\WalletTransaction`

Wallet types:

- `main`
- `bonus`

Main wallet is used for referral bonuses, 90% binary bonus, and withdrawals.

Bonus wallet is used for:

- 10% binary deposit part;
- package upgrade cashback.

All money movement should create immutable `wallet_transactions` rows.

## Withdrawals

Model: `App\Models\WithdrawalRequest`

Service: `App\Services\WithdrawalService`

User flow:

1. User submits withdrawal request from `main` wallet.
2. Amount must be greater than zero.
3. Main wallet must have enough `balance`.
4. `balance` is decreased.
5. `hold_balance` is increased.
6. `withdrawal_requests.status = pending`.
7. `wallet_transactions.type = withdrawal_hold`.
8. Send `WithdrawalRequestedNotification`.

Admin approve:

- only `pending` requests;
- decreases `hold_balance`;
- creates `withdrawal_approve` wallet transaction.

Admin reject:

- only `pending` requests;
- decreases `hold_balance`;
- returns funds to `balance`;
- creates `withdrawal_reject` wallet transaction.

## Products And Orders

Models:

- `App\Models\Product`
- `App\Models\Order`
- `App\Models\OrderItem`

Products are seeded by `Database\Seeders\ProductSeeder`.

Order flow:

1. Authenticated user submits items:
   - `product_id`
   - `quantity`
2. Product must be active.
3. Quantity must be greater than zero.
4. Price and PV are read from products table.
5. Order totals are calculated server-side.
6. `orders.status = pending`.
7. `orders.payment_status = pending`.
8. `order_items` store unit price, total price, unit PV, total PV, and product snapshot.

Users can only list and view their own orders.

## Admin API

Admin access is controlled by:

- `users.role = admin`
- `App\Http\Middleware\EnsureAdmin`

Admin endpoints:

- list users;
- list withdrawals;
- approve withdrawals;
- reject withdrawals.

## Notifications

Notifications use Laravel Notifications with mail channel.

Classes:

- `UserRegisteredNotification`
- `BonusAccruedNotification`
- `WithdrawalRequestedNotification`

Current `.env` uses `MAIL_MAILER=log`, so notification mail is logged instead of sent through real SMTP.

Events:

- registration sends notification to user;
- referral/binary bonus sends notification to bonus receiver;
- withdrawal request sends notification to user.

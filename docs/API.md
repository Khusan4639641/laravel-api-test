# API Overview

Base path: `/api`

Authenticated endpoints use Laravel Sanctum bearer tokens:

```http
Authorization: Bearer <token>
Accept: application/json
```

## Auth

### Register

- Method: `POST`
- URL: `/api/register`
- Auth required: no

Body:

```json
{
  "name": "John Doe",
  "login": "john_doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "sponsor_id": 1,
  "branch": "L",
  "package_id": 1
}
```

Response:

```json
{
  "user": {
    "id": 2,
    "name": "John Doe",
    "login": "john_doe",
    "email": "john@example.com",
    "sponsor_id": 1,
    "current_package_id": 1,
    "status": "user"
  },
  "token": "1|plain-text-token"
}
```

### Login

- Method: `POST`
- URL: `/api/login`
- Auth required: no

Body:

```json
{
  "login": "john_doe",
  "password": "password123"
}
```

Alternative body:

```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

Response:

```json
{
  "user": {
    "id": 2,
    "login": "john_doe",
    "email": "john@example.com"
  },
  "token": "1|plain-text-token"
}
```

### Logout

- Method: `POST`
- URL: `/api/logout`
- Auth required: yes

Body: none

Response:

```json
{
  "message": "Logged out."
}
```

### Current User

- Method: `GET`
- URL: `/api/me`
- Auth required: yes

Body: none

Response:

```json
{
  "user": {
    "id": 2,
    "login": "john_doe",
    "profile": {},
    "wallets": [],
    "current_package": {}
  }
}
```

## Referral

### Check Referral Link

- Method: `GET`
- URL: `/api/ref/{user_id}/{branch}`
- Auth required: no
- `branch`: `L` or `R`

Body: none

Response:

```json
{
  "sponsor": {
    "id": 1,
    "name": "Sponsor",
    "login": "sponsor"
  },
  "branch": "L",
  "is_valid": true,
  "direct_branch_available": false,
  "spillover_parent_id": 5
}
```

## Packages

### Activate Package

- Method: `POST`
- URL: `/api/packages/{package}/activate`
- Auth required: yes

Body: none

Response:

```json
{
  "user": {
    "id": 2,
    "current_package_id": 1,
    "total_pv": "30000.00",
    "status": "silver_director",
    "current_package": {
      "id": 1,
      "code": "START"
    }
  }
}
```

Notes:

- `ELITE` cannot be activated directly.
- If the user has `sponsor_id`, referral bonus is calculated for sponsor.
- Package PV is added to user and binary parents.

### Upgrade Package

- Method: `POST`
- URL: `/api/packages/{package}/upgrade`
- Auth required: yes

Body: none

Response:

```json
{
  "user": {
    "id": 2,
    "current_package_id": 2,
    "total_pv": "60000.00",
    "status": "gold_director"
  },
  "payment_amount": "30000.00",
  "additional_pv": "30000.00",
  "cashback_amount": "3000.00"
}
```

Rules:

- Allowed chain: `START -> BUSINESS -> VIP -> ELITE`.
- Skipping steps is rejected.
- Cashback is credited to `bonus` wallet except `VIP -> ELITE`.

## Binary Bonus

### Calculate Binary Bonus

- Method: `POST`
- URL: `/api/bonuses/binary/calculate`
- Auth required: yes

Body: none

Response when bonus is available:

```json
{
  "bonus_transaction": {
    "id": 10,
    "bonus_type": "binary",
    "amount": "60.00",
    "matched_pv": "600.00",
    "metadata": {
      "base_pv": "600.00",
      "binary_percent": "10.00",
      "main_amount": "54.00",
      "bonus_amount": "6.00"
    }
  }
}
```

Response when no bonus is available:

```json
{
  "message": "No binary bonus available.",
  "bonus_transaction": null
}
```

## Withdrawals

### Create Withdrawal Request

- Method: `POST`
- URL: `/api/withdrawals`
- Auth required: yes

Body:

```json
{
  "amount": 250,
  "payment_method": "card",
  "payment_details": {
    "card_last4": "4242"
  }
}
```

Response:

```json
{
  "withdrawal": {
    "id": 1,
    "user_id": 2,
    "wallet_id": 1,
    "amount": "250.00",
    "net_amount": "250.00",
    "status": "pending"
  }
}
```

Notes:

- Withdrawals use only `main` wallet.
- Amount must be greater than zero.
- Wallet balance is reduced and `hold_balance` is increased.
- A `wallet_transactions` row is created with type `withdrawal_hold`.

### List My Withdrawals

- Method: `GET`
- URL: `/api/withdrawals`
- Auth required: yes

Body: none

Response:

```json
{
  "withdrawals": [
    {
      "id": 1,
      "amount": "250.00",
      "status": "pending"
    }
  ]
}
```

## Admin

Admin endpoints require Sanctum auth and `users.role = admin`.

### List Users

- Method: `GET`
- URL: `/api/admin/users`
- Auth required: yes, admin

Body: none

Response:

```json
{
  "users": [
    {
      "id": 1,
      "login": "admin",
      "role": "admin",
      "status": "user"
    }
  ]
}
```

### List Withdrawals

- Method: `GET`
- URL: `/api/admin/withdrawals`
- Auth required: yes, admin

Body: none

Response:

```json
{
  "withdrawals": [
    {
      "id": 1,
      "amount": "250.00",
      "status": "pending",
      "user": {},
      "wallet": {}
    }
  ]
}
```

### Approve Withdrawal

- Method: `PATCH`
- URL: `/api/admin/withdrawals/{withdrawal}/approve`
- Auth required: yes, admin

Body: none

Response:

```json
{
  "withdrawal": {
    "id": 1,
    "status": "approved",
    "processed_at": "2026-05-04T00:00:00.000000Z"
  }
}
```

Notes:

- Only `pending` withdrawals can be approved.
- Approval decreases wallet `hold_balance`.
- A `wallet_transactions` row is created with type `withdrawal_approve`.

### Reject Withdrawal

- Method: `PATCH`
- URL: `/api/admin/withdrawals/{withdrawal}/reject`
- Auth required: yes, admin

Body:

```json
{
  "reason": "Invalid payment details"
}
```

Response:

```json
{
  "withdrawal": {
    "id": 1,
    "status": "rejected",
    "admin_comment": "Invalid payment details"
  }
}
```

Notes:

- Only `pending` withdrawals can be rejected.
- Rejection returns amount from `hold_balance` to `balance`.
- A `wallet_transactions` row is created with type `withdrawal_reject`.

## Products

### List Products

- Method: `GET`
- URL: `/api/products`
- Auth required: no

Body: none

Response:

```json
{
  "products": [
    {
      "id": 1,
      "name": "Omega Balance",
      "sku": "BAD-OMEGA-001",
      "price": "120000.00",
      "pv": "120000.00",
      "status": "active"
    }
  ]
}
```

### Show Product

- Method: `GET`
- URL: `/api/products/{product}`
- Auth required: no

Body: none

Response:

```json
{
  "product": {
    "id": 1,
    "name": "Omega Balance",
    "sku": "BAD-OMEGA-001",
    "price": "120000.00",
    "pv": "120000.00"
  }
}
```

Notes:

- Inactive products return `404`.

## Orders

### Create Order

- Method: `POST`
- URL: `/api/orders`
- Auth required: yes

Body:

```json
{
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 2,
      "quantity": 1
    }
  ],
  "shipping_address": {
    "city": "Tashkent",
    "address": "Example street"
  }
}
```

Response:

```json
{
  "order": {
    "id": 1,
    "order_number": "ORD-20260504153000-ABC123",
    "status": "pending",
    "payment_status": "pending",
    "subtotal_amount": "330000.00",
    "total_amount": "330000.00",
    "total_pv": "330000.00",
    "items": [
      {
        "product_id": 1,
        "quantity": 2,
        "unit_price": "120000.00",
        "total_price": "240000.00"
      }
    ]
  }
}
```

Notes:

- Product price and PV are always taken from DB.
- Inactive products are rejected.
- `quantity` must be greater than zero.

### List My Orders

- Method: `GET`
- URL: `/api/orders`
- Auth required: yes

Body: none

Response:

```json
{
  "orders": [
    {
      "id": 1,
      "order_number": "ORD-20260504153000-ABC123",
      "status": "pending",
      "items": []
    }
  ]
}
```

### Show My Order

- Method: `GET`
- URL: `/api/orders/{order}`
- Auth required: yes

Body: none

Response:

```json
{
  "order": {
    "id": 1,
    "order_number": "ORD-20260504153000-ABC123",
    "items": []
  }
}
```

Notes:

- Users can access only their own orders.

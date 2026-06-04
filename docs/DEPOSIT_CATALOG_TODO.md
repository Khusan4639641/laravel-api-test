# Deposit Catalog TODO

Backend API is available:

- `GET /api/dashboard/deposit-products`
- `POST /api/deposits/purchase` with `product_id` and `quantity`

Frontend TODO:

- Add a small dashboard entry for the deposit catalog.
- Render products from `getDashboardDepositProducts()`.
- Purchase with `createDepositPurchase({ product_id, quantity })`.
- Show deposit balance before purchase and cashback preview: `total_amount * 20%`.

Current implementation intentionally avoids a large new UI.

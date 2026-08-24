# Cart, Order And Stock Audit

## What Already Existed

- Backend already had product, order, and order item models:
  - `app/Models/Product.php`
  - `app/Models/Order.php`
  - `app/Models/OrderItem.php`
- Backend already had public/dashboard/admin product endpoints and user order endpoints.
- `products.stock_quantity` already existed in the initial products migration.
- `orders` and `order_items` tables already existed.
- `OrderController::store()` already used a database transaction and product row locking.
- Admin products page already allowed editing product stock through `stock_quantity`.

## Missing Before This Change

- No frontend cart route existed in `resources/js/safi`.
- No frontend `CartContext` existed in `resources/js/safi`; the reference cart used mock product data.
- Public `/products` used API products, but the button linked to `/contacts` instead of adding to cart.
- Dashboard `/dashboard/products` created a one-item order immediately instead of using cart checkout.
- Header had no cart icon/badge.
- Backend order creation did not enforce stock availability and did not decrement stock.
- Order item did not store a direct `product_name` snapshot column.
- Products did not have `reserved_quantity` or `image_path` columns.

## Existing Endpoints

Public:

- `GET /api/public/products`
- `GET /api/public/products/{product}`
- `GET /api/products`
- `GET /api/products/{product}`

User:

- `GET /api/orders`
- `GET /api/orders/{order}`
- `POST /api/orders`
- `GET /api/dashboard/products`
- `GET /api/dashboard/orders`

Admin:

- `GET /api/admin/products`
- `GET /api/admin/products/{product}`
- `POST /api/admin/products`
- `PUT /api/admin/products/{product}`
- `DELETE /api/admin/products/{product}`
- `GET /api/admin/orders`

## Endpoints Added Or Completed

- `GET /api/admin/orders/{order}`
- `PATCH /api/admin/orders/{order}/status`

`POST /api/orders` was updated to enforce stock rules and decrement stock safely.

## Frontend Mock Usage

- Current local `resources/js/safi/pages/ProductsPage.tsx` already used backend API products.
- Current local `resources/js/safi/pages/dashboard/Products.tsx` already used backend API products.
- The reference `/home/tempadmin/Загрузки/safi-life-website/src/context/CartContext.tsx` used mock `data/products`; local implementation does not use that mock and stores API product snapshots in localStorage.

## Stock Storage

Current product stock fields:

- `products.stock_quantity`: available stock used for ordering.
- `products.reserved_quantity`: added as a default `0` field for future reservation flows.
- `products.status`: `active` products can be shown/ordered; inactive products cannot be ordered.

Ordering behavior:

- If `stock_quantity <= 0`, the product is out of stock.
- If requested quantity is greater than `stock_quantity`, backend returns validation error.
- On successful `POST /api/orders`, stock is decremented inside `DB::transaction()` after `lockForUpdate()`.
- Bonus/PV business effects are not triggered by product order checkout; orders are created as `pending`.

## Frontend Pages Updated

- `resources/js/safi/pages/ProductsPage.tsx`
- `resources/js/safi/pages/dashboard/Products.tsx`
- `resources/js/safi/pages/CartPage.tsx`
- `resources/js/safi/context/CartContext.tsx`
- `resources/js/safi/components/layout/Header.tsx`
- `resources/js/safi/router/routes.tsx`

## Tests Added

- `tests/Feature/Orders/ProductStockOrderTest.php`

Covered behavior:

- User can create order with available stock.
- User cannot order more than stock.
- User cannot order inactive product.
- User cannot order zero-stock product.
- Order items store price/PV/product snapshots.
- Unauthenticated user cannot create order.
- Super admin can update stock.
- Order creation uses transaction and row locking.


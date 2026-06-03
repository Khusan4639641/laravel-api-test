<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = OrderResource::collection($request->user()
            ->orders()
            ->with(['items.product', 'items.package'])
            ->withSum('items as items_count', 'quantity')
            ->latest()
            ->get());

        return response()->json([
            'data' => $orders,
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        return response()->json([
            'order' => OrderResource::make($order->load(['items.product', 'items.package'])),
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $order = DB::transaction(function () use ($validated, $user): Order {
            $requestedQuantities = collect($validated['items'])
                ->groupBy('product_id')
                ->map(fn ($items): int => $items->sum(fn (array $item): int => (int) $item['quantity']));

            $products = Product::query()
                ->whereIn('id', $requestedQuantities->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($requestedQuantities as $productId => $quantity) {
                /** @var Product|null $product */
                $product = $products->get($productId);

                if (! $product || $product->status !== 'active') {
                    throw ValidationException::withMessages([
                        'items' => 'Товар недоступен для заказа',
                    ]);
                }

                if ((int) $product->stock_quantity <= 0 || $quantity > (int) $product->stock_quantity) {
                    throw ValidationException::withMessages([
                        'items' => 'Недостаточно товара на складе',
                    ]);
                }
            }

            $subtotal = '0';
            $totalPv = '0';
            $preparedItems = [];

            foreach ($validated['items'] as $item) {
                /** @var Product|null $product */
                $product = $products->get($item['product_id']);

                $quantity = (int) $item['quantity'];
                $totalPrice = bcmul((string) $product->price, (string) $quantity, 2);
                $itemPv = bcmul((string) $product->pv, (string) $quantity, 2);
                $subtotal = bcadd($subtotal, $totalPrice, 2);
                $totalPv = bcadd($totalPv, $itemPv, 2);

                $preparedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'total_price' => $totalPrice,
                    'unit_pv' => $product->pv,
                    'total_pv' => $itemPv,
                ];
            }

            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $this->makeOrderNumber(),
                'status' => 'pending',
                'payment_status' => 'pending',
                'subtotal_amount' => $subtotal,
                'discount_amount' => 0,
                'total_amount' => $subtotal,
                'total_pv' => $totalPv,
                'shipping_address' => $validated['shipping_address'] ?? null,
            ]);

            foreach ($preparedItems as $preparedItem) {
                /** @var Product $product */
                $product = $preparedItem['product'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $preparedItem['quantity'],
                    'unit_price' => $preparedItem['unit_price'],
                    'total_price' => $preparedItem['total_price'],
                    'unit_pv' => $preparedItem['unit_pv'],
                    'total_pv' => $preparedItem['total_pv'],
                    'item_snapshot' => [
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => (string) $product->price,
                        'pv' => (string) $product->pv,
                    ],
                ]);
            }

            foreach ($requestedQuantities as $productId => $quantity) {
                /** @var Product $product */
                $product = $products->get($productId);
                $product->decrement('stock_quantity', $quantity);
            }

            return $order->load('items.product');
        });

        return response()->json([
            'order' => OrderResource::make($order),
        ], 201);
    }

    private function makeOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}

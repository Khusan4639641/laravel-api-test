<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
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
        return response()->json([
            'orders' => $request->user()
                ->orders()
                ->with('items.product')
                ->latest()
                ->get(),
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        return response()->json([
            'order' => $order->load('items.product'),
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $order = DB::transaction(function () use ($validated, $user): Order {
            $products = Product::query()
                ->whereIn('id', collect($validated['items'])->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = '0';
            $totalPv = '0';
            $preparedItems = [];

            foreach ($validated['items'] as $item) {
                /** @var Product|null $product */
                $product = $products->get($item['product_id']);

                if (! $product || $product->status !== 'active') {
                    throw ValidationException::withMessages([
                        'items' => 'Order contains inactive or unavailable product.',
                    ]);
                }

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

            return $order->load('items.product');
        });

        return response()->json([
            'order' => $order,
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

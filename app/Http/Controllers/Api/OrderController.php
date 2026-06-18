<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\CheckoutOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly CheckoutOrderService $checkoutOrderService,
    ) {
    }

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
        $order = $this->checkoutOrderService->create($request->user(), $request->validated());

        return response()->json([
            'order' => OrderResource::make($order),
        ], 201);
    }
}

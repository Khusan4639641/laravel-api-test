<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['user.profile', 'items.product', 'items.package'])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($orders, OrderResource::class, 'orders', $request);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'order' => OrderResource::make($order->load(['user.profile', 'items.product', 'items.package'])),
        ]);
    }

    public function status(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'processing', 'completed', 'cancelled'])],
        ]);

        $order->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'order' => OrderResource::make($order->refresh()->load(['user.profile', 'items.product', 'items.package'])),
        ]);
    }
}

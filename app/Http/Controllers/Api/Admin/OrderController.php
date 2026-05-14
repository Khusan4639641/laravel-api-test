<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}

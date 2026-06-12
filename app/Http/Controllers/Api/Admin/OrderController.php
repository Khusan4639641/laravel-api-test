<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\PackageAutoUpgradeFromPaidOrdersService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use RespondsWithPagination;

    private const ORDER_STATUSES = ['pending', 'confirmed', 'cancelled', 'completed', 'shipped'];
    private const PAYMENT_STATUSES = ['unpaid', 'pending', 'paid', 'failed', 'refunded', 'cancelled'];

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $paymentStatus = trim((string) $request->query('payment_status', ''));

        $orders = Order::query()
            ->with(['user.profile', 'items.product', 'items.package'])
            ->withSum('items as items_count', 'quantity')
            ->where('status', '!=', 'voided')
            ->whereHas('user', fn (Builder $query) => $query->activeAccount())
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($paymentStatus !== '' && in_array($paymentStatus, self::PAYMENT_STATUSES, true), fn (Builder $query) => $query->where('payment_status', $paymentStatus))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('order_number', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('delivery_address', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('login', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhereHas('profile', function (Builder $profileQuery) use ($search): void {
                                    $profileQuery->where('phone', 'like', "%{$search}%")
                                        ->orWhere('city', 'like', "%{$search}%");
                                });
                        });

                    if (ctype_digit($search)) {
                        $nested->orWhere('id', (int) $search)
                            ->orWhere('user_id', (int) $search);
                    }
                });
            })
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($orders, OrderResource::class, 'orders', $request);
    }

    public function show(Order $order): JsonResponse
    {
        abort_if($order->status === 'voided' || ! $order->user()->activeAccount()->exists(), 404);

        return response()->json([
            'order' => OrderResource::make($order->load(['user.profile', 'items.product', 'items.package'])),
        ]);
    }

    public function status(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(self::ORDER_STATUSES)],
        ]);

        DB::transaction(function () use ($order, $validated): void {
            $order->loadMissing('items.product');
            $currentStatus = $order->status;
            $nextStatus = $validated['status'];

            if ($currentStatus === 'cancelled' && $nextStatus !== 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'Отменённый заказ нельзя вернуть в работу',
                ]);
            }

            if ($currentStatus !== 'cancelled' && $nextStatus === 'cancelled') {
                foreach ($order->items as $item) {
                    if ($item->product_id && $item->product) {
                        $item->product->increment('stock_quantity', $item->quantity);
                    }
                }
            }

            $order->update([
                'status' => $nextStatus,
            ]);
        });

        return response()->json([
            'order' => OrderResource::make($order->refresh()->load(['user.profile', 'items.product', 'items.package'])),
        ]);
    }

    public function paymentStatus(
        Request $request,
        Order $order,
        PackageAutoUpgradeFromPaidOrdersService $packageAutoUpgradeFromPaidOrders,
    ): JsonResponse {
        $validated = $request->validate([
            'payment_status' => ['required', 'string', Rule::in(self::PAYMENT_STATUSES)],
        ]);

        DB::transaction(function () use ($order, $validated, $packageAutoUpgradeFromPaidOrders): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasPaid = $lockedOrder->payment_status === 'paid';
            $nextPaymentStatus = $validated['payment_status'];
            $updates = [
                'payment_status' => $nextPaymentStatus,
            ];

            if ($nextPaymentStatus === 'paid') {
                $updates['status'] = $lockedOrder->status === 'pending' ? 'confirmed' : $lockedOrder->status;
                $updates['paid_at'] = $lockedOrder->paid_at ?: now();
            }

            $lockedOrder->forceFill($updates)->save();

            if (! $wasPaid && $nextPaymentStatus === 'paid') {
                $packageAutoUpgradeFromPaidOrders->handlePaidOrder($lockedOrder->refresh());
            }
        });

        return response()->json([
            'order' => OrderResource::make($order->refresh()->load(['user.profile', 'items.product', 'items.package'])),
        ]);
    }
}

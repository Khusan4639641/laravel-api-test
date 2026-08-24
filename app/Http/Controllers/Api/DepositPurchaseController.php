<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BonusTransactionResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\WalletTransactionResource;
use App\Models\Product;
use App\Services\DepositPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositPurchaseController extends Controller
{
    public function __construct(
        private readonly DepositPurchaseService $depositPurchaseService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required_without:items', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'gt:0'],
            'items' => ['required_without:product_id', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'gt:0'],
            'shipping_address' => ['nullable', 'array'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'min:6', 'max:32'],
            'city' => ['nullable', 'string', 'max:120'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = isset($validated['items'])
            ? $this->depositPurchaseService->purchaseProducts($request->user(), $validated['items'], $validated)
            : $this->depositPurchaseService->purchaseProduct(
                $request->user(),
                Product::query()->findOrFail($validated['product_id']),
                (int) ($validated['quantity'] ?? 1)
            );

        return $this->purchaseResponse($result);
    }

    public function purchaseProduct(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'gt:0'],
        ]);

        $result = $this->depositPurchaseService->purchaseProduct(
            $request->user(),
            $product,
            (int) ($validated['quantity'] ?? 1)
        );

        return $this->purchaseResponse($result);
    }

    /**
     * @param  array{deposit_transaction: mixed, cashback_bonus: mixed, order: mixed}  $result
     */
    private function purchaseResponse(array $result): JsonResponse
    {
        $order = $result['order'];
        $depositTransaction = $result['deposit_transaction'];
        $cashbackBonus = $result['cashback_bonus']?->load('walletTransaction');

        return response()->json([
            'message' => __('api.deposit_purchase_completed'),
            'data' => [
                'order_id' => $order?->id,
                'total' => $order?->total_amount,
                'deposit_debited' => $depositTransaction->amount,
                'cashback' => $cashbackBonus?->amount,
                'main_balance' => $cashbackBonus?->walletTransaction?->balance_after,
                'deposit_balance' => $depositTransaction->balance_after,
            ],
            'order' => $order ? OrderResource::make($order) : null,
            'deposit_transaction' => WalletTransactionResource::make($depositTransaction),
            'cashback_bonus' => $cashbackBonus
                ? BonusTransactionResource::make($cashbackBonus)
                : null,
        ], 201);
    }
}

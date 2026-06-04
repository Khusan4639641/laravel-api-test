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
            'amount' => ['required_without:product_id', 'numeric', 'gt:0'],
            'product_id' => ['required_without:amount', 'integer', 'exists:products,id'],
            'quantity' => ['required_with:product_id', 'integer', 'gt:0'],
        ]);

        $result = isset($validated['product_id'])
            ? $this->depositPurchaseService->purchaseProduct(
                $request->user(),
                Product::query()->findOrFail($validated['product_id']),
                (int) ($validated['quantity'] ?? 1)
            )
            : $this->depositPurchaseService->purchase(
                $request->user(),
                $validated['amount']
            );

        return response()->json([
            'order' => $result['order'] ? OrderResource::make($result['order']) : null,
            'deposit_transaction' => WalletTransactionResource::make($result['deposit_transaction']),
            'cashback_bonus' => $result['cashback_bonus']
                ? BonusTransactionResource::make($result['cashback_bonus']->load('walletTransaction'))
                : null,
        ], 201);
    }
}

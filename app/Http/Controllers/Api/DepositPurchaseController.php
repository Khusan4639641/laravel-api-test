<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BonusTransactionResource;
use App\Http\Resources\WalletTransactionResource;
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
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $result = $this->depositPurchaseService->purchase(
            $request->user(),
            $validated['amount']
        );

        return response()->json([
            'deposit_transaction' => WalletTransactionResource::make($result['deposit_transaction']),
            'cashback_bonus' => $result['cashback_bonus']
                ? BonusTransactionResource::make($result['cashback_bonus']->load('walletTransaction'))
                : null,
        ], 201);
    }
}

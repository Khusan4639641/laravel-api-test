<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BonusTransactionResource;
use App\Services\BonusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BinaryBonusController extends Controller
{
    public function __construct(
        private readonly BonusService $bonusService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $bonusTransaction = $this->bonusService->calculateBinaryBonus($request->user());

        if (! $bonusTransaction) {
            return response()->json([
                'message' => 'No binary bonus available.',
                'bonus_transaction' => null,
            ]);
        }

        return response()->json([
            'bonus_transaction' => BonusTransactionResource::make($bonusTransaction->load('walletTransaction')),
        ]);
    }
}

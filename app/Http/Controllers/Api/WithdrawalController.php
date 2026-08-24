<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Withdrawal\StoreWithdrawalRequest;
use App\Http\Resources\WithdrawalRequestResource;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawalService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $withdrawals = $request->user()
            ->withdrawalRequests()
            ->latest()
            ->get();

        return response()->json([
            'withdrawals' => WithdrawalRequestResource::collection($withdrawals),
        ]);
    }

    public function store(StoreWithdrawalRequest $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();

        $withdrawal = $this->withdrawalService->requestWithdrawal(
            $user,
            $wallet,
            $request->validated('amount'),
            $request->only(['payment_method', 'payment_details'])
        );

        return response()->json([
            'withdrawal' => WithdrawalRequestResource::make($withdrawal),
        ], 201);
    }
}

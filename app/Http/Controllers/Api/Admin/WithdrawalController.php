<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawalService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'withdrawals' => WithdrawalRequest::query()
                ->with(['user', 'wallet'])
                ->latest()
                ->get(),
        ]);
    }

    public function approve(WithdrawalRequest $withdrawal): JsonResponse
    {
        $this->withdrawalService->approveWithdrawal($withdrawal);

        return response()->json([
            'withdrawal' => $withdrawal->refresh()->load(['user', 'wallet']),
        ]);
    }

    public function reject(Request $request, WithdrawalRequest $withdrawal): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->withdrawalService->rejectWithdrawal($withdrawal, $validated['reason'] ?? null);

        return response()->json([
            'withdrawal' => $withdrawal->refresh()->load(['user', 'wallet']),
        ]);
    }
}

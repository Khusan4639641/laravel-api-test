<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\WithdrawalRequestResource;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    use RespondsWithPagination;

    public function __construct(
        private readonly WithdrawalService $withdrawalService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $withdrawals = WithdrawalRequest::query()
            ->with(['user.profile', 'wallet'])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($withdrawals, WithdrawalRequestResource::class, 'withdrawals', $request);
    }

    public function approve(WithdrawalRequest $withdrawal): JsonResponse
    {
        $this->withdrawalService->approveWithdrawal($withdrawal);

        return response()->json([
            'withdrawal' => WithdrawalRequestResource::make($withdrawal->refresh()->load(['user', 'wallet'])),
        ]);
    }

    public function reject(Request $request, WithdrawalRequest $withdrawal): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->withdrawalService->rejectWithdrawal($withdrawal, $validated['reason'] ?? null);

        return response()->json([
            'withdrawal' => WithdrawalRequestResource::make($withdrawal->refresh()->load(['user', 'wallet'])),
        ]);
    }
}

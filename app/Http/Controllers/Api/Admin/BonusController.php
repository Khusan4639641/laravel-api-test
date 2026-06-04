<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\BonusTransactionResource;
use App\Models\BonusTransaction;
use App\Models\User;
use App\Services\BonusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonusController extends Controller
{
    use RespondsWithPagination;

    public function __construct(
        private readonly BonusService $bonusService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $bonuses = BonusTransaction::query()
            ->with(['user.profile', 'sourceUser.profile', 'sourceOrder', 'walletTransaction'])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($bonuses, BonusTransactionResource::class, 'bonuses', $request);
    }

    public function calculateBinary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $bonusTransaction = $this->bonusService->calculateBinaryBonus($user);

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

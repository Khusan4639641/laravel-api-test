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
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (empty($validated['user_id'])) {
            $calculated = collect();

            User::query()
                ->where('role', User::ROLE_USER)
                ->whereHas('currentPackage')
                ->where(function ($query): void {
                    $query->where('remaining_left_pv', '>', 0)
                        ->where('remaining_right_pv', '>', 0);
                })
                ->orderBy('id')
                ->each(function (User $user) use ($calculated): void {
                    $bonusTransaction = $this->bonusService->calculateBinaryBonus($user);

                    if ($bonusTransaction) {
                        $calculated->push($bonusTransaction->load('walletTransaction'));
                    }
                });

            return response()->json([
                'message' => $calculated->isEmpty()
                    ? 'No binary bonus available.'
                    : 'Binary bonuses calculated.',
                'calculated_count' => $calculated->count(),
                'bonus_transactions' => BonusTransactionResource::collection($calculated),
            ]);
        }

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

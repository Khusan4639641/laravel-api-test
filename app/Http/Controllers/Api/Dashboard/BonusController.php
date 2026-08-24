<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\BonusTransactionResource;
use App\Services\EarningsSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonusController extends Controller
{
    use RespondsWithPagination;

    public function __construct(
        private readonly EarningsSummaryService $earningsSummaryService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $bonuses = $user->bonusTransactions()
            ->with(['sourceUser', 'sourceOrder', 'walletTransaction'])
            ->whereNotIn('status', ['reversed', 'voided', 'cancelled'])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($bonuses, BonusTransactionResource::class, 'bonuses', $request, [
            'summary' => $this->earningsSummaryService->forUser($user),
        ]);
    }
}

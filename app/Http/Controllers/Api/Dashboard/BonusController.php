<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\BonusTransactionResource;
use App\Models\BonusTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BonusController extends Controller
{
    use RespondsWithPagination;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $bonuses = $user->bonusTransactions()
            ->with(['sourceUser', 'sourceOrder', 'walletTransaction'])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($bonuses, BonusTransactionResource::class, 'bonuses', $request, [
            'summary' => [
                'total' => (string) BonusTransaction::query()->where('user_id', $user->id)->sum('amount'),
                'by_type' => BonusTransaction::query()
                    ->select('bonus_type', DB::raw('sum(amount) as total'))
                    ->where('user_id', $user->id)
                    ->groupBy('bonus_type')
                    ->pluck('total', 'bonus_type'),
            ],
        ]);
    }
}

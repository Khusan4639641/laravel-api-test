<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\BonusTransactionResource;
use App\Models\BonusTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonusController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $bonuses = BonusTransaction::query()
            ->with(['user.profile', 'sourceUser.profile', 'sourceOrder', 'walletTransaction'])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($bonuses, BonusTransactionResource::class, 'bonuses', $request);
    }
}

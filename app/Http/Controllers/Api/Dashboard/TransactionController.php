<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use RespondsWithPagination;

    public function __invoke(Request $request): JsonResponse
    {
        $transactions = $request->user()
            ->walletTransactions()
            ->with('wallet')
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($transactions, WalletTransactionResource::class, 'transactions', $request);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $transactions = WalletTransaction::query()
            ->with(['user.profile', 'wallet'])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($transactions, WalletTransactionResource::class, 'transactions', $request);
    }
}

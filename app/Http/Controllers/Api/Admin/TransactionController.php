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
        $search = trim((string) $request->query('search', ''));

        $transactions = WalletTransaction::query()
            ->with(['user.profile', 'wallet'])
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', (int) $request->integer('user_id')))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    if (ctype_digit($search)) {
                        $numericSearch = (int) $search;

                        $query->where('id', $numericSearch)
                            ->orWhere('user_id', $numericSearch);
                    }

                    $query->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('login', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                });
            })
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($transactions, WalletTransactionResource::class, 'transactions', $request);
    }
}

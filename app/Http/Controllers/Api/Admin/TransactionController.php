<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use App\Models\BinaryBonusRun;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $baseQuery = $this->transactionsQuery($request, $search);
        $summary = $this->summary($request, $baseQuery);

        $transactions = (clone $baseQuery)
            ->with(['user.profile', 'wallet'])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($transactions, WalletTransactionResource::class, 'transactions', $request, [
            'summary' => $summary,
        ]);
    }

    private function transactionsQuery(Request $request, string $search)
    {
        return WalletTransaction::query()
            ->whereNotIn('status', ['reversed', 'voided', 'cancelled'])
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', (int) $request->integer('user_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', trim((string) $request->query('type'))))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', (string) $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', (string) $request->query('date_to')))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    if (ctype_digit($search)) {
                        $numericSearch = (int) $search;
                        $hasUserTransactions = WalletTransaction::query()
                            ->where('user_id', $numericSearch)
                            ->exists();

                        if ($hasUserTransactions) {
                            $query->where('user_id', $numericSearch);
                        } else {
                            $query->where('id', $numericSearch);
                        }

                        return;
                    }

                    $query->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('login', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                });
            });
    }

    /**
     * @return array<string, string>
     */
    private function summary(Request $request, $baseQuery): array
    {
        $userId = $request->filled('user_id') ? (int) $request->integer('user_id') : null;

        $operationTurnover = (string) (clone $baseQuery)
            ->where('status', 'completed')
            ->sum('amount');
        $totalCredited = (string) (clone $baseQuery)
            ->where('status', 'completed')
            ->where('affects_balance', true)
            ->where('direction', 'credit')
            ->whereIn('type', $this->incomeTypes())
            ->sum('amount');
        $totalPaid = (string) (clone $baseQuery)
            ->where('status', 'completed')
            ->whereIn('type', ['withdrawal_approved', 'payout_completed'])
            ->sum('amount');
        $deferredDeposit = (string) (clone $baseQuery)
            ->where('status', 'completed')
            ->where(function ($query): void {
                $query->whereIn('type', ['binary_bonus_deposit'])
                    ->orWhereHas('wallet', fn ($walletQuery) => $walletQuery->where('type', 'deposit'));
            })
            ->sum('amount');

        $pendingWithdrawals = WithdrawalRequest::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->where('status', 'pending')
            ->sum('amount');
        $pendingBinary = BinaryBonusRun::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->where('status', 'pending')
            ->sum('pending_amount');

        return [
            'operation_turnover' => $this->decimal($operationTurnover),
            'total_credited' => $this->decimal($totalCredited),
            'total_paid' => $this->decimal($totalPaid),
            'pending' => $this->decimal(bcadd((string) $pendingWithdrawals, (string) $pendingBinary, 2)),
            'deferred_deposit' => $this->decimal($deferredDeposit),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function incomeTypes(): array
    {
        return [
            'referral_bonus',
            'binary_bonus_main',
            'status_bonus',
            'x2_bonus',
            'bonus_x2',
            'cashback',
            'deposit_purchase_cashback',
            'manual_credit',
            'manual_adjustment',
        ];
    }

    protected function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 20), 1), 100);
    }

    private function decimal(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value === '' ? '0' : $value;
    }
}

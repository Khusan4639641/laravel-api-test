<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use RespondsWithPagination;

    public function __invoke(Request $request): JsonResponse
    {
        $filter = $this->filter($request);

        $transactions = $request->user()
            ->walletTransactions()
            ->with('wallet')
            ->where('user_id', $request->user()->id)
            ->visible()
            ->when($filter === 'credits', fn ($query) => $query
                ->where('affects_balance', true)
                ->where('direction', 'credit'))
            ->when($filter === 'withdrawals', fn ($query) => $query
                ->where(function ($typeQuery): void {
                    $typeQuery
                        ->where('type', 'like', '%withdrawal%')
                        ->orWhere('type', 'like', '%payout%');
                }))
            ->when($filter === 'cashback', fn ($query) => $query
                ->where('type', 'like', '%cashback%'))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($transactions, WalletTransactionResource::class, 'transactions', $request, [
            'summary' => $this->summary($request),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function summary(Request $request): array
    {
        $user = $request->user();
        $available = (string) Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', 'main')
            ->sum('balance');
        $totalEarned = (string) WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('affects_balance', true)
            ->where('direction', 'credit')
            ->whereIn('type', [
                'referral_bonus',
                'binary_bonus_main',
                'status_bonus',
                'x2_bonus',
                'bonus_x2',
                'cashback',
                'deposit_purchase_cashback',
                'manual_credit',
                'manual_adjustment',
            ])
            ->sum('amount');
        $pending = (string) WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->sum('amount');
        $withdrawn = (string) WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['approved', 'paid', 'completed'])
            ->sum('amount');

        return [
            'total_earned' => $this->decimal($totalEarned),
            'available' => $this->decimal($available),
            'pending' => $this->decimal($pending),
            'withdrawn' => $this->decimal($withdrawn),
        ];
    }

    private function decimal(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value === '' ? '0' : $value;
    }

    private function filter(Request $request): string
    {
        return match ((string) $request->query('filter', 'all')) {
            'credits', 'withdrawals', 'cashback' => (string) $request->query('filter'),
            default => 'all',
        };
    }
}

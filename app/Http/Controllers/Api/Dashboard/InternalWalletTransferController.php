<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use App\Models\User;
use App\Services\InternalWalletTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalWalletTransferController extends Controller
{
    public function __invoke(Request $request, InternalWalletTransferService $internalWalletTransferService): JsonResponse
    {
        if (! in_array($request->user()?->role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            abort(403);
        }

        $validated = $request->validate([
            'from' => ['required', 'string', 'max:40'],
            'to' => ['required', 'string', 'max:40', 'different:from'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $result = $internalWalletTransferService->transfer(
            user: $request->user(),
            from: $validated['from'],
            to: $validated['to'],
            amount: $validated['amount'],
            comment: $validated['comment'] ?? null,
        );

        return response()->json([
            'message' => 'Перевод между счетами выполнен',
            'data' => [
                'transfer_uuid' => $result['transfer_uuid'],
                'from' => 'main',
                'to' => 'deposit',
                'amount' => $result['debit_transaction']->amount,
                'main_balance' => $result['main_wallet']->balance,
                'deposit_balance' => $result['deposit_wallet']->balance,
                'debit_transaction_id' => $result['debit_transaction']->id,
                'credit_transaction_id' => $result['credit_transaction']->id,
            ],
            'debit_transaction' => WalletTransactionResource::make($result['debit_transaction']),
            'credit_transaction' => WalletTransactionResource::make($result['credit_transaction']),
        ], 201);
    }
}

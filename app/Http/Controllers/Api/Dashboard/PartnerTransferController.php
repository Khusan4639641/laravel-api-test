<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerTransferResource;
use App\Models\PartnerTransfer;
use App\Models\Wallet;
use App\Services\PartnerTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PartnerTransferController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $transfers = PartnerTransfer::query()
            ->with(['sender.profile', 'sender.currentPackage', 'recipient.profile', 'recipient.currentPackage'])
            ->where(function ($query) use ($request): void {
                $query->where('sender_user_id', $request->user()->id)
                    ->orWhere('recipient_user_id', $request->user()->id);
            })
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($transfers, PartnerTransferResource::class, 'transfers', $request);
    }

    public function store(Request $request, PartnerTransferService $partnerTransferService): JsonResponse
    {
        $requestedSourceWallet = $request->input('from', $request->input('wallet', $request->input('from_wallet')));
        $requestedSourceWallet = is_string($requestedSourceWallet)
            ? mb_strtolower(trim($requestedSourceWallet))
            : $requestedSourceWallet;

        if ($requestedSourceWallet !== null && $requestedSourceWallet !== '' && $requestedSourceWallet !== 'main') {
            throw ValidationException::withMessages([
                'from' => $requestedSourceWallet === 'deposit'
                    ? 'Депозитный баланс нельзя переводить партнёрам'
                    : 'Перевод партнёру доступен только с основного баланса',
            ]);
        }

        $validated = $request->validate([
            'recipient_user_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $transfer = $partnerTransferService->transfer(
            sender: $request->user(),
            recipientUserId: (int) $validated['recipient_user_id'],
            amount: $validated['amount'],
            comment: $validated['comment'] ?? null,
            idempotencyKey: $validated['idempotency_key'] ?? null,
        );

        return response()->json([
            'message' => 'Перевод выполнен',
            'data' => [
                'transfer_id' => $transfer->id,
                'uuid' => $transfer->uuid,
                'sender_id' => $transfer->sender_user_id,
                'recipient_id' => $transfer->recipient_user_id,
                'amount' => (float) $transfer->amount,
                'currency' => $transfer->currency,
                'status' => $transfer->status,
                'sender_available_balance' => $this->mainBalance($transfer->sender_user_id),
                'recipient_available_balance' => $this->mainBalance($transfer->recipient_user_id),
            ],
            'transfer' => PartnerTransferResource::make($transfer),
        ], 201);
    }

    private function mainBalance(int $userId): float
    {
        return (float) Wallet::query()
            ->where('user_id', $userId)
            ->where('type', 'main')
            ->sum('balance');
    }
}

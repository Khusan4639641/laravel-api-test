<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;

class WithdrawalService
{
    public function requestWithdrawal(User $user, Wallet $wallet, float|string $amount, array $paymentDetails = []): WithdrawalRequest
    {
        // TODO: Validate wallet ownership, minimum amount, fees, and available balance.
        // TODO: Put funds on hold and create withdrawal request.
    }

    public function approveWithdrawal(WithdrawalRequest $withdrawalRequest): void
    {
        // TODO: Mark request approved and convert held funds into a final debit transaction.
    }

    public function rejectWithdrawal(WithdrawalRequest $withdrawalRequest, ?string $reason = null): void
    {
        // TODO: Release held funds and mark request rejected with admin comment.
    }
}

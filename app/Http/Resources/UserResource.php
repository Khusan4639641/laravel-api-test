<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    private const PV_MONEY_RATE = 500;

    public function toArray(Request $request): array
    {
        $wallets = $this->resource->relationLoaded('wallets') ? $this->wallets : collect();
        $mainBalance = (float) $wallets->where('type', 'main')->sum('balance');
        $bonusBalance = (float) $wallets->where('type', 'bonus')->sum('balance');
        $depositBalance = (float) $wallets->where('type', 'deposit')->sum('balance');
        $totalWalletBalance = $mainBalance + $bonusBalance + $depositBalance;
        $package = $this->resource->relationLoaded('currentPackage') ? $this->currentPackage : null;
        $packageActivityPv = $package ? (float) $package->activityPv() : 0;
        $packageActivityAmount = $packageActivityPv * self::PV_MONEY_RATE;
        $attributes = $this->resource->getAttributes();
        $invitedCount = (int) ($attributes['invited_count']
            ?? $attributes['invited_users_count']
            ?? $attributes['referrals_count']
            ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'login' => $this->login,
            'referral_code' => $this->login ?: (string) $this->id,
            'email' => $this->email,
            'phone' => $this->resource->relationLoaded('profile') ? $this->profile?->phone : null,
            'city' => $this->resource->relationLoaded('profile') ? $this->profile?->city : null,
            'role' => $this->role,
            'sponsor_id' => $this->sponsor_id,
            'current_package_id' => $this->current_package_id,
            'status' => $this->status,
            'account_status' => $this->account_status,
            'admin_note' => $this->admin_note,
            'left_pv' => $this->left_pv,
            'right_pv' => $this->right_pv,
            'remaining_left_pv' => $this->remaining_left_pv,
            'remaining_right_pv' => $this->remaining_right_pv,
            'total_pv' => $this->total_pv,
            'invited_count' => $invitedCount,
            'balance' => $mainBalance,
            'wallet_balance' => $mainBalance,
            'available_balance' => $mainBalance,
            'withdrawable_balance' => $mainBalance,
            'bonus_balance' => $bonusBalance,
            'deposit_balance' => $depositBalance,
            'total_wallet_balance' => $totalWalletBalance,
            'total_balance' => $totalWalletBalance,
            'total_earned' => $totalWalletBalance,
            'package_activity_pv' => $packageActivityPv,
            'package_activity_amount' => $packageActivityAmount,
            'pv_amount' => $packageActivityAmount,
            'pv_money_rate' => self::PV_MONEY_RATE,
            'current_package' => new PackageResource($this->whenLoaded('currentPackage')),
            'package' => new PackageResource($this->whenLoaded('currentPackage')),
            'profile' => new UserProfileResource($this->whenLoaded('profile')),
            'sponsor' => new UserResource($this->whenLoaded('sponsor')),
            'wallets' => WalletResource::collection($this->whenLoaded('wallets')),
            'binary_node' => new BinaryNodeResource($this->whenLoaded('binaryNode')),
            'referrals_count' => $this->whenCounted('referrals'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

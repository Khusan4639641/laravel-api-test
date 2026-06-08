<?php

namespace App\Http\Resources;

use App\Support\SystemLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $wallets = $this->resource->relationLoaded('wallets') ? $this->wallets : collect();
        $mainBalance = (float) $wallets->where('type', 'main')->sum('balance');
        $bonusBalance = (float) $wallets->where('type', 'bonus')->sum('balance');
        $depositBalance = (float) $wallets->where('type', 'deposit')->sum('balance');
        $totalWalletBalance = $mainBalance + $bonusBalance + $depositBalance;
        $totalEarned = (float) $this->resource->walletTransactions()
            ->where('direction', 'credit')
            ->where('affects_balance', true)
            ->sum('amount');
        $totalEarned = $totalEarned > 0 ? $totalEarned : $totalWalletBalance;
        $package = $this->resource->relationLoaded('currentPackage') ? $this->currentPackage : null;
        $packageActivityPv = $package ? (float) $package->activityPv() : 0;
        $packageActivityAmount = $package ? (float) $package->volumeAmount() : 0;
        $leftPv = (float) ($this->left_pv ?? 0);
        $rightPv = (float) ($this->right_pv ?? 0);
        $weakLegPv = min($leftPv, $rightPv);
        $attributes = $this->resource->getAttributes();
        $profileAvatarPath = $this->resource->relationLoaded('profile') ? $this->profile?->avatar_path : null;
        $avatarPath = $this->avatar_path ?: $profileAvatarPath;
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
            'status_label' => SystemLabel::mlmStatus($this->status),
            'account_status' => $this->account_status,
            'account_status_label' => SystemLabel::accountStatus($this->account_status),
            'admin_note' => $this->admin_note,
            'avatar_path' => $avatarPath,
            'avatar_url' => $this->avatarUrl($avatarPath),
            'left_pv' => $this->left_pv,
            'right_pv' => $this->right_pv,
            'weak_leg_pv' => $weakLegPv,
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
            'total_earned' => $totalEarned,
            'package_activity_pv' => $packageActivityPv,
            'package_activity_amount' => $packageActivityAmount,
            'pv_amount' => $packageActivityAmount,
            'pv_money_rate' => 500,
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

    private function avatarUrl(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return asset(Storage::url($path));
    }
}

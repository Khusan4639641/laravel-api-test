<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'login' => $this->login,
            'email' => $this->email,
            'role' => $this->role,
            'sponsor_id' => $this->sponsor_id,
            'current_package_id' => $this->current_package_id,
            'status' => $this->status,
            'left_pv' => $this->left_pv,
            'right_pv' => $this->right_pv,
            'remaining_left_pv' => $this->remaining_left_pv,
            'remaining_right_pv' => $this->remaining_right_pv,
            'total_pv' => $this->total_pv,
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

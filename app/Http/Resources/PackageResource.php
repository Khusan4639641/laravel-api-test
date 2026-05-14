<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'pv' => $this->pv,
            'referral_percent' => $this->referral_percent,
            'referralBonus' => (float) $this->referral_percent,
            'binary_percent' => $this->binary_percent,
            'binaryBonus' => (float) $this->binary_percent,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'is_upgradeable' => $this->is_upgradeable,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

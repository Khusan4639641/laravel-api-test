<?php

namespace App\Http\Resources;

use App\Support\LocalizedValue;
use App\Support\SystemLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fallbackName = LocalizedValue::get($this->name_translations, $this->name);
        $name = SystemLabel::package($this->code, $fallbackName);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'code_label' => SystemLabel::package($this->code, $fallbackName),
            'name' => $fallbackName,
            'label' => $name,
            'slug' => $this->slug,
            'description' => LocalizedValue::get($this->description_translations, $this->description),
            'price' => $this->price,
            'pv' => $this->pv,
            'activity_pv' => $this->activityPv(),
            'activityPv' => (float) $this->activityPv(),
            'turnover_pv' => $this->turnoverPv(),
            'turnoverPv' => (float) $this->turnoverPv(),
            'volume_amount' => $this->volumeAmount(),
            'volumeAmount' => (float) $this->volumeAmount(),
            'pv_amount' => $this->volumeAmount(),
            'pvAmount' => (float) $this->volumeAmount(),
            'pv_money_rate' => 500,
            'pvMoneyRate' => 500,
            'referral_percent' => $this->referral_percent,
            'referralBonus' => (float) $this->referral_percent,
            'binary_percent' => $this->binary_percent,
            'binaryBonus' => (float) $this->binary_percent,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'status_label' => SystemLabel::productStatus($this->status),
            'is_active' => $this->is_active,
            'is_upgradeable' => $this->is_upgradeable,
            'translations' => [
                'name' => $this->name_translations,
                'description' => $this->description_translations,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

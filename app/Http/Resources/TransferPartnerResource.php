<?php

namespace App\Http\Resources;

use App\Support\SystemLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferPartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $package = $this->resource->relationLoaded('currentPackage') ? $this->currentPackage : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'login' => $this->login,
            'email' => $this->email,
            'phone' => $this->resource->relationLoaded('profile') ? $this->profile?->phone : null,
            'package' => $package?->code,
            'package_label' => $package ? SystemLabel::package($package->code, $package->name) : null,
            'status' => $this->account_status,
        ];
    }
}

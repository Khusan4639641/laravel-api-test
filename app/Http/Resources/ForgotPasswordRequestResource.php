<?php

namespace App\Http\Resources;

use App\Models\ForgotPasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForgotPasswordRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'status_label' => $this->statusLabel((string) $this->status),
            'requested_at' => $this->requested_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'resolved_by' => $this->resolved_by,
            'ip_address' => $this->ip_address,
            'admin_note' => $this->admin_note,
            'user' => new UserResource($this->whenLoaded('user')),
            'resolved_by_user' => new UserResource($this->whenLoaded('resolvedBy')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            ForgotPasswordRequest::STATUS_RESOLVED => 'Решено',
            ForgotPasswordRequest::STATUS_CANCELLED => 'Отменено',
            default => 'Ожидает',
        };
    }
}

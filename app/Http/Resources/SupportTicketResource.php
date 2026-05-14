<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'subject' => $this->subject,
            'category' => $this->category,
            'message' => $this->message,
            'status' => $this->status,
            'priority' => $this->priority,
            'admin_reply' => $this->admin_reply,
            'replied_at' => $this->replied_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'last_reply_at' => $this->last_reply_at?->toISOString(),
            'metadata' => $this->metadata,
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

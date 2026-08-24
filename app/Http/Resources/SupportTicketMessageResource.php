<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'user_id' => $this->user_id,
            'sender_role' => $this->sender_role ?: ((bool) $this->is_staff ? 'admin' : 'partner'),
            'message' => $this->message,
            'is_staff' => (bool) $this->is_staff,
            'user' => new UserResource($this->whenLoaded('user')),
            'attachments' => SupportMessageAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

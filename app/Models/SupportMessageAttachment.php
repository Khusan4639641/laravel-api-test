<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'message_id',
    'original_name',
    'path',
    'mime_type',
    'size',
    'disk',
])]
class SupportMessageAttachment extends Model
{
    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessage::class, 'message_id');
    }
}

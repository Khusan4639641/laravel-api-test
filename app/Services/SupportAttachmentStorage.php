<?php

namespace App\Services;

use App\Models\SupportMessageAttachment;
use App\Models\SupportTicketMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportAttachmentStorage
{
    public function store(SupportTicketMessage $message, UploadedFile $file): SupportMessageAttachment
    {
        $disk = 'local';
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeBaseName = Str::slug($baseName) ?: 'attachment';
        $filename = $safeBaseName.'-'.Str::uuid().($extension ? ".{$extension}" : '');
        $directory = "support/{$message->ticket_id}/{$message->id}";
        $path = $file->storeAs($directory, $filename, $disk);

        return $message->attachments()->create([
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'disk' => $disk,
        ]);
    }

    public function download(SupportMessageAttachment $attachment): StreamedResponse
    {
        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            $attachment->mime_type ? ['Content-Type' => $attachment->mime_type] : [],
        );
    }
}

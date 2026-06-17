<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function __construct(
        private readonly SupportAttachmentStorage $attachmentStorage,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTicket(User $user, array $data, ?UploadedFile $file = null): SupportTicket
    {
        $messageText = trim((string) Arr::get($data, 'message', ''));
        $subject = trim((string) Arr::get($data, 'subject', ''));

        if ($subject === '') {
            $subject = $messageText !== ''
                ? Str::limit($messageText, 80, '')
                : ($file ? $file->getClientOriginalName() : 'Обращение в поддержку');
        }

        return DB::transaction(function () use ($user, $data, $file, $messageText, $subject): SupportTicket {
            $now = now();
            $ticket = $user->supportTickets()->create([
                'subject' => $subject,
                'category' => Arr::get($data, 'category'),
                'message' => $messageText,
                'status' => SupportTicket::STATUS_WAITING_ADMIN,
                'priority' => Arr::get($data, 'priority'),
                'last_reply_at' => $now,
                'last_message_at' => $now,
            ]);

            $this->createMessage($ticket, $user, $messageText, false, $file);

            return $this->loadTicket($ticket->refresh());
        });
    }

    public function sendPartnerMessage(SupportTicket $ticket, User $user, ?string $message, ?UploadedFile $file = null): SupportTicket
    {
        $this->ensureOpen($ticket);

        return DB::transaction(function () use ($ticket, $user, $message, $file): SupportTicket {
            $text = trim((string) $message);
            $this->createMessage($ticket, $user, $text, false, $file);
            $ticket->forceFill([
                'status' => SupportTicket::STATUS_WAITING_ADMIN,
                'last_reply_at' => now(),
                'last_message_at' => now(),
            ])->save();

            return $this->loadTicket($ticket->refresh());
        });
    }

    public function sendAdminMessage(SupportTicket $ticket, User $admin, ?string $message, ?UploadedFile $file = null): SupportTicket
    {
        $this->ensureOpen($ticket);

        return DB::transaction(function () use ($ticket, $admin, $message, $file): SupportTicket {
            $text = trim((string) $message);
            $this->createMessage($ticket, $admin, $text, true, $file);
            $ticket->forceFill([
                'assigned_to' => $ticket->assigned_to ?? $admin->id,
                'admin_reply' => $text !== '' ? $text : $ticket->admin_reply,
                'status' => SupportTicket::STATUS_WAITING_USER,
                'replied_at' => now(),
                'last_reply_at' => now(),
                'last_message_at' => now(),
                'closed_at' => null,
                'closed_by' => null,
            ])->save();

            return $this->loadTicket($ticket->refresh());
        });
    }

    public function closeTicket(SupportTicket $ticket, User $actor): SupportTicket
    {
        $ticket->forceFill([
            'status' => SupportTicket::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => $actor->id,
            'last_reply_at' => now(),
            'last_message_at' => now(),
        ])->save();

        return $this->loadTicket($ticket->refresh());
    }

    public function reopenTicket(SupportTicket $ticket): SupportTicket
    {
        $ticket->forceFill([
            'status' => SupportTicket::STATUS_WAITING_ADMIN,
            'closed_at' => null,
            'closed_by' => null,
            'last_reply_at' => now(),
            'last_message_at' => now(),
        ])->save();

        return $this->loadTicket($ticket->refresh());
    }

    public function loadTicket(SupportTicket $ticket): SupportTicket
    {
        return $ticket->load([
            'user.profile',
            'assignedTo',
            'closedBy',
            'messages.user.profile',
            'messages.attachments',
        ]);
    }

    private function createMessage(
        SupportTicket $ticket,
        User $sender,
        string $message,
        bool $isStaff,
        ?UploadedFile $file = null,
    ): SupportTicketMessage {
        $ticketMessage = $ticket->messages()->create([
            'user_id' => $sender->id,
            'sender_role' => $isStaff ? 'admin' : 'partner',
            'message' => $message,
            'is_staff' => $isStaff,
        ]);

        if ($file) {
            $this->attachmentStorage->store($ticketMessage, $file);
        }

        return $ticketMessage;
    }

    private function ensureOpen(SupportTicket $ticket): void
    {
        if ($ticket->isClosed()) {
            throw ValidationException::withMessages([
                'message' => ['Обращение закрыто. Создайте новое обращение.'],
            ]);
        }
    }
}

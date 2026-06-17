<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportTicket\AssignSupportTicketRequest;
use App\Http\Requests\SupportTicket\ReplySupportTicketRequest;
use App\Http\Requests\SupportTicket\UpdateSupportTicketStatusRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportMessageAttachment;
use App\Models\SupportTicket;
use App\Services\SupportAttachmentStorage;
use App\Services\SupportTicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    use RespondsWithPagination;

    public function __construct(
        private readonly SupportTicketService $supportTicketService,
        private readonly SupportAttachmentStorage $attachmentStorage,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->with(['user.profile', 'assignedTo', 'closedBy', 'messages.user.profile', 'messages.attachments'])
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->query('q'));
                $like = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $query) use ($search, $like): void {
                    $query
                        ->whereRaw('LOWER(subject) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(message) LIKE ?', [$like]);

                    if (ctype_digit($search)) {
                        $query->orWhere('id', (int) $search)
                            ->orWhere('user_id', (int) $search);
                    }

                    $query->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                        $userQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(login) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                    });
                });
            })
            ->orderByDesc('last_message_at')
            ->latest('updated_at')
            ->paginate($this->perPage($request));

        return $this->paginated($tickets, SupportTicketResource::class, 'support_tickets', $request);
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        return response()->json([
            'support_ticket' => SupportTicketResource::make($this->supportTicketService->loadTicket($ticket)),
        ]);
    }

    public function reply(ReplySupportTicketRequest $request, SupportTicket $ticket): JsonResponse
    {
        $ticket = $this->supportTicketService->sendAdminMessage(
            $ticket,
            $request->user(),
            $request->validated('message'),
            $request->file('file'),
        );

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket),
        ]);
    }

    public function status(UpdateSupportTicketStatusRequest $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validated();

        $ticket->forceFill([
            'status' => $validated['status'],
            'closed_at' => $validated['status'] === SupportTicket::STATUS_CLOSED ? now() : null,
            'closed_by' => $validated['status'] === SupportTicket::STATUS_CLOSED ? $request->user()?->id : null,
            'last_reply_at' => now(),
            'last_message_at' => now(),
        ])->save();

        return response()->json([
            'support_ticket' => SupportTicketResource::make($this->supportTicketService->loadTicket($ticket->refresh())),
        ]);
    }

    public function assign(AssignSupportTicketRequest $request, SupportTicket $ticket): JsonResponse
    {
        $assignedTo = $request->has('assigned_to') ? $request->validated('assigned_to') : $request->user()->id;

        $ticket->forceFill([
            'assigned_to' => $assignedTo,
            'status' => $ticket->status === SupportTicket::STATUS_OPEN ? SupportTicket::STATUS_IN_PROGRESS : $ticket->status,
        ])->save();

        return response()->json([
            'support_ticket' => SupportTicketResource::make($this->supportTicketService->loadTicket($ticket->refresh())),
        ]);
    }

    public function close(Request $request, SupportTicket $ticket): JsonResponse
    {
        $ticket = $this->supportTicketService->closeTicket($ticket, $request->user());

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket),
        ]);
    }

    public function reopen(SupportTicket $ticket): JsonResponse
    {
        return response()->json([
            'support_ticket' => SupportTicketResource::make($this->supportTicketService->reopenTicket($ticket)),
        ]);
    }

    public function download(SupportMessageAttachment $attachment): StreamedResponse
    {
        return $this->attachmentStorage->download($attachment);
    }
}

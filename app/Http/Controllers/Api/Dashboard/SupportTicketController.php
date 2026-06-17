<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportTicket\SendSupportMessageRequest;
use App\Http\Requests\SupportTicket\StoreSupportTicketRequest;
use App\Http\Requests\SupportTicket\UpdateSupportTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportMessageAttachment;
use App\Models\SupportTicket;
use App\Services\SupportAttachmentStorage;
use App\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    use RespondsWithPagination;

    public function __construct(
        private readonly SupportTicketService $supportTicketService,
        private readonly SupportAttachmentStorage $attachmentStorage,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()
            ->supportTickets()
            ->with(['assignedTo', 'closedBy', 'messages.user.profile', 'messages.attachments'])
            ->orderByDesc('last_message_at')
            ->latest('updated_at')
            ->paginate($this->perPage($request));

        return $this->paginated($tickets, SupportTicketResource::class, 'support_tickets', $request);
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $ticket = $this->supportTicketService->createTicket(
            $request->user(),
            $request->validated(),
            $request->file('file'),
        );

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket),
        ], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicketOwner($request, $ticket);

        return response()->json([
            'support_ticket' => SupportTicketResource::make($this->supportTicketService->loadTicket($ticket)),
        ]);
    }

    public function message(SendSupportMessageRequest $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicketOwner($request, $ticket);

        $ticket = $this->supportTicketService->sendPartnerMessage(
            $ticket,
            $request->user(),
            $request->validated('message'),
            $request->file('file'),
        );

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket),
        ]);
    }

    public function update(UpdateSupportTicketRequest $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicketOwner($request, $ticket);

        if ($ticket->isClosed()) {
            abort(422, 'Closed tickets cannot be edited.');
        }

        $validated = $request->validated();

        $ticket->forceFill([
            'subject' => $validated['subject'],
            'category' => $validated['category'] ?? null,
            'message' => $validated['message'],
            'priority' => $validated['priority'] ?? $ticket->priority,
            'last_reply_at' => now(),
        ])->save();

        $message = $ticket->messages()->where('is_staff', false)->oldest()->first();

        if ($message) {
            $message->forceFill([
                'message' => $validated['message'],
            ])->save();
        } else {
            $ticket->messages()->create([
                'user_id' => $request->user()->id,
                'message' => $validated['message'],
                'is_staff' => false,
            ]);
        }

        return response()->json([
            'support_ticket' => SupportTicketResource::make($this->supportTicketService->loadTicket($ticket->refresh())),
        ]);
    }

    public function close(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicketOwner($request, $ticket);

        $ticket = $this->supportTicketService->closeTicket($ticket, $request->user());

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket),
        ]);
    }

    public function download(Request $request, SupportMessageAttachment $attachment): StreamedResponse
    {
        $attachment->load('message.ticket');
        $ticket = $attachment->message?->ticket;

        if (! $ticket || (int) $ticket->user_id !== (int) $request->user()?->id) {
            abort(403, 'You cannot access this attachment.');
        }

        return $this->attachmentStorage->download($attachment);
    }

    private function authorizeTicketOwner(Request $request, SupportTicket $ticket): void
    {
        if ((int) $ticket->user_id !== (int) $request->user()?->id) {
            abort(403, 'You cannot access this support ticket.');
        }
    }
}

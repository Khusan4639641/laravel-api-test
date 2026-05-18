<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportTicket\StoreSupportTicketRequest;
use App\Http\Requests\SupportTicket\UpdateSupportTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()
            ->supportTickets()
            ->with(['assignedTo', 'messages.user'])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($tickets, SupportTicketResource::class, 'support_tickets', $request);
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $ticket = $request->user()->supportTickets()->create([
            'subject' => $validated['subject'],
            'category' => $validated['category'] ?? null,
            'message' => $validated['message'],
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => $validated['priority'] ?? null,
            'last_reply_at' => now(),
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_staff' => false,
        ]);

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket->load(['assignedTo', 'messages.user'])),
        ], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicketOwner($request, $ticket);

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket->load(['assignedTo', 'messages.user'])),
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
            'support_ticket' => SupportTicketResource::make($ticket->refresh()->load(['assignedTo', 'messages.user'])),
        ]);
    }

    public function close(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicketOwner($request, $ticket);

        $ticket->forceFill([
            'status' => SupportTicket::STATUS_CLOSED,
            'closed_at' => now(),
            'last_reply_at' => now(),
        ])->save();

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket->refresh()->load(['assignedTo', 'messages.user'])),
        ]);
    }

    private function authorizeTicketOwner(Request $request, SupportTicket $ticket): void
    {
        if ((int) $ticket->user_id !== (int) $request->user()?->id) {
            abort(403, 'You cannot access this support ticket.');
        }
    }
}

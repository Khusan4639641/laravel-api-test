<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportTicket\AssignSupportTicketRequest;
use App\Http\Requests\SupportTicket\ReplySupportTicketRequest;
use App\Http\Requests\SupportTicket\UpdateSupportTicketStatusRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->with(['user.profile', 'assignedTo', 'messages.user'])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($tickets, SupportTicketResource::class, 'support_tickets', $request);
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket->load(['user.profile', 'assignedTo', 'messages.user'])),
        ]);
    }

    public function reply(ReplySupportTicketRequest $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validated();
        $status = $validated['status'] ?? SupportTicket::STATUS_ANSWERED;

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_staff' => true,
        ]);

        $ticket->forceFill([
            'assigned_to' => $ticket->assigned_to ?? $request->user()->id,
            'admin_reply' => $validated['message'],
            'status' => $status,
            'replied_at' => now(),
            'last_reply_at' => now(),
            'closed_at' => $status === SupportTicket::STATUS_CLOSED ? now() : null,
        ])->save();

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket->refresh()->load(['user.profile', 'assignedTo', 'messages.user'])),
        ]);
    }

    public function status(UpdateSupportTicketStatusRequest $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validated();

        $ticket->forceFill([
            'status' => $validated['status'],
            'closed_at' => $validated['status'] === SupportTicket::STATUS_CLOSED ? now() : null,
            'last_reply_at' => now(),
        ])->save();

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket->refresh()->load(['user.profile', 'assignedTo', 'messages.user'])),
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
            'support_ticket' => SupportTicketResource::make($ticket->refresh()->load(['user.profile', 'assignedTo', 'messages.user'])),
        ]);
    }

    public function close(SupportTicket $ticket): JsonResponse
    {
        $ticket->forceFill([
            'status' => SupportTicket::STATUS_CLOSED,
            'closed_at' => now(),
            'last_reply_at' => now(),
        ])->save();

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket->refresh()->load(['user.profile', 'assignedTo', 'messages.user'])),
        ]);
    }
}

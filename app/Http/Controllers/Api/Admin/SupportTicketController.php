<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->with('user.profile')
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($tickets, SupportTicketResource::class, 'support_tickets', $request);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'reply' => ['required', 'string'],
            'status' => ['nullable', 'string', 'max:255'],
        ]);

        $ticket->forceFill([
            'admin_reply' => $validated['reply'],
            'status' => $validated['status'] ?? 'answered',
            'replied_at' => now(),
            'last_reply_at' => now(),
        ])->save();

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket->refresh()->load('user.profile')),
        ]);
    }

    public function close(SupportTicket $ticket): JsonResponse
    {
        $ticket->forceFill([
            'status' => 'closed',
            'closed_at' => now(),
        ])->save();

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket->refresh()->load('user.profile')),
        ]);
    }
}

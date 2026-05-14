<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\SupportTicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()
            ->supportTickets()
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($tickets, SupportTicketResource::class, 'support_tickets', $request);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        $ticket = $request->user()->supportTickets()->create([
            ...$validated,
            'status' => 'open',
            'priority' => 'normal',
            'last_reply_at' => now(),
        ]);

        return response()->json([
            'support_ticket' => SupportTicketResource::make($ticket),
        ], 201);
    }
}

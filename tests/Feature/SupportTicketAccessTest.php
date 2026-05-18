<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_role_can_manage_support_tickets_but_not_full_admin_resources(): void
    {
        $support = User::factory()->create(['role' => 'support']);
        $otherSupport = User::factory()->create(['role' => 'support']);
        $user = User::factory()->create(['role' => 'user']);
        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'subject' => 'Need help',
            'category' => 'General',
            'message' => 'Please check my request.',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        Sanctum::actingAs($support);

        $this->getJson('/api/support/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'support_tickets');

        $this->getJson("/api/support/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('support_ticket.id', $ticket->id);

        $this->postJson("/api/support/tickets/{$ticket->id}/reply", [
            'message' => 'Answered by support.',
            'status' => 'answered',
        ])
            ->assertOk()
            ->assertJsonPath('support_ticket.status', 'answered')
            ->assertJsonPath('support_ticket.admin_reply', 'Answered by support.');

        $this->assertDatabaseHas('support_ticket_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => $support->id,
            'message' => 'Answered by support.',
            'is_staff' => 1,
        ]);

        $this->patchJson("/api/support/tickets/{$ticket->id}/status", [
            'status' => 'in_progress',
        ])
            ->assertOk()
            ->assertJsonPath('support_ticket.status', 'in_progress');

        $this->patchJson("/api/support/tickets/{$ticket->id}/assign", [
            'assigned_to' => $otherSupport->id,
        ])
            ->assertOk()
            ->assertJsonPath('support_ticket.assigned_to', $otherSupport->id);

        $this->getJson('/api/admin/products')->assertForbidden();
    }

    public function test_user_can_only_view_and_update_own_non_closed_support_tickets(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        $ownTicket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'subject' => 'Own ticket',
            'category' => 'General',
            'message' => 'Original message.',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $otherTicket = SupportTicket::query()->create([
            'user_id' => $otherUser->id,
            'subject' => 'Other ticket',
            'category' => 'General',
            'message' => 'Hidden message.',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/dashboard/support-tickets', [
            'subject' => 'Created ticket',
            'category' => 'General',
            'message' => 'Created by user.',
        ])
            ->assertCreated()
            ->assertJsonPath('support_ticket.status', 'open')
            ->assertJsonCount(1, 'support_ticket.messages');

        $this->assertDatabaseHas('support_ticket_messages', [
            'user_id' => $user->id,
            'message' => 'Created by user.',
            'is_staff' => 0,
        ]);

        $this->getJson('/api/dashboard/support-tickets')
            ->assertOk()
            ->assertJsonCount(2, 'support_tickets');

        $this->getJson("/api/dashboard/support-tickets/{$otherTicket->id}")
            ->assertForbidden();

        $this->putJson("/api/dashboard/support-tickets/{$otherTicket->id}", [
            'subject' => 'Should fail',
            'category' => 'General',
            'message' => 'Other ticket edit.',
        ])->assertForbidden();

        $this->patchJson("/api/dashboard/support-tickets/{$otherTicket->id}/close")
            ->assertForbidden();

        $this->putJson("/api/dashboard/support-tickets/{$ownTicket->id}", [
            'subject' => 'Updated subject',
            'category' => 'General',
            'message' => 'Updated message.',
        ])
            ->assertOk()
            ->assertJsonPath('support_ticket.subject', 'Updated subject');

        $this->patchJson("/api/dashboard/support-tickets/{$ownTicket->id}/close")
            ->assertOk()
            ->assertJsonPath('support_ticket.status', 'closed');

        $this->putJson("/api/dashboard/support-tickets/{$ownTicket->id}", [
            'subject' => 'Should fail',
            'category' => 'General',
            'message' => 'Closed ticket edit.',
        ])->assertUnprocessable();

        $this->deleteJson("/api/dashboard/support-tickets/{$ownTicket->id}")
            ->assertStatus(405);
    }
}

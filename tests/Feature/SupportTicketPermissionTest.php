<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_own_tickets_and_cannot_delete(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        $ownTicket = $this->ticketFor($user, 'Own ticket');
        $otherTicket = $this->ticketFor($otherUser, 'Other ticket');

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/support-tickets')
            ->assertOk()
            ->assertJsonCount(1, 'support_tickets')
            ->assertJsonPath('support_tickets.0.id', $ownTicket->id);

        $this->getJson("/api/dashboard/support-tickets/{$otherTicket->id}")->assertForbidden();
        $this->deleteJson("/api/dashboard/support-tickets/{$ownTicket->id}")->assertStatus(405);
    }

    public function test_user_can_close_own_ticket(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $ticket = $this->ticketFor($user, 'Close me');

        Sanctum::actingAs($user);

        $this->patchJson("/api/dashboard/support-tickets/{$ticket->id}/close")
            ->assertOk()
            ->assertJsonPath('support_ticket.status', SupportTicket::STATUS_CLOSED);
    }

    public function test_support_admin_and_super_admin_see_all_tickets(): void
    {
        $this->ticketFor(User::factory()->create(['role' => 'user']), 'First');
        $this->ticketFor(User::factory()->create(['role' => 'user']), 'Second');

        foreach (['support', 'admin', 'super_admin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->getJson('/api/support/tickets')
                ->assertOk()
                ->assertJsonCount(2, 'support_tickets');
        }
    }

    public function test_support_can_reply(): void
    {
        $support = User::factory()->create(['role' => 'support']);
        $ticket = $this->ticketFor(User::factory()->create(['role' => 'user']), 'Reply ticket');

        Sanctum::actingAs($support);

        $this->postJson("/api/support/tickets/{$ticket->id}/reply", [
            'message' => 'Support reply.',
            'status' => SupportTicket::STATUS_ANSWERED,
        ])
            ->assertOk()
            ->assertJsonPath('support_ticket.status', SupportTicket::STATUS_ANSWERED)
            ->assertJsonPath('support_ticket.admin_reply', 'Support reply.');
    }

    private function ticketFor(User $user, string $subject): SupportTicket
    {
        return SupportTicket::query()->create([
            'user_id' => $user->id,
            'subject' => $subject,
            'category' => 'General',
            'message' => 'Need help.',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'normal',
        ]);
    }
}

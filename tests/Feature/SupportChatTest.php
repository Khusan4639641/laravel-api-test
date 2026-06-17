<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_ticket_and_continue_chat_with_message(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/dashboard/support/tickets', [
            'subject' => 'Need help',
            'message' => 'Initial user message.',
        ])
            ->assertCreated()
            ->assertJsonPath('support_ticket.status', SupportTicket::STATUS_WAITING_ADMIN)
            ->assertJsonPath('support_ticket.messages.0.message', 'Initial user message.');

        $ticketId = $createResponse->json('support_ticket.id');

        $this->postJson("/api/dashboard/support/tickets/{$ticketId}/messages", [
            'message' => 'Additional user message.',
        ])
            ->assertOk()
            ->assertJsonPath('support_ticket.status', SupportTicket::STATUS_WAITING_ADMIN)
            ->assertJsonPath('support_ticket.messages.1.message', 'Additional user message.');
    }

    public function test_user_cannot_send_empty_message_without_file(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $ticket = $this->ticketFor($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/dashboard/support/tickets/{$ticket->id}/messages", [
            'message' => '',
        ])->assertUnprocessable();
    }

    public function test_user_can_upload_allowed_file_and_download_own_attachment(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        Sanctum::actingAs($user);

        $response = $this->post('/api/dashboard/support/tickets', [
            'subject' => 'File ticket',
            'message' => 'See attachment.',
            'file' => UploadedFile::fake()->create('document.pdf', 120, 'application/pdf'),
        ])
            ->assertCreated()
            ->assertJsonPath('support_ticket.messages.0.attachments.0.original_name', 'document.pdf');

        $attachmentId = $response->json('support_ticket.messages.0.attachments.0.id');

        $this->get("/api/dashboard/support/attachments/{$attachmentId}/download")
            ->assertOk();
    }

    public function test_user_cannot_upload_file_over_five_megabytes(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        Sanctum::actingAs($user);

        $this->withHeader('Accept', 'application/json')
            ->post('/api/dashboard/support/tickets', [
                'subject' => 'Big file',
                'file' => UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf'),
            ])->assertUnprocessable();
    }

    public function test_user_cannot_access_another_user_ticket_or_attachment(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $other = User::factory()->create(['role' => User::ROLE_USER]);

        Sanctum::actingAs($owner);
        $response = $this->post('/api/dashboard/support/tickets', [
            'subject' => 'Private file',
            'file' => UploadedFile::fake()->create('private.txt', 10, 'text/plain'),
        ])->assertCreated();
        $ticketId = $response->json('support_ticket.id');
        $attachmentId = $response->json('support_ticket.messages.0.attachments.0.id');

        Sanctum::actingAs($other);

        $this->getJson("/api/dashboard/support/tickets/{$ticketId}")
            ->assertForbidden();
        $this->get("/api/dashboard/support/attachments/{$attachmentId}/download")
            ->assertForbidden();
    }

    public function test_user_cannot_send_message_to_closed_ticket(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $ticket = $this->ticketFor($user, ['status' => SupportTicket::STATUS_CLOSED, 'closed_at' => now()]);

        Sanctum::actingAs($user);

        $this->postJson("/api/dashboard/support/tickets/{$ticket->id}/messages", [
            'message' => 'Can I add more?',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Обращение закрыто. Создайте новое обращение.');
    }

    public function test_admin_can_list_open_answer_close_and_download_attachment(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        Sanctum::actingAs($user);
        $createResponse = $this->post('/api/dashboard/support/tickets', [
            'subject' => 'Admin flow',
            'message' => 'Question for admin.',
            'file' => UploadedFile::fake()->create('screen.png', 120, 'image/png'),
        ])->assertCreated();
        $ticketId = $createResponse->json('support_ticket.id');
        $attachmentId = $createResponse->json('support_ticket.messages.0.attachments.0.id');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/support/tickets')
            ->assertOk()
            ->assertJsonPath('support_tickets.0.id', $ticketId);

        $this->getJson("/api/admin/support/tickets/{$ticketId}")
            ->assertOk()
            ->assertJsonPath('support_ticket.id', $ticketId);

        $this->postJson("/api/admin/support/tickets/{$ticketId}/messages", [
            'message' => 'Admin answer.',
        ])
            ->assertOk()
            ->assertJsonPath('support_ticket.status', SupportTicket::STATUS_WAITING_USER);

        $this->get("/api/admin/support/attachments/{$attachmentId}/download")
            ->assertOk();

        $this->postJson("/api/admin/support/tickets/{$ticketId}/close")
            ->assertOk()
            ->assertJsonPath('support_ticket.status', SupportTicket::STATUS_CLOSED);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticketId,
            'closed_by' => $admin->id,
        ]);
    }

    public function test_non_admin_cannot_access_admin_support_endpoints(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/support/tickets')
            ->assertForbidden();
    }

    private function ticketFor(User $user, array $attributes = []): SupportTicket
    {
        return SupportTicket::query()->create([
            'user_id' => $user->id,
            'subject' => 'Existing ticket',
            'category' => 'General',
            'message' => 'Need help.',
            'status' => SupportTicket::STATUS_WAITING_ADMIN,
            'priority' => 'normal',
            'last_message_at' => now(),
            ...$attributes,
        ]);
    }
}

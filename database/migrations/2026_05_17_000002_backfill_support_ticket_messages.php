<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_ticket_messages')) {
            return;
        }

        DB::table('support_tickets')
            ->orderBy('id')
            ->chunkById(100, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    if (! empty($ticket->message)) {
                        DB::table('support_ticket_messages')->insert([
                            'ticket_id' => $ticket->id,
                            'user_id' => $ticket->user_id,
                            'message' => $ticket->message,
                            'is_staff' => false,
                            'created_at' => $ticket->created_at ?? now(),
                            'updated_at' => $ticket->updated_at ?? now(),
                        ]);
                    }

                    if (! empty($ticket->admin_reply)) {
                        DB::table('support_ticket_messages')->insert([
                            'ticket_id' => $ticket->id,
                            'user_id' => $ticket->assigned_to ?? null,
                            'message' => $ticket->admin_reply,
                            'is_staff' => true,
                            'created_at' => $ticket->replied_at ?? $ticket->updated_at ?? now(),
                            'updated_at' => $ticket->replied_at ?? $ticket->updated_at ?? now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        //
    }
};

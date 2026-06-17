<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_tickets', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('support_tickets', 'last_message_at')) {
                $table->timestamp('last_message_at')->nullable()->after('last_reply_at')->index();
            }

            if (! Schema::hasColumn('support_tickets', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_ticket_messages', 'sender_role')) {
                $table->string('sender_role')->nullable()->after('user_id')->index();
            }
        });

        Schema::create('support_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('support_ticket_messages')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk')->default('local');
            $table->timestamps();

            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_message_attachments');

        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('support_ticket_messages', 'sender_role')) {
                $table->dropColumn('sender_role');
            }
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('support_tickets', 'closed_by')) {
                $table->dropConstrainedForeignId('closed_by');
            }

            if (Schema::hasColumn('support_tickets', 'last_message_at')) {
                $table->dropColumn('last_message_at');
            }

            if (Schema::hasColumn('support_tickets', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};

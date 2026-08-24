<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_tickets', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }

            if (Schema::hasColumn('support_tickets', 'priority')) {
                $table->string('priority')->nullable()->default(null)->change();
            }
        });

        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('message');
            $table->boolean('is_staff')->default(false)->index();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');

        Schema::table('support_tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('support_tickets', 'assigned_to')) {
                $table->dropConstrainedForeignId('assigned_to');
            }

            if (Schema::hasColumn('support_tickets', 'priority')) {
                $table->string('priority')->default('normal')->change();
            }
        });
    }
};

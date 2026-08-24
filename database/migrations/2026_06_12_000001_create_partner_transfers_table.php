<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('KZT');
            $table->string('status')->default('completed')->index();
            $table->foreignId('sender_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('recipient_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('sender_user_id');
            $table->index('recipient_user_id');
            $table->index('created_at');
            $table->unique(['sender_user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_transfers');
    }
};

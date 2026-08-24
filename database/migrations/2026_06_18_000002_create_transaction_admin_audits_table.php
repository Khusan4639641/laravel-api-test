<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_admin_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action')->index();
            $table->decimal('old_amount', 18, 2)->nullable();
            $table->decimal('new_amount', 18, 2)->nullable();
            $table->json('old_payload')->nullable();
            $table->json('new_payload')->nullable();
            $table->text('reason');
            $table->timestamps();

            $table->index(['transaction_id', 'action']);
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_admin_audits');
    }
};

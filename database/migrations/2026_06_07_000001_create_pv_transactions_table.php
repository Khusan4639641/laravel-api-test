<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pv_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('upline_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('source')->index();
            $table->string('branch', 1)->index();
            $table->decimal('pv', 15, 2);
            $table->boolean('is_bonusable')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'source']);
            $table->index(['upline_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pv_transactions');
    }
};

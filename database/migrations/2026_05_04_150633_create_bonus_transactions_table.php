<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bonus_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_transaction_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('source_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('source_order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();
            $table->string('bonus_type')->index();
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('left_pv', 15, 2)->default(0);
            $table->decimal('right_pv', 15, 2)->default(0);
            $table->decimal('matched_pv', 15, 2)->default(0);
            $table->string('status')->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('source_user_id');
            $table->index('source_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_transactions');
    }
};

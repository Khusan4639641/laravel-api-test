<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('binary_bonus_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bonus_transaction_id')->nullable()->constrained('bonus_transactions')->nullOnDelete();
            $table->string('status')->default('completed');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->decimal('weak_leg_pv', 15, 2)->default(0);
            $table->decimal('used_left_pv', 15, 2)->default(0);
            $table->decimal('used_right_pv', 15, 2)->default(0);
            $table->decimal('carry_left_pv', 15, 2)->default(0);
            $table->decimal('carry_right_pv', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'period_start', 'period_end']);
        });

        Schema::create('binary_bonus_calculations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('binary_bonus_run_id')->constrained('binary_bonus_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bonus_transaction_id')->nullable()->constrained('bonus_transactions')->nullOnDelete();
            $table->decimal('left_pv', 15, 2)->default(0);
            $table->decimal('right_pv', 15, 2)->default(0);
            $table->decimal('weak_leg_pv', 15, 2)->default(0);
            $table->decimal('used_left_pv', 15, 2)->default(0);
            $table->decimal('used_right_pv', 15, 2)->default(0);
            $table->decimal('carry_left_pv', 15, 2)->default(0);
            $table->decimal('carry_right_pv', 15, 2)->default(0);
            $table->decimal('money_base_amount', 15, 2)->default(0);
            $table->decimal('binary_percent', 8, 2)->default(0);
            $table->decimal('bonus_amount', 15, 2)->default(0);
            $table->decimal('main_amount', 15, 2)->default(0);
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binary_bonus_calculations');
        Schema::dropIfExists('binary_bonus_runs');
    }
};

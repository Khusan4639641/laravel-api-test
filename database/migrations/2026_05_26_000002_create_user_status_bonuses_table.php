<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_status_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('status_bonus_definition_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('bonus_transaction_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('status_code')->index();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 3)->default('KZT');
            $table->text('reward_text');
            $table->timestamp('awarded_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'status_bonus_definition_id'], 'user_status_bonus_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_status_bonuses');
    }
};

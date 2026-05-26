<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_x2_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('x2_bonus_definition_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('bonus_transaction_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('code')->index();
            $table->unsignedSmallInteger('qualified_count')->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 3)->default('KZT');
            $table->text('reward_text');
            $table->timestamp('awarded_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'x2_bonus_definition_id'], 'user_x2_bonus_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_x2_bonuses');
    }
};

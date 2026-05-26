<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x2_bonus_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('required_status')->index();
            $table->unsignedSmallInteger('required_count')->default(5);
            $table->string('reward_type')->default('gift');
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 3)->default('KZT');
            $table->text('reward_text');
            $table->boolean('is_cash_bonus')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x2_bonus_definitions');
    }
};

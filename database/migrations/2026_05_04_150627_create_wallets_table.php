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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('main');
            $table->string('currency', 3)->default('USD');
            $table->decimal('balance', 18, 2)->default(0);
            $table->decimal('hold_balance', 18, 2)->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};

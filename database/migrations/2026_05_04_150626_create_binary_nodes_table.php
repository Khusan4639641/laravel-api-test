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
        Schema::create('binary_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('binary_nodes')
                ->nullOnDelete();
            $table->string('position')->nullable();
            $table->unsignedInteger('depth')->default(0);
            $table->string('path')->nullable()->index();
            $table->timestamps();

            $table->index('user_id');
            $table->index('parent_id');
            $table->unique(['parent_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('binary_nodes');
    }
};

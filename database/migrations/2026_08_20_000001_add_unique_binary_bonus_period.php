<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('binary_bonus_runs', function (Blueprint $table): void {
            $table->unique(
                ['user_id', 'period_start', 'period_end'],
                'binary_bonus_runs_user_period_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('binary_bonus_runs', function (Blueprint $table): void {
            $table->dropUnique('binary_bonus_runs_user_period_unique');
        });
    }
};

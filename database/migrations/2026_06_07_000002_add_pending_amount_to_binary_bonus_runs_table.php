<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('binary_bonus_runs', 'pending_amount')) {
            Schema::table('binary_bonus_runs', function (Blueprint $table): void {
                $table->decimal('pending_amount', 15, 2)->default(0)->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('binary_bonus_runs', 'pending_amount')) {
            Schema::table('binary_bonus_runs', function (Blueprint $table): void {
                $table->dropColumn('pending_amount');
            });
        }
    }
};

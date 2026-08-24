<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            if (! Schema::hasColumn('packages', 'activity_pv')) {
                $table->decimal('activity_pv', 15, 2)->default(0)->after('pv');
            }

            if (! Schema::hasColumn('packages', 'turnover_pv')) {
                $table->decimal('turnover_pv', 15, 2)->default(0)->after('activity_pv');
            }
        });

        DB::table('packages')
            ->where('activity_pv', 0)
            ->update([
                'activity_pv' => DB::raw('pv'),
                'turnover_pv' => DB::raw('pv'),
            ]);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            if (Schema::hasColumn('packages', 'turnover_pv')) {
                $table->dropColumn('turnover_pv');
            }

            if (Schema::hasColumn('packages', 'activity_pv')) {
                $table->dropColumn('activity_pv');
            }
        });
    }
};

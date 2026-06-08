<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('wallet_transactions', 'affects_balance')) {
                $table->boolean('affects_balance')->default(true)->after('status')->index();
            }
        });

        DB::table('wallet_transactions')
            ->whereIn('type', [
                'package_activity_credit',
                'package_activation_credit',
                'package_upgrade_credit',
                'admin_package_assignment_credit',
                'package_activation',
                'package_upgrade',
                'package_assignment',
            ])
            ->update(['affects_balance' => false]);
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('wallet_transactions', 'affects_balance')) {
                $table->dropColumn('affects_balance');
            }
        });
    }
};

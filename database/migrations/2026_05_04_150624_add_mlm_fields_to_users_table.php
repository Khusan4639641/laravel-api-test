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
        Schema::table('users', function (Blueprint $table) {
            $table->string('login')->unique()->after('id');
            $table->foreignId('sponsor_id')
                ->nullable()
                ->after('password')
                ->index()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('current_package_id')
                ->nullable()
                ->after('sponsor_id')
                ->constrained('packages')
                ->nullOnDelete();
            $table->string('status')->default('active')->after('current_package_id')->index();
            $table->decimal('left_pv', 15, 2)->default(0)->after('status');
            $table->decimal('right_pv', 15, 2)->default(0)->after('left_pv');
            $table->decimal('remaining_left_pv', 15, 2)->default(0)->after('right_pv');
            $table->decimal('remaining_right_pv', 15, 2)->default(0)->after('remaining_left_pv');
            $table->decimal('total_pv', 15, 2)->default(0)->after('remaining_right_pv');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['sponsor_id']);
            $table->dropForeign(['current_package_id']);
            $table->dropUnique(['login']);
            $table->dropIndex(['sponsor_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'login',
                'sponsor_id',
                'current_package_id',
                'status',
                'left_pv',
                'right_pv',
                'remaining_left_pv',
                'remaining_right_pv',
                'total_pv',
            ]);
        });
    }
};

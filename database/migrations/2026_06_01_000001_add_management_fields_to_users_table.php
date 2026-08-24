<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_status')->default('active')->after('status')->index();
            $table->text('admin_note')->nullable()->after('account_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['account_status']);
            $table->dropColumn(['account_status', 'admin_note']);
        });
    }
};

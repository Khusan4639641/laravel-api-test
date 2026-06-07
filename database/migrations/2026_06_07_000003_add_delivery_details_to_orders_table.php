<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'recipient_name')) {
                $table->string('recipient_name')->nullable()->after('shipping_address');
            }

            if (! Schema::hasColumn('orders', 'phone')) {
                $table->string('phone')->nullable()->after('recipient_name');
            }

            if (! Schema::hasColumn('orders', 'city')) {
                $table->string('city')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('orders', 'delivery_address')) {
                $table->text('delivery_address')->nullable()->after('city');
            }

            if (! Schema::hasColumn('orders', 'comment')) {
                $table->text('comment')->nullable()->after('delivery_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            foreach (['comment', 'delivery_address', 'city', 'phone', 'recipient_name'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

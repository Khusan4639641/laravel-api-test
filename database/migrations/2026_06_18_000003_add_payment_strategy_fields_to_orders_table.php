<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'payment_strategy')) {
                $table->string('payment_strategy')->default('card_100')->after('payment_status')->index();
            }

            if (! Schema::hasColumn('orders', 'card_amount')) {
                $table->decimal('card_amount', 18, 2)->default(0)->after('total_amount');
            }

            if (! Schema::hasColumn('orders', 'deposit_amount')) {
                $table->decimal('deposit_amount', 18, 2)->default(0)->after('card_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            foreach (['deposit_amount', 'card_amount', 'payment_strategy'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

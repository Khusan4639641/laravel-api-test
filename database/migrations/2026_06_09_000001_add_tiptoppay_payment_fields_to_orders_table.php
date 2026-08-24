<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status')->default('unpaid')->index()->after('status');
            }

            if (! Schema::hasColumn('orders', 'payment_provider')) {
                $table->string('payment_provider')->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('orders', 'payment_external_id')) {
                $table->string('payment_external_id')->nullable()->index()->after('payment_provider');
            }

            if (! Schema::hasColumn('orders', 'payment_transaction_id')) {
                $table->string('payment_transaction_id')->nullable()->index()->after('payment_external_id');
            }

            if (! Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_transaction_id');
            }

            if (! Schema::hasColumn('orders', 'payment_meta')) {
                $table->json('payment_meta')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            foreach (['payment_meta', 'paid_at', 'payment_transaction_id', 'payment_external_id', 'payment_provider'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

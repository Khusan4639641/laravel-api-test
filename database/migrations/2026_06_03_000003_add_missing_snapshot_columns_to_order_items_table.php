<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'product_name')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->string('product_name')->nullable()->after('product_id');
            });
        }

        if (! Schema::hasColumn('order_items', 'unit_price')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->decimal('unit_price', 12, 2)->default(0)->after('quantity');
            });
        }

        if (! Schema::hasColumn('order_items', 'unit_pv')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->decimal('unit_pv', 12, 2)->default(0)->after('unit_price');
            });
        }

        if (! Schema::hasColumn('order_items', 'total_price')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->decimal('total_price', 12, 2)->default(0)->after('unit_pv');
            });
        }

        if (! Schema::hasColumn('order_items', 'total_pv')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->decimal('total_pv', 12, 2)->default(0)->after('total_price');
            });
        }

        if (! Schema::hasColumn('order_items', 'item_snapshot')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->json('item_snapshot')->nullable()->after('total_pv');
            });
        }
    }

    public function down(): void
    {
        // Repair migration: keep existing order item data and original schema columns intact.
    }
};

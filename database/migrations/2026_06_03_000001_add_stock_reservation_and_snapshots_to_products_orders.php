<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'reserved_quantity')) {
                $table->unsignedInteger('reserved_quantity')->default(0)->after('stock_quantity');
            }

            if (! Schema::hasColumn('products', 'image_path')) {
                $table->string('image_path')->nullable()->after('status');
            }
        });

        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'product_name')) {
                $table->string('product_name')->nullable()->after('product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'product_name')) {
                $table->dropColumn('product_name');
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'image_path')) {
                $table->dropColumn('image_path');
            }

            if (Schema::hasColumn('products', 'reserved_quantity')) {
                $table->dropColumn('reserved_quantity');
            }
        });
    }
};


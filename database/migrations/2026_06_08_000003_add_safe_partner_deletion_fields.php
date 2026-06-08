<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->index();
            }

            if (! Schema::hasColumn('users', 'deleted_by')) {
                $table->foreignId('deleted_by')
                    ->nullable()
                    ->after('deleted_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'deleted_reason')) {
                $table->text('deleted_reason')->nullable()->after('deleted_by');
            }

            if (! Schema::hasColumn('users', 'deleted_meta')) {
                $table->json('deleted_meta')->nullable()->after('deleted_reason');
            }
        });

        Schema::table('binary_nodes', function (Blueprint $table): void {
            if (! Schema::hasColumn('binary_nodes', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('path')->index();
            }

            if (! Schema::hasColumn('binary_nodes', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }

            if (! Schema::hasColumn('binary_nodes', 'deleted_by')) {
                $table->foreignId('deleted_by')
                    ->nullable()
                    ->after('deleted_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('binary_nodes', 'deleted_reason')) {
                $table->text('deleted_reason')->nullable()->after('deleted_by');
            }

            if (! Schema::hasColumn('binary_nodes', 'deleted_meta')) {
                $table->json('deleted_meta')->nullable()->after('deleted_reason');
            }
        });

        Schema::table('pv_transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('pv_transactions', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('is_bonusable')->index();
            }

            if (! Schema::hasColumn('pv_transactions', 'voided_by')) {
                $table->foreignId('voided_by')
                    ->nullable()
                    ->after('voided_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasTable('admin_action_logs')) {
            Schema::create('admin_action_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action')->index();
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['action', 'target_user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_logs');

        Schema::table('pv_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('pv_transactions', 'voided_by')) {
                $table->dropConstrainedForeignId('voided_by');
            }

            if (Schema::hasColumn('pv_transactions', 'voided_at')) {
                $table->dropColumn('voided_at');
            }
        });

        Schema::table('binary_nodes', function (Blueprint $table): void {
            foreach (['deleted_meta', 'deleted_reason'] as $column) {
                if (Schema::hasColumn('binary_nodes', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('binary_nodes', 'deleted_by')) {
                $table->dropConstrainedForeignId('deleted_by');
            }

            if (Schema::hasColumn('binary_nodes', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('binary_nodes', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            foreach (['deleted_meta', 'deleted_reason'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('users', 'deleted_by')) {
                $table->dropConstrainedForeignId('deleted_by');
            }

            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};

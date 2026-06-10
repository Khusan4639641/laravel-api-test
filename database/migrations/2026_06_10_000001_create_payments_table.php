<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('payable_type')->nullable();
                $table->unsignedBigInteger('payable_id')->nullable();
                $table->string('type')->index();
                $table->string('provider')->default('tiptoppay')->index();
                $table->string('external_id')->unique();
                $table->decimal('amount', 18, 2);
                $table->string('currency', 3)->default('KZT');
                $table->string('status')->default('pending')->index();
                $table->string('description')->nullable();
                $table->json('payload')->nullable();
                $table->json('provider_response')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();

                $table->index(['payable_type', 'payable_id']);
                $table->index(['user_id', 'type', 'status']);
            });

            return;
        }

        $this->addColumnIfMissing('payments', 'user_id', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $this->addColumnIfMissing('payments', 'payable_type', function (Blueprint $table): void {
            $table->string('payable_type')->nullable()->after('user_id');
        });

        $this->addColumnIfMissing('payments', 'payable_id', function (Blueprint $table): void {
            $table->unsignedBigInteger('payable_id')->nullable()->after('payable_type');
        });

        $this->addColumnIfMissing('payments', 'type', function (Blueprint $table): void {
            $table->string('type')->default('order')->after('payable_id');
        });

        $this->addColumnIfMissing('payments', 'provider', function (Blueprint $table): void {
            $table->string('provider')->default('tiptoppay')->after('type');
        });

        $this->addColumnIfMissing('payments', 'external_id', function (Blueprint $table): void {
            $table->string('external_id')->nullable()->after('provider');
        });

        $this->addColumnIfMissing('payments', 'amount', function (Blueprint $table): void {
            $table->decimal('amount', 18, 2)->default(0)->after('external_id');
        });

        $this->addColumnIfMissing('payments', 'currency', function (Blueprint $table): void {
            $table->string('currency', 3)->default('KZT')->after('amount');
        });

        $this->addColumnIfMissing('payments', 'status', function (Blueprint $table): void {
            $table->string('status')->default('pending')->after('currency');
        });

        $this->addColumnIfMissing('payments', 'description', function (Blueprint $table): void {
            $table->string('description')->nullable()->after('status');
        });

        $this->addColumnIfMissing('payments', 'payload', function (Blueprint $table): void {
            $table->json('payload')->nullable()->after('description');
        });

        $this->addColumnIfMissing('payments', 'provider_response', function (Blueprint $table): void {
            $table->json('provider_response')->nullable()->after('payload');
        });

        $this->addColumnIfMissing('payments', 'paid_at', function (Blueprint $table): void {
            $table->timestamp('paid_at')->nullable()->after('provider_response');
        });

        $this->addColumnIfMissing('payments', 'failed_at', function (Blueprint $table): void {
            $table->timestamp('failed_at')->nullable()->after('paid_at');
        });

        if (! Schema::hasColumn('payments', 'created_at') && ! Schema::hasColumn('payments', 'updated_at')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->timestamps();
            });
        }

        if (Schema::hasColumn('payments', 'external_id') && ! Schema::hasIndex('payments', 'payments_external_id_unique')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->unique('external_id', 'payments_external_id_unique');
            });
        }

        if (Schema::hasColumn('payments', 'payable_type') && Schema::hasColumn('payments', 'payable_id') && ! Schema::hasIndex('payments', 'payments_payable_type_payable_id_index')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->index(['payable_type', 'payable_id'], 'payments_payable_type_payable_id_index');
            });
        }

        if (Schema::hasColumn('payments', 'user_id') && Schema::hasColumn('payments', 'type') && Schema::hasColumn('payments', 'status') && ! Schema::hasIndex('payments', 'payments_user_id_type_status_index')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->index(['user_id', 'type', 'status'], 'payments_user_id_type_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }

    private function addColumnIfMissing(string $tableName, string $column, callable $callback): void
    {
        if (Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, $callback);
    }
};

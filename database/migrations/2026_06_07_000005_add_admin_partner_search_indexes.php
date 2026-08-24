<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasIndex('users', 'users_name_index')) {
                $table->index('name', 'users_name_index');
            }
        });

        Schema::table('user_profiles', function (Blueprint $table): void {
            if (! Schema::hasIndex('user_profiles', 'user_profiles_city_index')) {
                $table->index('city', 'user_profiles_city_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            if (Schema::hasIndex('user_profiles', 'user_profiles_city_index')) {
                $table->dropIndex('user_profiles_city_index');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasIndex('users', 'users_name_index')) {
                $table->dropIndex('users_name_index');
            }
        });
    }
};

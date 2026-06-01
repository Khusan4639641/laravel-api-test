<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->json('name_translations')->nullable();
            $table->json('description_translations')->nullable();
            $table->json('category_translations')->nullable();
            $table->json('short_description_translations')->nullable();
            $table->json('benefits_translations')->nullable();
            $table->json('composition_translations')->nullable();
            $table->json('usage_translations')->nullable();
        });

        Schema::table('news', function (Blueprint $table): void {
            $table->json('title_translations')->nullable();
            $table->json('category_translations')->nullable();
            $table->json('excerpt_translations')->nullable();
            $table->json('content_translations')->nullable();
        });

        Schema::table('faqs', function (Blueprint $table): void {
            $table->json('category_translations')->nullable();
            $table->json('question_translations')->nullable();
            $table->json('answer_translations')->nullable();
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->json('name_translations')->nullable();
            $table->json('description_translations')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'name_translations',
                'description_translations',
                'category_translations',
                'short_description_translations',
                'benefits_translations',
                'composition_translations',
                'usage_translations',
            ]);
        });

        Schema::table('news', function (Blueprint $table): void {
            $table->dropColumn([
                'title_translations',
                'category_translations',
                'excerpt_translations',
                'content_translations',
            ]);
        });

        Schema::table('faqs', function (Blueprint $table): void {
            $table->dropColumn([
                'category_translations',
                'question_translations',
                'answer_translations',
            ]);
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn(['name_translations', 'description_translations']);
        });
    }
};

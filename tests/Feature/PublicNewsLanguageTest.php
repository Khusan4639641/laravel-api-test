<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNewsLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_news_return_localized_title_and_excerpt(): void
    {
        News::query()->create([
            'title' => 'Русская новость',
            'slug' => 'news-lang',
            'category' => 'Компания',
            'excerpt' => 'Русский анонс',
            'content' => 'Русский текст',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
            'title_translations' => ['ru' => 'Русская новость', 'mn' => 'Монгол мэдээ'],
            'category_translations' => ['ru' => 'Компания', 'mn' => 'Компани'],
            'excerpt_translations' => ['ru' => 'Русский анонс', 'mn' => 'Монгол товч'],
            'content_translations' => ['ru' => 'Русский текст', 'mn' => 'Монгол текст'],
        ]);

        $this->getJson('/api/public/news', ['Accept-Language' => 'mn'])
            ->assertOk()
            ->assertJsonPath('news.0.title', 'Монгол мэдээ')
            ->assertJsonPath('news.0.category', 'Компани')
            ->assertJsonPath('news.0.excerpt', 'Монгол товч');
    }
}

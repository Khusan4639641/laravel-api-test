<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminNewsImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_news_with_uploaded_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->post('/api/admin/news', [
            'title' => 'News with image',
            'category' => 'Company',
            'status' => 'published',
            'excerpt' => 'Short text',
            'content' => 'Full news content',
            'image' => $this->tinyPngUpload('news.png'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('message', 'Новость создана');

        $imageUrl = $response->json('news.image_url');

        $this->assertIsString($imageUrl);
        $this->assertStringStartsWith('/storage/news/', $imageUrl);
        Storage::disk('public')->assertExists(Str::after($imageUrl, '/storage/'));
        $this->assertDatabaseHas('news', [
            'title' => 'News with image',
            'image_url' => $imageUrl,
        ]);
    }

    public function test_admin_can_update_news_image(): void
    {
        Storage::fake('public');
        $news = News::query()->create([
            'title' => 'Old news',
            'slug' => 'old-news',
            'category' => 'Company',
            'excerpt' => 'Old text',
            'content' => 'Old content',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->post("/api/admin/news/{$news->id}", [
            '_method' => 'PUT',
            'title' => 'Updated news',
            'category' => 'Company',
            'status' => 'published',
            'content' => 'Updated content',
            'image' => $this->tinyPngUpload('updated.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Новость сохранена');

        $imageUrl = $response->json('news.image_url');

        $this->assertIsString($imageUrl);
        $this->assertStringStartsWith('/storage/news/', $imageUrl);
        Storage::disk('public')->assertExists(Str::after($imageUrl, '/storage/'));
        $this->assertSame($imageUrl, $news->refresh()->image_url);
    }

    public function test_invalid_image_type_returns_422(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->post('/api/admin/news', [
            'title' => 'Invalid image',
            'category' => 'Company',
            'status' => 'published',
            'content' => 'Full news content',
            'image' => UploadedFile::fake()->create('news.pdf', 12, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_image_over_max_size_returns_422(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->post('/api/admin/news', [
            'title' => 'Large image',
            'category' => 'Company',
            'status' => 'published',
            'content' => 'Full news content',
            'image' => UploadedFile::fake()->create('large.png', 5121, 'image/png'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_admin_can_create_news_without_image(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->postJson('/api/admin/news', [
            'title' => 'News without image',
            'category' => 'Company',
            'status' => 'draft',
            'content' => 'Full news content',
        ])
            ->assertCreated()
            ->assertJsonPath('news.title', 'News without image')
            ->assertJsonPath('news.image_url', null);
    }

    public function test_news_list_returns_image_url(): void
    {
        $news = News::query()->create([
            'title' => 'Listed news',
            'slug' => 'listed-news',
            'category' => 'Company',
            'content' => 'Full content',
            'image_url' => '/storage/news/listed.png',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/news')
            ->assertOk()
            ->assertJsonPath('news.0.id', $news->id)
            ->assertJsonPath('news.0.image_url', '/storage/news/listed.png');
    }

    public function test_unauthenticated_user_cannot_create_news(): void
    {
        $this->postJson('/api/admin/news', [
            'title' => 'Unauthorized news',
            'category' => 'Company',
            'status' => 'published',
            'content' => 'Full news content',
        ])->assertUnauthorized();
    }

    public function test_non_admin_cannot_create_news(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));

        $this->postJson('/api/admin/news', [
            'title' => 'Forbidden news',
            'category' => 'Company',
            'status' => 'published',
            'content' => 'Full news content',
        ])->assertForbidden();
    }

    public function test_create_news_returns_validation_errors_instead_of_500(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->postJson('/api/admin/news', [
            'title' => '',
            'category' => '',
            'status' => 'published',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'category', 'content']);
    }

    private function tinyPngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='
        ));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'user']);

        Sanctum::actingAs($user);

        $response = $this->patch('/api/dashboard/profile/avatar', [
            'avatar' => $this->tinyPngUpload('avatar.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $avatarPath = $response->json('avatar_path');
        $avatarUrl = $response->json('avatar_url');

        $this->assertIsString($avatarPath);
        $this->assertStringStartsWith('avatars/', $avatarPath);
        Storage::disk('public')->assertExists($avatarPath);

        $this->assertIsString($avatarUrl);
        $this->assertStringContainsString('/storage/avatars/', $avatarUrl);
        $this->assertSame($avatarPath, $user->refresh()->avatar_path);
        $this->assertSame($avatarPath, $user->profile()->firstOrFail()->avatar_path);
    }

    public function test_invalid_avatar_file_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'user']);

        Sanctum::actingAs($user);

        $this->patch('/api/dashboard/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('avatar.txt', 12, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        Storage::disk('public')->assertMissing('avatars/avatar.txt');
        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_avatar_upload_accepts_browser_form_post(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'user']);

        Sanctum::actingAs($user);

        $this->post('/api/dashboard/profile/avatar', [
            '_method' => 'PATCH',
            'avatar' => $this->tinyPngUpload('avatar.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->whereType('avatar_url', 'string')
                ->etc()
            );

        $this->assertNotNull($user->refresh()->avatar_path);
    }

    public function test_avatar_url_is_returned_from_me_and_profile_api(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'user']);

        Sanctum::actingAs($user);

        $avatarPath = $this->patch('/api/dashboard/profile/avatar', [
            'avatar' => $this->tinyPngUpload('avatar.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('avatar_path');

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.avatar_path', $avatarPath)
            ->assertJsonPath('user.profile.avatar_path', $avatarPath)
            ->assertJson(fn ($json) => $json
                ->whereType('user.avatar_url', 'string')
                ->whereType('user.profile.avatar_url', 'string')
                ->etc()
            );

        $this->getJson('/api/dashboard/profile')
            ->assertOk()
            ->assertJsonPath('user.avatar_path', $avatarPath)
            ->assertJson(fn ($json) => $json
                ->whereType('user.avatar_url', 'string')
                ->etc()
            );
    }

    public function test_unauthorized_user_cannot_upload_avatar(): void
    {
        Storage::fake('public');

        $this->patch('/api/dashboard/profile/avatar', [
            'avatar' => $this->tinyPngUpload('avatar.png'),
        ], ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    private function tinyPngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='
        ));
    }
}

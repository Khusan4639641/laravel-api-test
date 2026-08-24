<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BinaryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerSoftDeleteIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_partner_releases_identity_and_stores_original_values(): void
    {
        $partner = $this->partner('megalider', 'sholpanuzen@mail.ru', '87013956362');

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        $deleted = User::withTrashed()->with('profile')->findOrFail($partner->id);

        $this->assertNotNull($deleted->deleted_at);
        $this->assertStringStartsWith("deleted_{$partner->id}_", $deleted->login);
        $this->assertStringStartsWith("deleted+{$partner->id}+", $deleted->email);
        $this->assertStringEndsWith('@safilife.deleted', $deleted->email);
        $this->assertStringStartsWith("deleted_{$partner->id}_", (string) $deleted->profile?->phone);
        $this->assertSame('megalider', $deleted->deleted_meta['original_login'] ?? null);
        $this->assertSame('sholpanuzen@mail.ru', $deleted->deleted_meta['original_email'] ?? null);
        $this->assertSame('87013956362', $deleted->deleted_meta['original_phone'] ?? null);
        $this->assertSame('megalider', $deleted->deleted_meta['original_referral_code'] ?? null);
        $this->assertNotEmpty($deleted->deleted_meta['identity_released_at'] ?? null);
    }

    public function test_can_register_new_user_with_same_login_email_phone_after_delete(): void
    {
        $partner = $this->partner('reuse-login', 'reuse@example.test', '87010000001');

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        $this->postJson('/api/register', $this->registrationPayload('reuse-login', 'reuse@example.test', '87010000001'))
            ->assertCreated()
            ->assertJsonPath('user.login', 'reuse-login')
            ->assertJsonPath('user.email', 'reuse@example.test');

        $this->assertDatabaseHas('user_profiles', ['phone' => '87010000001']);
    }

    public function test_active_user_still_blocks_same_login_email_and_phone(): void
    {
        $this->partner('active-duplicate', 'active-duplicate@example.test', '87010000002');

        $this->postJson('/api/register', $this->registrationPayload('active-duplicate', 'new-active@example.test', '87010000003'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('login');

        $this->postJson('/api/register', $this->registrationPayload('new-active-login', 'active-duplicate@example.test', '87010000004'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/register', $this->registrationPayload('new-active-phone', 'new-active-phone@example.test', '87010000002'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_deleted_user_cannot_login_with_original_or_released_login(): void
    {
        $partner = $this->partner('deleted-login', 'deleted-login@example.test', '87010000005');

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        $deleted = User::withTrashed()->findOrFail($partner->id);

        $this->postJson('/api/login', [
            'login' => 'deleted-login',
            'password' => 'password',
        ])->assertUnprocessable();

        $this->postJson('/api/login', [
            'login' => $deleted->login,
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_sponsor_lookup_ignores_deleted_sponsor_and_returns_validation_error(): void
    {
        $sponsor = $this->partner('deleted-sponsor-code', 'deleted-sponsor@example.test', '87010000006');

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$sponsor->id}", $this->deletePayload())->assertOk();

        $this->postJson('/api/register', [
            ...$this->registrationPayload('under-deleted-sponsor', 'under-deleted-sponsor@example.test', '87010000007'),
            'referral_code' => 'deleted-sponsor-code',
            'branch' => 'left',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('referral_code')
            ->assertJsonFragment(['Пригласитель не найден или недоступен']);
    }

    public function test_admin_single_and_bulk_create_can_reuse_identity_from_deleted_user(): void
    {
        $single = $this->partner('admin-reuse-single', 'admin-reuse-single@example.test', '87010000008');
        $bulk = $this->partner('admin-reuse-bulk', 'admin-reuse-bulk@example.test', '87010000009');
        $admin = $this->superAdmin();

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/admin/partners/{$single->id}", $this->deletePayload())->assertOk();
        $this->deleteJson("/api/admin/partners/{$bulk->id}", $this->deletePayload())->assertOk();

        $this->postJson('/api/admin/partners', $this->adminPayload('admin-reuse-single', 'admin-reuse-single@example.test', '87010000008'))
            ->assertCreated()
            ->assertJsonPath('user.login', 'admin-reuse-single');

        $this->postJson('/api/admin/partners/bulk-create', [
            'partners' => [
                $this->adminPayload('admin-reuse-bulk', 'admin-reuse-bulk@example.test', '87010000009'),
            ],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'created')
            ->assertJsonCount(0, 'failed');
    }

    public function test_release_deleted_user_identities_command_updates_existing_deleted_users_only(): void
    {
        $deleted = $this->partner('old-deleted', 'old-deleted@example.test', '87010000010');
        $active = $this->partner('still-active', 'still-active@example.test', '87010000011');

        $deleted->forceFill(['account_status' => 'inactive'])->save();
        $deleted->delete();

        $this->artisan('safi:release-deleted-user-identities')
            ->expectsOutput('found count: 1')
            ->expectsOutput('updated count: 1')
            ->expectsOutput('skipped active count: 0')
            ->assertExitCode(0);

        $released = User::withTrashed()->findOrFail($deleted->id);
        $this->assertStringStartsWith("deleted_{$deleted->id}_", $released->login);
        $this->assertSame('old-deleted', $released->deleted_meta['original_login'] ?? null);
        $this->assertSame('still-active', $active->fresh()->login);
        $this->assertSame('still-active@example.test', $active->fresh()->email);
    }

    public function test_delete_subtree_releases_identities_for_all_deleted_descendants(): void
    {
        $root = $this->partner('identity-root', 'identity-root@example.test', '87010000012');
        $child = $this->partner('identity-child', 'identity-child@example.test', '87010000013');
        $grandchild = $this->partner('identity-grandchild', 'identity-grandchild@example.test', '87010000014');
        $tree = app(BinaryTreeService::class);

        $tree->placeUser($root);
        $tree->placeUser($child, $root, 'L');
        $tree->placeUser($grandchild, $child, 'L');

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$child->id}", $this->deletePayload(['delete_subtree' => true]))
            ->assertOk()
            ->assertJsonPath('deleted_users_count', 2);

        foreach ([$child, $grandchild] as $deletedUser) {
            $deleted = User::withTrashed()->findOrFail($deletedUser->id);

            $this->assertStringStartsWith("deleted_{$deletedUser->id}_", $deleted->login);
            $this->assertSame($deletedUser->login, $deleted->deleted_meta['original_login'] ?? null);
        }
    }

    private function partner(string $login, string $email, string $phone): User
    {
        $user = User::factory()->create([
            'name' => "Partner {$login}",
            'login' => $login,
            'email' => $email,
            'role' => User::ROLE_USER,
            'account_status' => 'active',
        ]);

        $user->profile()->create([
            'first_name' => 'Partner',
            'phone' => $phone,
            'country' => 'Казахстан',
        ]);

        return $user;
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function deletePayload(array $overrides = []): array
    {
        return [
            'delete_subtree' => false,
            'reason' => 'Тестовое удаление identity',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationPayload(string $login, string $email, string $phone): array
    {
        return [
            'name' => "Partner {$login}",
            'login' => $login,
            'email' => $email,
            'phone' => $phone,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPayload(string $login, string $email, string $phone): array
    {
        return [
            ...$this->registrationPayload($login, $email, $phone),
            'role' => User::ROLE_USER,
        ];
    }
}

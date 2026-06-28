<?php

namespace Tests\Feature;

use App\Models\AdminActionLog;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerIdentityEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_partner_basic_identity_fields(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@safilife.test',
            'left_pv' => 300,
            'right_pv' => 500,
            'total_pv' => 800,
        ]);
        UserProfile::query()->create([
            'user_id' => $partner->id,
            'first_name' => 'Old',
            'last_name' => 'Name',
            'phone' => '+77000000010',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/identity", [
            'first_name' => 'New',
            'last_name' => 'Person',
            'phone' => '+77000000011',
            'email' => 'new@safilife.test',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Данные пользователя обновлены.')
            ->assertJsonPath('user.name', 'New Person')
            ->assertJsonPath('user.first_name', 'New')
            ->assertJsonPath('user.last_name', 'Person')
            ->assertJsonPath('user.phone', '+77000000011')
            ->assertJsonPath('user.email', 'new@safilife.test');

        $partner->refresh();

        $this->assertSame('New Person', $partner->name);
        $this->assertSame('new@safilife.test', $partner->email);
        $this->assertNull($partner->current_package_id);
        $this->assertSame('300.00', $partner->left_pv);
        $this->assertSame('500.00', $partner->right_pv);
        $this->assertSame('800.00', $partner->total_pv);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $partner->id,
            'first_name' => 'New',
            'last_name' => 'Person',
            'phone' => '+77000000011',
        ]);

        $auditLog = AdminActionLog::query()
            ->where('admin_id', $admin->id)
            ->where('target_user_id', $partner->id)
            ->where('action', 'user_identity_updated')
            ->firstOrFail();

        $this->assertContains('email', $auditLog->metadata['changed_fields']);
        $this->assertArrayNotHasKey('password', $auditLog->metadata['new']);
    }

    public function test_regular_admin_and_user_cannot_update_partner_identity(): void
    {
        $partner = User::factory()->create();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->patchJson("/api/admin/partners/{$partner->id}/identity", [
            'first_name' => 'Admin',
            'last_name' => 'Blocked',
            'phone' => '+77000000021',
            'email' => 'blocked@safilife.test',
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));

        $this->patchJson("/api/admin/partners/{$partner->id}/identity", [
            'first_name' => 'User',
            'last_name' => 'Blocked',
            'phone' => '+77000000022',
            'email' => 'blocked2@safilife.test',
        ])->assertForbidden();
    }

    public function test_identity_email_and_phone_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create(['email' => 'editable@safilife.test']);
        UserProfile::query()->create([
            'user_id' => $partner->id,
            'phone' => '+77000000030',
        ]);
        $existing = User::factory()->create(['email' => 'taken@safilife.test']);
        UserProfile::query()->create([
            'user_id' => $existing->id,
            'phone' => '+77000000031',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/identity", [
            'first_name' => 'Editable',
            'last_name' => 'Partner',
            'phone' => '+77000000031',
            'email' => 'taken@safilife.test',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone']);
    }
}

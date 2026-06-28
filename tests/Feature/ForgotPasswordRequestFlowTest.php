<?php

namespace Tests\Feature;

use App\Models\AdminActionLog;
use App\Models\ForgotPasswordRequest;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ForgotPasswordRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_check_reports_missing_email_and_accepts_existing_email(): void
    {
        $user = User::factory()->create(['email' => 'partner@safilife.test']);
        UserProfile::query()->create([
            'user_id' => $user->id,
            'phone' => '+77000000001',
        ]);

        $this->postJson('/api/auth/forgot-password/check-email', [
            'email' => 'missing@safilife.test',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Такой email не найден.');

        $this->postJson('/api/auth/forgot-password/check-email', [
            'email' => 'partner@safilife.test',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Email найден.')
            ->assertJsonPath('data.email', 'partner@safilife.test');
    }

    public function test_phone_confirmation_creates_single_pending_admin_request(): void
    {
        $user = User::factory()->create(['email' => 'reset@safilife.test']);
        UserProfile::query()->create([
            'user_id' => $user->id,
            'phone' => '+7 (700) 000-00-02',
        ]);

        $this->postJson('/api/auth/forgot-password/request', [
            'email' => 'reset@safilife.test',
            'phone' => '+77000000003',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone')
            ->assertJsonPath('errors.phone.0', 'Номер телефона не совпадает с указанным email.');

        $this->postJson('/api/auth/forgot-password/request', [
            'email' => 'reset@safilife.test',
            'phone' => '87000000002',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Обращение передано в администрацию.')
            ->assertJsonPath('data.status', ForgotPasswordRequest::STATUS_PENDING);

        $this->postJson('/api/auth/forgot-password/request', [
            'email' => 'reset@safilife.test',
            'phone' => '+77000000002',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Обращение уже передано в администрацию.');

        $this->assertSame(1, ForgotPasswordRequest::query()
            ->where('user_id', $user->id)
            ->where('status', ForgotPasswordRequest::STATUS_PENDING)
            ->count());

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_id' => null,
            'target_user_id' => $user->id,
            'action' => 'forgot_password_request_created',
        ]);
    }

    public function test_admin_can_reset_password_and_pending_request_disappears(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create([
            'email' => 'target@safilife.test',
            'password' => Hash::make('old-password'),
        ]);
        UserProfile::query()->create([
            'user_id' => $user->id,
            'phone' => '+77000000004',
        ]);
        $forgotPasswordRequest = ForgotPasswordRequest::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => '+77000000004',
            'status' => ForgotPasswordRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/forgot-password-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $forgotPasswordRequest->id);

        $response = $this->postJson("/api/admin/forgot-password-requests/{$forgotPasswordRequest->id}/reset-password", [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Новый пароль установлен.')
            ->assertJsonPath('data.status', ForgotPasswordRequest::STATUS_RESOLVED);

        $this->assertStringNotContainsString('new-password', $response->getContent());

        $user->refresh();
        $forgotPasswordRequest->refresh();

        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertSame(ForgotPasswordRequest::STATUS_RESOLVED, $forgotPasswordRequest->status);
        $this->assertSame($admin->id, $forgotPasswordRequest->resolved_by);
        $this->assertNotNull($forgotPasswordRequest->resolved_at);

        $this->getJson('/api/admin/forgot-password-requests')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson('/api/login', [
            'email' => 'target@safilife.test',
            'password' => 'new-password',
        ])->assertOk();

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_id' => $admin->id,
            'target_user_id' => $user->id,
            'action' => 'forgot_password_reset_completed',
        ]);
    }

    public function test_support_can_view_and_reset_forgot_password_requests(): void
    {
        $support = User::factory()->create(['role' => User::ROLE_SUPPORT]);
        $user = User::factory()->create([
            'email' => 'support-reset@safilife.test',
            'password' => Hash::make('old-password'),
        ]);
        UserProfile::query()->create([
            'user_id' => $user->id,
            'phone' => '+77000000005',
        ]);
        $forgotPasswordRequest = ForgotPasswordRequest::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => '+77000000005',
            'status' => ForgotPasswordRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        Sanctum::actingAs($support);

        $this->getJson('/api/admin/forgot-password-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $forgotPasswordRequest->id);

        $this->getJson("/api/admin/forgot-password-requests/{$forgotPasswordRequest->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $forgotPasswordRequest->id);

        $this->postJson("/api/admin/forgot-password-requests/{$forgotPasswordRequest->id}/reset-password", [
            'password' => 'support-password',
            'password_confirmation' => 'support-password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Новый пароль установлен.')
            ->assertJsonPath('data.status', ForgotPasswordRequest::STATUS_RESOLVED);

        $this->assertTrue(Hash::check('support-password', $user->refresh()->password));
        $this->assertSame($support->id, $forgotPasswordRequest->refresh()->resolved_by);
        $this->assertDatabaseHas('admin_action_logs', [
            'admin_id' => $support->id,
            'target_user_id' => $user->id,
            'action' => 'forgot_password_reset_completed',
        ]);
    }

    public function test_support_cannot_access_other_admin_sections_or_binary_recalculation(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPPORT]));

        $this->getJson('/api/admin/partners')->assertForbidden();
        $this->getJson('/api/admin/bonuses')->assertForbidden();
        $this->getJson('/api/admin/transactions')->assertForbidden();
        $this->postJson('/api/admin/bonuses/binary/recalculate')->assertForbidden();
    }

    public function test_guest_and_regular_user_cannot_access_admin_forgot_password_requests(): void
    {
        $this->getJson('/api/admin/forgot-password-requests')
            ->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));

        $this->getJson('/api/admin/forgot-password-requests')
            ->assertForbidden();
    }
}

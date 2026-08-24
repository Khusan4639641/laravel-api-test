<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationPhoneRequiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_requires_phone(): void
    {
        $payload = $this->registerPayload('public-no-phone');
        unset($payload['phone']);

        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_referral_registration_requires_phone(): void
    {
        $sponsor = User::factory()->create(['login' => 'sponsor_ref']);
        $payload = $this->registerPayload('ref-no-phone', [
            'referral_code' => $sponsor->login,
            'branch' => 'left',
        ]);
        unset($payload['phone']);

        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_register_ref_branch_requires_phone(): void
    {
        $sponsor = User::factory()->create(['login' => 'branch_ref']);
        $payload = $this->registerPayload('branch-no-phone', [
            'ref' => $sponsor->login,
            'branch' => 'right',
        ]);
        unset($payload['phone']);

        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_admin_create_partner_requires_phone(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $payload = $this->adminPartnerPayload('admin-no-phone');
        unset($payload['phone']);

        $this->postJson('/api/admin/partners', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_bulk_create_partner_requires_phone_per_row(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $payload = $this->adminPartnerPayload('bulk-no-phone');
        unset($payload['phone']);

        $this->postJson('/api/admin/partners/bulk-create', [
            'partners' => [$payload],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'created')
            ->assertJsonCount(1, 'failed')
            ->assertJsonPath('failed.0.row', 0);

        $this->assertDatabaseMissing('users', ['login' => 'bulk-no-phone']);
    }

    public function test_successful_registration_stores_phone(): void
    {
        $response = $this->postJson('/api/register', $this->registerPayload('phone-stored'))
            ->assertCreated()
            ->assertJsonPath('user.phone', '+77000000001');

        $user = User::query()->where('login', 'phone-stored')->firstOrFail();

        $this->assertSame('+77000000001', $user->profile()->firstOrFail()->phone);

        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.phone', '+77000000001');

        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registerPayload(string $login, array $overrides = []): array
    {
        return [
            'name' => 'Phone Required',
            'login' => $login,
            'email' => "{$login}@safilife.test",
            'phone' => '+77000000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function adminPartnerPayload(string $login, array $overrides = []): array
    {
        return [
            'name' => 'Admin Phone Required',
            'login' => $login,
            'email' => "{$login}@safilife.test",
            'phone' => '+77000000002',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
            ...$overrides,
        ];
    }
}

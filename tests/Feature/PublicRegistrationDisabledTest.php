<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicRegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['safi.public_registration_enabled' => false]);
    }

    public function test_register_page_redirects_to_login(): void
    {
        $this->get('/register')->assertRedirect('/login');
    }

    public function test_register_ref_branch_page_redirects_to_login(): void
    {
        $this->get('/register-ref-branch?ref=test&branch=left')->assertRedirect('/login');
    }

    public function test_public_register_endpoint_returns_forbidden(): void
    {
        $this->postJson('/api/register', $this->registrationPayload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Самостоятельная регистрация временно недоступна');
    }

    public function test_admin_can_still_create_partner(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->postJson('/api/admin/partners', $this->adminPartnerPayload('admin-created-partner'))
            ->assertCreated()
            ->assertJsonPath('user.login', 'admin-created-partner');

        $this->assertDatabaseHas('users', ['login' => 'admin-created-partner']);
    }

    public function test_admin_bulk_create_still_works(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->postJson('/api/admin/partners/bulk-create', [
            'partners' => [
                $this->adminPartnerPayload('bulk-created-one'),
                $this->adminPartnerPayload('bulk-created-two'),
            ],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'created')
            ->assertJsonCount(0, 'failed');

        $this->assertDatabaseHas('users', ['login' => 'bulk-created-one']);
        $this->assertDatabaseHas('users', ['login' => 'bulk-created-two']);
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationPayload(): array
    {
        return [
            'name' => 'Public Disabled',
            'login' => 'public-disabled',
            'email' => 'public-disabled@example.test',
            'phone' => '+77000000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPartnerPayload(string $login): array
    {
        return [
            'name' => "Partner {$login}",
            'login' => $login,
            'email' => "{$login}@example.test",
            'phone' => '+7700'.str_pad((string) (abs(crc32($login)) % 10000000), 7, '0', STR_PAD_LEFT),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_USER,
        ];
    }
}

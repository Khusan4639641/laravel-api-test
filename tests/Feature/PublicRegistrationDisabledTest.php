<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
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

    public function test_register_ref_branch_page_is_served_for_guests(): void
    {
        $sponsor = User::factory()->create(['login' => 'guest-ref-page']);

        $this->get("/register-ref-branch?ref={$sponsor->login}&branch=left")->assertOk();
        $this->get("/register-ref-branch?ref={$sponsor->login}&branch=right")->assertOk();
    }

    public function test_public_register_endpoint_returns_forbidden(): void
    {
        $this->postJson('/api/register', $this->registrationPayload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Самостоятельная регистрация временно недоступна');
    }

    public function test_referral_register_endpoint_is_allowed_for_left_branch(): void
    {
        $sponsor = User::factory()->create(['login' => 'public-disabled-left-sponsor']);

        $this->postJson('/api/register', [
            ...$this->registrationPayload('public-disabled-left'),
            'referral_code' => $sponsor->login,
            'branch' => 'left',
        ])->assertCreated();

        $user = User::query()->where('login', 'public-disabled-left')->firstOrFail();
        $sponsorNode = BinaryNode::query()->where('user_id', $sponsor->id)->firstOrFail();
        $userNode = BinaryNode::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame($sponsor->id, $user->sponsor_id);
        $this->assertSame($sponsorNode->id, $userNode->parent_id);
        $this->assertSame('L', $userNode->position);
    }

    public function test_referral_register_endpoint_is_allowed_for_right_branch(): void
    {
        $sponsor = User::factory()->create(['login' => 'public-disabled-right-sponsor']);

        $this->postJson('/api/register', [
            ...$this->registrationPayload('public-disabled-right'),
            'referral_code' => $sponsor->login,
            'branch' => 'right',
        ])->assertCreated();

        $user = User::query()->where('login', 'public-disabled-right')->firstOrFail();
        $sponsorNode = BinaryNode::query()->where('user_id', $sponsor->id)->firstOrFail();
        $userNode = BinaryNode::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame($sponsor->id, $user->sponsor_id);
        $this->assertSame($sponsorNode->id, $userNode->parent_id);
        $this->assertSame('R', $userNode->position);
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
    private function registrationPayload(string $login = 'public-disabled'): array
    {
        return [
            'name' => 'Public Disabled',
            'login' => $login,
            'email' => "{$login}@example.test",
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

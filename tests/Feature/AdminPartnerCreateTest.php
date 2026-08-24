<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_partner_without_sponsor(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $response = $this->postJson('/api/admin/partners', $this->payload())
            ->assertCreated()
            ->assertJsonPath('user.login', 'newpartner')
            ->assertJsonPath('user.current_package_id', null)
            ->assertJsonPath('credentials.login', 'newpartner')
            ->assertJsonPath('credentials.password', 'secret123');

        $user = User::query()->where('login', 'newpartner')->firstOrFail();

        $this->assertNull($user->sponsor_id);
        $this->assertNull($user->current_package_id);
        $this->assertNull(BinaryNode::query()->where('user_id', $user->id)->first());
        $this->assertSame($user->id, $response->json('user.id'));
    }

    public function test_super_admin_can_create_partner_with_sponsor_and_left_branch(): void
    {
        $sponsor = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->postJson('/api/admin/partners', $this->payload([
            'login' => 'leftpartner',
            'email' => 'leftpartner@example.test',
            'sponsor_id' => $sponsor->id,
            'branch' => 'left',
        ]))->assertCreated();

        $user = User::query()->where('login', 'leftpartner')->firstOrFail();
        $node = $user->binaryNode()->firstOrFail();

        $this->assertSame($sponsor->id, $user->sponsor_id);
        $this->assertSame('L', $node->position);
        $this->assertSame($sponsor->binaryNode()->firstOrFail()->id, $node->parent_id);
    }

    public function test_super_admin_can_create_partner_with_sponsor_and_right_branch(): void
    {
        $sponsor = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->postJson('/api/admin/partners', $this->payload([
            'login' => 'rightpartner',
            'email' => 'rightpartner@example.test',
            'sponsor_id' => $sponsor->id,
            'branch' => 'right',
        ]))->assertCreated();

        $node = User::query()->where('login', 'rightpartner')->firstOrFail()->binaryNode()->firstOrFail();

        $this->assertSame('R', $node->position);
    }

    public function test_created_partner_receives_hashed_password_and_can_login(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $credentials = $this->postJson('/api/admin/partners', $this->payload())
            ->assertCreated()
            ->json('credentials');

        $user = User::query()->where('login', $credentials['login'])->firstOrFail();

        $this->assertNotSame($credentials['password'], $user->password);
        $this->assertTrue(Hash::check($credentials['password'], $user->password));

        $this->postJson('/api/login', [
            'login' => $credentials['login'],
            'password' => $credentials['password'],
        ])->assertOk()->assertJsonPath('user.login', $credentials['login']);
    }

    public function test_response_returns_credentials_only_on_create_response(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($admin);

        $userId = $this->postJson('/api/admin/partners', $this->payload())
            ->assertCreated()
            ->assertJsonPath('credentials.password', 'secret123')
            ->json('user.id');

        $showResponse = $this->getJson("/api/admin/users/{$userId}")
            ->assertOk();

        $this->assertNull($showResponse->json('credentials'));
        $this->assertNull($showResponse->json('user.password'));
    }

    public function test_normal_user_and_support_cannot_create_partner(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->postJson('/api/admin/partners', $this->payload())->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'support']));
        $this->postJson('/api/admin/partners', $this->payload([
            'login' => 'supportattempt',
            'email' => 'supportattempt@example.test',
        ]))->assertForbidden();
    }

    public function test_binary_node_is_created_if_sponsor_is_provided(): void
    {
        $sponsor = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->postJson('/api/admin/partners', $this->payload([
            'login' => 'binarypartner',
            'email' => 'binarypartner@example.test',
            'sponsor_id' => $sponsor->id,
            'branch' => 'L',
        ]))->assertCreated();

        $user = User::query()->where('login', 'binarypartner')->firstOrFail();

        $this->assertDatabaseHas('binary_nodes', [
            'user_id' => $user->id,
            'position' => 'L',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Partner',
            'login' => 'newpartner',
            'email' => 'newpartner@example.test',
            'phone' => '+7 700 000 00 00',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'user',
        ], $overrides);
    }
}

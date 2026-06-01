<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerBulkCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_bulk_create_partners(): void
    {
        $sponsor = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $response = $this->postJson('/api/admin/partners/bulk-create', [
            'partners' => [
                $this->payload('bulkone', ['sponsor_id' => $sponsor->id, 'branch' => 'left']),
                $this->payload('bulktwo'),
            ],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'created')
            ->assertJsonCount(0, 'failed');

        foreach ($response->json('created') as $created) {
            $credentials = $created['credentials'];
            $user = User::query()->where('login', $credentials['login'])->firstOrFail();

            $this->assertNotSame($credentials['password'], $user->password);
            $this->assertTrue(Hash::check($credentials['password'], $user->password));

            $this->postJson('/api/login', [
                'login' => $credentials['login'],
                'password' => $credentials['password'],
            ])->assertOk();
        }

        $linkedUser = User::query()->where('login', 'bulkone')->firstOrFail();
        $this->assertSame($sponsor->id, $linkedUser->sponsor_id);
        $this->assertDatabaseHas('binary_nodes', [
            'user_id' => $linkedUser->id,
            'position' => 'L',
        ]);
    }

    public function test_invalid_bulk_row_returns_row_error_and_valid_rows_are_created(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->postJson('/api/admin/partners/bulk-create', [
            'partners' => [
                $this->payload('validbulk'),
                $this->payload('invalidbulk', ['email' => 'not-email']),
            ],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'created')
            ->assertJsonCount(1, 'failed')
            ->assertJsonPath('created.0.credentials.login', 'validbulk')
            ->assertJsonPath('failed.0.row', 1);

        $this->assertDatabaseHas('users', ['login' => 'validbulk']);
        $this->assertDatabaseMissing('users', ['login' => 'invalidbulk']);
    }

    public function test_normal_user_and_support_cannot_bulk_create(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->postJson('/api/admin/partners/bulk-create', [
            'partners' => [$this->payload('userbulk')],
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'support']));
        $this->postJson('/api/admin/partners/bulk-create', [
            'partners' => [$this->payload('supportbulk')],
        ])->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $login, array $overrides = []): array
    {
        return array_merge([
            'name' => "Partner {$login}",
            'login' => $login,
            'email' => "{$login}@example.test",
            'phone' => '+7 700 000 00 00',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
        ], $overrides);
    }
}

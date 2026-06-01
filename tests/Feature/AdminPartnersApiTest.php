<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_partners_list_returns_invited_count(): void
    {
        $partner = User::factory()->create(['role' => 'user']);
        User::factory()->create(['role' => 'user', 'sponsor_id' => $partner->id]);
        User::factory()->create(['role' => 'user', 'sponsor_id' => $partner->id]);

        $payload = $this->adminPayloadFor($partner);

        $this->assertSame(2, $payload['invited_count']);
        $this->assertSame(2, $payload['referrals_count']);
    }

    public function test_admin_partners_list_returns_pv_fields(): void
    {
        $partner = User::factory()->create([
            'role' => 'user',
            'left_pv' => 9600,
            'right_pv' => 8700,
            'total_pv' => 3200,
        ]);

        $payload = $this->adminPayloadFor($partner);

        $this->assertEquals(9600, (float) $payload['left_pv']);
        $this->assertEquals(8700, (float) $payload['right_pv']);
        $this->assertEquals(3200, (float) $payload['total_pv']);
    }

    public function test_admin_partners_list_returns_wallet_balances(): void
    {
        $partner = User::factory()->create(['role' => 'user']);
        $this->createWallet($partner, 'main', 12000);
        $this->createWallet($partner, 'bonus', 900);
        $this->createWallet($partner, 'deposit', 900);

        $payload = $this->adminPayloadFor($partner);

        $this->assertEquals(12000, $payload['balance']);
        $this->assertEquals(900, $payload['bonus_balance']);
        $this->assertEquals(900, $payload['deposit_balance']);
        $this->assertEquals(13800, $payload['total_balance']);
    }

    public function test_admin_partners_list_includes_sponsor_and_package_data(): void
    {
        $sponsor = User::factory()->create([
            'role' => 'user',
            'name' => 'Айдар Алиханов',
            'login' => 'aidar',
        ]);
        $package = Package::query()->create([
            'code' => 'START',
            'name' => 'START',
            'slug' => 'start',
            'price' => 1000,
            'pv' => 100,
            'status' => 'active',
            'is_active' => true,
        ]);
        $partner = User::factory()->create([
            'role' => 'user',
            'sponsor_id' => $sponsor->id,
            'current_package_id' => $package->id,
        ]);

        $payload = $this->adminPayloadFor($partner);

        $this->assertSame($sponsor->id, $payload['sponsor']['id']);
        $this->assertSame('Айдар Алиханов', $payload['sponsor']['name']);
        $this->assertSame('aidar', $payload['sponsor']['login']);
        $this->assertSame($package->id, $payload['package']['id']);
        $this->assertSame('START', $payload['package']['name']);
    }

    public function test_admin_partners_list_handles_missing_sponsor_wallet_and_package(): void
    {
        $partner = User::factory()->create([
            'role' => 'user',
            'sponsor_id' => null,
            'current_package_id' => null,
        ]);

        $payload = $this->adminPayloadFor($partner);

        $this->assertNull($payload['sponsor']);
        $this->assertNull($payload['package']);
        $this->assertEquals(0, $payload['balance']);
        $this->assertEquals(0, $payload['bonus_balance']);
        $this->assertEquals(0, $payload['deposit_balance']);
        $this->assertEquals(0, $payload['total_balance']);
    }

    public function test_normal_user_cannot_access_admin_partners_list(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_support_cannot_access_admin_partners_list(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'support']));

        $this->getJson('/api/admin/users')->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPayloadFor(User $partner): array
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $users = $this->getJson('/api/admin/users?per_page=100')
            ->assertOk()
            ->json('users');

        $payload = collect($users)->firstWhere('id', $partner->id);

        $this->assertIsArray($payload);

        return $payload;
    }

    private function createWallet(User $user, string $type, int $balance): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'currency' => 'KZT',
            'balance' => $balance,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerPackageActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_package_activity_flags_follow_active_package(): void
    {
        $withoutPackage = User::factory()->create([
            'account_status' => 'active',
            'current_package_id' => null,
        ]);
        $inactivePackage = $this->package('BUSINESS', false, 'inactive');
        $withInactivePackage = User::factory()->create([
            'account_status' => 'active',
            'current_package_id' => $inactivePackage->id,
        ]);

        $this->assertFalse($withoutPackage->isPartnerActive());
        $this->assertSame('inactive', $withoutPackage->packageStatus());
        $this->assertFalse($withInactivePackage->isPartnerActive());

        foreach (['START', 'VIP', 'ELITE'] as $code) {
            $package = $this->package($code);
            $partner = User::factory()->create([
                'account_status' => 'active',
                'current_package_id' => $package->id,
            ]);

            $this->assertTrue($partner->isPartnerActive(), "{$code} must activate partner status.");
            $this->assertSame('active', $partner->packageStatus());
        }
    }

    public function test_public_registration_package_choice_does_not_activate_package_without_payment(): void
    {
        $start = $this->package('START');

        $this->postJson('/api/register', $this->registrationPayload('pending-package-choice', [
            'package_id' => $start->id,
        ]))
            ->assertCreated()
            ->assertJsonPath('user.is_partner_active', false)
            ->assertJsonPath('user.package_status', 'inactive')
            ->assertJsonPath('user.package_code', null);

        $user = User::query()->where('login', 'pending-package-choice')->firstOrFail();

        $this->assertNull($user->current_package_id);
        $this->assertFalse($user->isPartnerActive());
        $this->assertSame('0.00', $user->total_pv);
        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'referral')->count());
    }

    public function test_dashboard_profile_api_returns_inactive_for_user_without_package(): void
    {
        $user = User::factory()->create([
            'account_status' => 'active',
            'current_package_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/profile')
            ->assertOk()
            ->assertJsonPath('user.is_partner_active', false)
            ->assertJsonPath('user.package_status', 'inactive')
            ->assertJsonPath('user.package_code', null)
            ->assertJsonPath('user.package_name', null)
            ->assertJsonPath('user.can_invite', false);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.is_partner_active', false)
            ->assertJsonPath('user.package_status', 'inactive')
            ->assertJsonPath('user.can_invite', false);
    }

    public function test_admin_partners_api_returns_inactive_for_user_without_package(): void
    {
        $partner = User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => null,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $row = collect($this->getJson('/api/admin/partners?per_page=100')->assertOk()->json('data'))
            ->firstWhere('id', $partner->id);

        $this->assertIsArray($row);
        $this->assertFalse($row['is_partner_active']);
        $this->assertSame('inactive', $row['package_status']);
        $this->assertNull($row['package_code']);
        $this->assertNull($row['package_name']);
    }

    public function test_admin_structure_api_returns_inactive_package_status_for_root_without_package(): void
    {
        $partner = User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => null,
        ]);
        $this->node($partner);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson("/api/admin/structure?root_id={$partner->id}")
            ->assertOk()
            ->assertJsonPath('root.is_partner_active', false)
            ->assertJsonPath('root.package_status', 'inactive')
            ->assertJsonPath('root.package_code', null)
            ->assertJsonPath('root.package_label', '-');
    }

    public function test_dashboard_structure_api_marks_descendant_without_package_inactive(): void
    {
        $start = $this->package('START');
        $root = User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => $start->id,
        ]);
        $child = User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => null,
        ]);
        $rootNode = $this->node($root);
        $this->node($child, $rootNode, 'L');

        Sanctum::actingAs($root);

        $this->getJson('/api/dashboard/structure?per_page=20')
            ->assertOk()
            ->assertJsonPath('partners.data.0.id', $child->id)
            ->assertJsonPath('partners.data.0.is_partner_active', false)
            ->assertJsonPath('partners.data.0.package_status', 'inactive')
            ->assertJsonPath('partners.data.0.package_code', null)
            ->assertJsonPath('partners.data.0.package_name', null)
            ->assertJsonPath('partners.data.0.package_label', '-')
            ->assertJsonPath('tree.children.left.is_partner_active', false);
    }

    public function test_referral_registration_rejects_referrer_without_active_package(): void
    {
        $sponsor = User::factory()->create([
            'login' => 'inactive-referrer',
            'account_status' => 'active',
            'current_package_id' => null,
        ]);

        $this->postJson('/api/register', $this->registrationPayload('blocked-referral-package', [
            'referral_code' => $sponsor->login,
            'branch' => 'left',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('referral_code');
    }

    private function package(string $code, bool $isActive = true, string $status = 'active'): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code).'-'.uniqid(),
            'price' => match ($code) {
                'VIP' => 180000,
                'ELITE' => 300000,
                default => 60000,
            },
            'pv' => match ($code) {
                'VIP' => 300,
                'ELITE' => 500,
                default => 100,
            },
            'activity_pv' => match ($code) {
                'VIP' => 300,
                'ELITE' => 500,
                default => 100,
            },
            'turnover_pv' => match ($code) {
                'ELITE' => 200,
                'VIP' => 300,
                default => 100,
            },
            'referral_percent' => 10,
            'binary_percent' => 7,
            'sort_order' => match ($code) {
                'VIP' => 2,
                'ELITE' => 3,
                default => 1,
            },
            'status' => $status,
            'is_active' => $isActive,
            'is_upgradeable' => true,
        ]);
    }

    private function node(User $user, ?BinaryNode $parent = null, ?string $position = null): BinaryNode
    {
        return BinaryNode::query()->create([
            'user_id' => $user->id,
            'parent_id' => $parent?->id,
            'position' => $position,
            'depth' => $parent ? $parent->depth + 1 : 0,
            'path' => $parent ? $parent->path.'.'.$user->id : (string) $user->id,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(string $login, array $overrides = []): array
    {
        return [
            'name' => "Partner {$login}",
            'login' => $login,
            'email' => "{$login}@safilife.test",
            'phone' => '+7700'.str_pad((string) (abs(crc32($login)) % 10000000), 7, '0', STR_PAD_LEFT),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$overrides,
        ];
    }
}

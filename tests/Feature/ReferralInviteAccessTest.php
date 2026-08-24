<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralInviteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_package_does_not_receive_referral_links_in_dashboard_api(): void
    {
        $user = User::factory()->create([
            'login' => 'no-package-invite',
            'current_package_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('can_invite', false)
            ->assertJsonPath('user.can_invite', false)
            ->assertJsonPath('referral_links.left', '')
            ->assertJsonPath('referral_links.right', '');

        $this->getJson('/api/dashboard/structure')
            ->assertOk()
            ->assertJsonPath('can_invite', false)
            ->assertJsonPath('structure.can_invite', false)
            ->assertJsonPath('referral_links.left', '')
            ->assertJsonPath('referral_links.right', '');
    }

    public function test_register_by_referral_code_of_user_without_package_returns_validation_error(): void
    {
        $sponsor = User::factory()->create([
            'login' => 'sponsor-without-package',
            'current_package_id' => null,
        ]);

        $this->postJson('/api/register', $this->registrationPayload('blocked-no-package', [
            'referral_code' => $sponsor->login,
            'branch' => 'left',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('referral_code')
            ->assertJsonPath('errors.referral_code.0', 'У пригласителя нет активного пакета. Регистрация по этой ссылке недоступна.');
    }

    public function test_register_by_referral_code_of_user_with_inactive_package_returns_validation_error(): void
    {
        $package = $this->package('START', false, 'inactive');
        $sponsor = User::factory()->create([
            'login' => 'sponsor-inactive-package',
            'current_package_id' => $package->id,
        ]);

        $this->postJson('/api/register', $this->registrationPayload('blocked-inactive-package', [
            'referral_code' => $sponsor->login,
            'branch' => 'right',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('referral_code')
            ->assertJsonPath('errors.referral_code.0', 'У пригласителя нет активного пакета. Регистрация по этой ссылке недоступна.');
    }

    public function test_only_start_vip_and_elite_packages_can_invite(): void
    {
        foreach (['START', 'VIP', 'ELITE'] as $code) {
            $package = $this->package($code);
            $sponsor = User::factory()->create([
                'login' => 'sponsor-'.strtolower($code),
                'current_package_id' => $package->id,
            ]);

            $this->postJson('/api/register', $this->registrationPayload('invited-'.strtolower($code), [
                'referral_code' => $sponsor->login,
                'branch' => 'left',
            ]))->assertCreated();

            $this->assertDatabaseHas('users', [
                'login' => 'invited-'.strtolower($code),
                'sponsor_id' => $sponsor->id,
            ]);
        }
    }

    public function test_active_non_invitation_package_cannot_invite(): void
    {
        $package = $this->package('BUSINESS');
        $sponsor = User::factory()->create([
            'login' => 'sponsor-business',
            'current_package_id' => $package->id,
        ]);

        Sanctum::actingAs($sponsor);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('can_invite', false)
            ->assertJsonPath('referral_links.left', '')
            ->assertJsonPath('referral_links.right', '');

        $this->postJson('/api/register', $this->registrationPayload('blocked-business', [
            'referral_code' => $sponsor->login,
            'branch' => 'left',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('referral_code');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(string $login, array $overrides = []): array
    {
        return [
            'name' => "Referral {$login}",
            'login' => $login,
            'email' => "{$login}@example.test",
            'phone' => '+7700'.str_pad((string) (abs(crc32($login)) % 10000000), 7, '0', STR_PAD_LEFT),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$overrides,
        ];
    }

    private function package(string $code, bool $isActive = true, string $status = 'active'): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code).'-'.uniqid(),
            'price' => 60000,
            'pv' => 100,
            'activity_pv' => 100,
            'turnover_pv' => 100,
            'referral_percent' => 10,
            'binary_percent' => 7,
            'sort_order' => 1,
            'status' => $status,
            'is_active' => $isActive,
            'is_upgradeable' => true,
        ]);
    }
}

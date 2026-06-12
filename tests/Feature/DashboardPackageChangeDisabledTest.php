<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardPackageChangeDisabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['safi.user_package_changes_enabled' => false]);
    }

    public function test_regular_user_cannot_activate_package_through_api(): void
    {
        $package = $this->package('START');
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertForbidden()
            ->assertJsonPath('message', 'Покупка пакетов пользователем временно недоступна. Обратитесь к администратору.');
    }

    public function test_regular_user_cannot_upgrade_package_through_api(): void
    {
        $start = $this->package('START');
        $vip = $this->package('VIP');
        $user = User::factory()->create(['current_package_id' => $start->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$vip->id}/upgrade")
            ->assertForbidden()
            ->assertJsonPath('message', 'Покупка пакетов пользователем временно недоступна. Обратитесь к администратору.');
    }

    public function test_super_admin_can_still_change_partner_package_through_admin_endpoint(): void
    {
        $partner = User::factory()->create();
        $package = $this->package('VIP');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $package->id,
            'apply_business_effects' => false,
        ])
            ->assertOk()
            ->assertJsonPath('user.current_package_id', $package->id);

        $this->assertDatabaseHas('users', [
            'id' => $partner->id,
            'current_package_id' => $package->id,
        ]);
    }

    public function test_dashboard_package_page_uses_informational_package_buttons(): void
    {
        $source = file_get_contents(resource_path('js/safi/pages/dashboard/PackageStatus.tsx'));

        $this->assertStringContainsString('Ваш текущий пакет', $source);
        $this->assertStringContainsString('Уже приобрели', $source);
        $this->assertStringContainsString('Вы еще не приобрели', $source);
        $this->assertStringContainsString('Пакет назначается администратором', $source);
        $this->assertStringNotContainsString('handlePackagePayment', $source);
        $this->assertStringNotContainsString('createTipTopPayPackagePaymentIntent', $source);
        $this->assertStringNotContainsString('handlePackageAction', $source);
        $this->assertStringNotContainsString('activatePackage', $source);
        $this->assertStringNotContainsString('upgradePackage', $source);
    }

    private function package(string $code): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
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
            'binary_percent' => match ($code) {
                'ELITE' => 10,
                'VIP' => 8,
                default => 7,
            },
            'sort_order' => match ($code) {
                'ELITE' => 3,
                'VIP' => 2,
                default => 1,
            },
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}

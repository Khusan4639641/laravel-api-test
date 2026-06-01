<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_permissions_endpoint_returns_menu(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/me/permissions')
            ->assertOk()
            ->assertJsonStructure([
                'role',
                'redirect_after_login',
                'allowed_routes',
                'menu' => [
                    '*' => ['path', 'label', 'icon'],
                ],
            ])
            ->assertJsonPath('menu.0.path', '/dashboard');
    }

    public function test_menu_changes_by_role(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'support']));
        $supportMenu = $this->getJson('/api/me/permissions')->assertOk()->json('menu');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $adminMenu = $this->getJson('/api/me/permissions')->assertOk()->json('menu');

        Sanctum::actingAs(User::factory()->create(['role' => 'accountant']));
        $accountantMenu = $this->getJson('/api/me/permissions')->assertOk()->json('menu');

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));
        $superAdminMenu = $this->getJson('/api/me/permissions')->assertOk()->json('menu');

        $this->assertContains('/support', array_column($supportMenu, 'path'));
        $this->assertNotContains('/admin/settings', array_column($supportMenu, 'path'));
        $this->assertContains('/admin/products', array_column($adminMenu, 'path'));
        $this->assertNotContains('/admin/settings', array_column($adminMenu, 'path'));
        $this->assertSame(['/admin', '/admin/transactions', '/admin/withdrawals', '/admin/reports'], array_column($accountantMenu, 'path'));
        $this->assertContains('/admin/settings', array_column($superAdminMenu, 'path'));
    }
}

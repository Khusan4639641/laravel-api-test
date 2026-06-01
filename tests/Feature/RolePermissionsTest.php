<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_permissions_contain_only_dashboard_routes(): void
    {
        $permissions = $this->permissionsFor('user');

        $this->assertSame('user', $permissions['role']);
        $this->assertSame('/dashboard', $permissions['redirect_after_login']);
        $this->assertContains('/dashboard', $permissions['allowed_routes']);
        $this->assertContains('/dashboard/support', $permissions['allowed_routes']);
        $this->assertNotContains('/admin', $permissions['allowed_routes']);
        $this->assertNotContains('/support', $permissions['allowed_routes']);
    }

    public function test_support_permissions_contain_support_routes_only(): void
    {
        $permissions = $this->permissionsFor('support');

        $this->assertSame('support', $permissions['role']);
        $this->assertSame('/support', $permissions['redirect_after_login']);
        $this->assertContains('/support', $permissions['allowed_routes']);
        $this->assertNotContains('/admin/products', $permissions['allowed_routes']);
        $this->assertNotContains('/dashboard', $permissions['allowed_routes']);
    }

    public function test_admin_permissions_contain_allowed_admin_routes(): void
    {
        $permissions = $this->permissionsFor('admin');

        $this->assertSame('admin', $permissions['role']);
        $this->assertSame('/admin', $permissions['redirect_after_login']);
        $this->assertContains('/admin/partners', $permissions['allowed_routes']);
        $this->assertContains('/admin/products', $permissions['allowed_routes']);
        $this->assertNotContains('/admin/settings', $permissions['allowed_routes']);
        $this->assertNotContains('/admin/reports', $permissions['allowed_routes']);
    }

    public function test_accountant_permissions_contain_only_accounting_admin_routes(): void
    {
        $permissions = $this->permissionsFor('accountant');
        $expectedRoutes = [
            '/admin',
            '/admin/transactions',
            '/admin/withdrawals',
            '/admin/reports',
        ];

        $this->assertSame('accountant', $permissions['role']);
        $this->assertSame('Бухгалтер', $permissions['label']);
        $this->assertSame('/admin', $permissions['redirect_after_login']);
        $this->assertSame($expectedRoutes, $permissions['allowed_routes']);
        $this->assertSame($expectedRoutes, array_column($permissions['menu'], 'path'));
        $this->assertNotContains('/admin/partners', $permissions['allowed_routes']);
        $this->assertNotContains('/admin/products', $permissions['allowed_routes']);
        $this->assertNotContains('/admin/news', $permissions['allowed_routes']);
        $this->assertNotContains('/admin/settings', $permissions['allowed_routes']);
        $this->assertNotContains('/admin/support', $permissions['allowed_routes']);
    }

    public function test_super_admin_permissions_contain_all_admin_routes(): void
    {
        $permissions = $this->permissionsFor('super_admin');

        $this->assertSame('super_admin', $permissions['role']);
        $this->assertContains('/admin/settings', $permissions['allowed_routes']);
        $this->assertContains('/admin/reports', $permissions['allowed_routes']);
        $this->assertContains('/admin/products', $permissions['allowed_routes']);
    }

    /**
     * @return array<string, mixed>
     */
    private function permissionsFor(string $role): array
    {
        Sanctum::actingAs(User::factory()->create(['role' => $role]));

        return $this->getJson('/api/me/permissions')
            ->assertOk()
            ->assertJsonPath('role', $role)
            ->json();
    }
}

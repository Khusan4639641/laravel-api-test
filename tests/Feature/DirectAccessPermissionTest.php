<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DirectAccessPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_admin_api_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/admin/products')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_support_cannot_access_super_admin_product_crud(): void
    {
        $product = Product::query()->create([
            'name' => 'Protected Product',
            'sku' => 'PROTECTED-001',
            'price' => 100,
            'pv' => 10,
            'status' => 'active',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'support']));

        $this->getJson('/api/admin/products')->assertForbidden();
        $this->postJson('/api/admin/products', [])->assertForbidden();
        $this->putJson("/api/admin/products/{$product->id}", [])->assertForbidden();
        $this->deleteJson("/api/admin/products/{$product->id}")->assertForbidden();
    }

    public function test_admin_can_access_allowed_admin_routes_but_not_settings_or_writes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/users')->assertOk();
        $this->getJson('/api/admin/products')->assertOk();
        $this->getJson('/api/admin/transactions')->assertOk();
        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->postJson('/api/admin/products', [])->assertForbidden();
    }

    public function test_super_admin_can_access_settings_reports_and_product_crud(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson('/api/admin/settings')->assertOk();
        $this->getJson('/api/admin/reports/summary')->assertOk();
        $this->postJson('/api/admin/products', [
            'name' => 'Super Product',
            'sku' => 'SUPER-001',
            'price' => 100,
            'status' => 'active',
        ])->assertCreated();
    }

    public function test_user_cannot_access_another_role_menu(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $permissions = $this->getJson('/api/me/permissions')->assertOk()->json();

        $this->assertSame('user', $permissions['role']);
        $this->assertNotContains('/admin/products', $permissions['allowed_routes']);
        $this->assertNotContains('/support', $permissions['allowed_routes']);
    }
}

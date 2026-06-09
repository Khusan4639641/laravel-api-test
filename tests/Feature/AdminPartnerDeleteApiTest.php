<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BinaryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerDeleteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_delete(): void
    {
        $partner = $this->partner('api-delete-partner');

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())
            ->assertForbidden();

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())
            ->assertOk()
            ->assertJsonPath('deleted_users_count', 1);
    }

    public function test_cannot_delete_self(): void
    {
        $admin = $this->superAdmin();
        $this->superAdmin('second-super-admin');

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/admin/partners/{$admin->id}", $this->deletePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');
    }

    public function test_cannot_delete_last_super_admin(): void
    {
        $admin = $this->superAdmin();

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/admin/partners/{$admin->id}", $this->deletePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');
    }

    public function test_cannot_delete_partner_with_children_without_delete_subtree_true(): void
    {
        [$root, $child] = [$this->partner('api-delete-root'), $this->partner('api-delete-child')];
        app(BinaryTreeService::class)->placeUser($root);
        app(BinaryTreeService::class)->placeUser($child, $root, 'L');

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$root->id}", $this->deletePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delete_subtree');
    }

    public function test_deleting_leaf_succeeds(): void
    {
        $partner = $this->partner('api-delete-leaf');

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())
            ->assertOk()
            ->assertJsonPath('deleted_users_count', 1);

        $this->assertSoftDeleted('users', ['id' => $partner->id]);
    }

    public function test_deleting_subtree_succeeds_and_recalculates_uplines(): void
    {
        [$root, $child, $grandchild] = [
            $this->partner('api-subtree-root'),
            $this->partner('api-subtree-child'),
            $this->partner('api-subtree-grandchild'),
        ];
        $tree = app(BinaryTreeService::class);
        $tree->placeUser($root);
        $tree->placeUser($child, $root, 'L');
        $tree->placeUser($grandchild, $child, 'L');

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$child->id}", $this->deletePayload(['delete_subtree' => true]))
            ->assertOk()
            ->assertJsonPath('deleted_users_count', 2);

        $this->assertSoftDeleted('users', ['id' => $child->id]);
        $this->assertSoftDeleted('users', ['id' => $grandchild->id]);
        $this->assertSame('0.00', (string) $root->refresh()->left_pv);
    }

    private function partner(string $login): User
    {
        return User::factory()->create([
            'login' => $login,
            'email' => "{$login}@example.test",
            'role' => User::ROLE_USER,
            'account_status' => 'active',
        ]);
    }

    private function superAdmin(string $login = 'super-admin'): User
    {
        return User::factory()->create([
            'login' => $login,
            'email' => "{$login}@example.test",
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function deletePayload(array $overrides = []): array
    {
        return [
            'delete_subtree' => false,
            'reason' => 'Тестовое удаление api',
            ...$overrides,
        ];
    }
}

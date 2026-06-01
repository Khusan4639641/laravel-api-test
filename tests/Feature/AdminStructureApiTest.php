<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStructureApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_get_structure_for_selected_user(): void
    {
        [$root, $left, $right] = $this->createBinaryTree();

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson("/api/admin/structure?user_id={$left->id}")
            ->assertOk()
            ->assertJsonPath('root_user_id', $left->id)
            ->assertJsonPath('root.id', $left->id)
            ->assertJsonPath('root.login', $left->login)
            ->assertJsonPath('root.children.left.id', $right->id);
    }

    public function test_structure_endpoint_does_not_always_return_current_admin_user(): void
    {
        [, $partner] = $this->createBinaryTree();
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/structure?user_id={$partner->id}")
            ->assertOk()
            ->assertJsonPath('root.id', $partner->id);

        $this->assertNotSame($admin->id, $partner->id);
    }

    public function test_different_user_id_returns_different_root(): void
    {
        [$root, $left] = $this->createBinaryTree();

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $rootResponse = $this->getJson("/api/admin/structure?user_id={$root->id}")
            ->assertOk();
        $leftResponse = $this->getJson("/api/admin/structure?user_id={$left->id}")
            ->assertOk();

        $this->assertNotSame($rootResponse->json('root.id'), $leftResponse->json('root.id'));
    }

    public function test_structure_endpoint_returns_404_for_missing_user_id(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson('/api/admin/structure?user_id=999999')
            ->assertNotFound();
    }

    public function test_user_cannot_access_admin_structure_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/admin/structure')
            ->assertForbidden();
    }

    public function test_support_cannot_access_admin_structure_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'support']));

        $this->getJson('/api/admin/structure')
            ->assertForbidden();
    }

    public function test_selected_user_without_binary_node_returns_empty_tree_but_valid_root_user(): void
    {
        $partner = User::factory()->create([
            'name' => 'No Node Partner',
            'login' => 'nonode',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson("/api/admin/structure?user_id={$partner->id}")
            ->assertOk()
            ->assertJsonPath('root_user_id', $partner->id)
            ->assertJsonPath('root.id', $partner->id)
            ->assertJsonPath('root.name', 'No Node Partner')
            ->assertJsonPath('root.children.left', null)
            ->assertJsonPath('root.children.right', null);
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function createBinaryTree(): array
    {
        $root = User::factory()->create([
            'name' => 'Root Partner',
            'login' => 'rootpartner',
        ]);
        $left = User::factory()->create([
            'name' => 'Left Partner',
            'login' => 'leftpartner',
        ]);
        $right = User::factory()->create([
            'name' => 'Right Under Left',
            'login' => 'rightunderleft',
        ]);

        $rootNode = BinaryNode::query()->create([
            'user_id' => $root->id,
            'parent_id' => null,
            'position' => null,
            'depth' => 0,
            'path' => (string) $root->id,
        ]);
        $leftNode = BinaryNode::query()->create([
            'user_id' => $left->id,
            'parent_id' => $rootNode->id,
            'position' => 'L',
            'depth' => 1,
            'path' => $rootNode->path.'.'.$left->id,
        ]);
        BinaryNode::query()->create([
            'user_id' => $right->id,
            'parent_id' => $leftNode->id,
            'position' => 'L',
            'depth' => 2,
            'path' => $leftNode->path.'.'.$right->id,
        ]);

        return [$root, $left, $right];
    }
}

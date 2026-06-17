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

    public function test_admin_structure_returns_recursive_children(): void
    {
        [$root, $left, $right, $leftGrandchild] = $this->createNestedBinaryTree();

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson("/api/admin/structure?user_id={$root->id}")
            ->assertOk()
            ->assertJsonPath('root_user_id', $root->id)
            ->assertJsonPath('root.id', $root->id)
            ->assertJsonPath('root.children.left.id', $left->id)
            ->assertJsonPath('root.children.right.id', $right->id)
            ->assertJsonPath('root.children.left.children.left.id', $leftGrandchild->id);
    }

    public function test_admin_structure_returns_selected_user_as_root(): void
    {
        [, $left] = $this->createNestedBinaryTree();
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/structure?user_id={$left->id}")
            ->assertOk()
            ->assertJsonPath('root.id', $left->id);

        $this->assertNotSame($admin->id, $left->id);
    }

    public function test_different_user_id_returns_different_root(): void
    {
        [$root, $left] = $this->createNestedBinaryTree();

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $rootResponse = $this->getJson("/api/admin/structure?user_id={$root->id}")
            ->assertOk();
        $leftResponse = $this->getJson("/api/admin/structure?user_id={$left->id}")
            ->assertOk();

        $this->assertNotSame($rootResponse->json('root.id'), $leftResponse->json('root.id'));
    }

    public function test_admin_structure_accepts_root_id_alias(): void
    {
        [, $left] = $this->createNestedBinaryTree();

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson("/api/admin/structure?root_id={$left->id}")
            ->assertOk()
            ->assertJsonPath('root_user_id', $left->id)
            ->assertJsonPath('root.id', $left->id);
    }

    public function test_admin_structure_includes_downline_and_branch_counts(): void
    {
        [$root, , , , , , , $rightRight] = $this->createSevenDescendantTree();

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson("/api/admin/structure?user_id={$root->id}&include_flat=true")
            ->assertOk()
            ->assertJsonPath('stats.total_downline_count', 7)
            ->assertJsonPath('stats.left_branch_count', 4)
            ->assertJsonPath('stats.right_branch_count', 3)
            ->assertJsonCount(7, 'flat')
            ->assertJsonFragment([
                'id' => $rightRight->id,
                'login' => 'rightright',
            ]);
    }

    public function test_admin_structure_includes_direct_invited_count(): void
    {
        [$root] = $this->createNestedBinaryTree();
        User::factory()->create(['sponsor_id' => $root->id]);
        User::factory()->create(['sponsor_id' => $root->id]);
        User::factory()->create(['sponsor_id' => $root->id]);

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson("/api/admin/structure?user_id={$root->id}")
            ->assertOk()
            ->assertJsonPath('stats.direct_invited_count', 3);
    }

    public function test_admin_structure_respects_depth(): void
    {
        [$root, $left, , $leftGrandchild, $greatGrandchild] = $this->createNestedBinaryTree();

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson("/api/admin/structure?user_id={$root->id}&depth=1")
            ->assertOk()
            ->assertJsonPath('root.children.left.id', $left->id)
            ->assertJsonPath('root.children.left.children.left', null);

        $this->getJson("/api/admin/structure?user_id={$root->id}&depth=3")
            ->assertOk()
            ->assertJsonPath('root.children.left.children.left.id', $leftGrandchild->id)
            ->assertJsonPath('root.children.left.children.left.children.left.id', $greatGrandchild->id);
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

    public function test_admin_structure_root_orphans_excludes_super_admin_and_counts_partner_children(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'name' => 'Super Admin',
            'login' => 'super-admin-root',
            'sponsor_id' => null,
        ]);
        $rootA = User::factory()->create([
            'role' => User::ROLE_USER,
            'name' => 'Root A',
            'login' => 'root-a',
            'sponsor_id' => null,
        ]);
        $rootB = User::factory()->create([
            'role' => User::ROLE_USER,
            'name' => 'Root B',
            'login' => 'root-b',
            'sponsor_id' => null,
        ]);
        $noNodeRoot = User::factory()->create([
            'role' => User::ROLE_USER,
            'name' => 'No Node Root',
            'login' => 'no-node-root',
            'sponsor_id' => null,
        ]);

        $rootAChild = User::factory()->create(['role' => User::ROLE_USER, 'sponsor_id' => $rootA->id]);
        $rootASuperAdminChild = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'sponsor_id' => $rootA->id]);
        $rootBChildA = User::factory()->create(['role' => User::ROLE_USER, 'sponsor_id' => $rootB->id]);
        $rootBChildB = User::factory()->create(['role' => User::ROLE_USER, 'sponsor_id' => $rootB->id]);
        $nonRoot = User::factory()->create(['role' => User::ROLE_USER, 'sponsor_id' => $rootAChild->id]);

        $this->node($superAdmin);
        $rootANode = $this->node($rootA);
        $rootBNode = $this->node($rootB);
        $rootAChildNode = $this->node($rootAChild, $rootANode, 'L');
        $this->node($rootASuperAdminChild, $rootANode, 'R');
        $this->node($rootBChildA, $rootBNode, 'L');
        $this->node($rootBChildB, $rootBNode, 'R');
        $this->node($nonRoot, $rootAChildNode, 'L');

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->getJson('/api/admin/structure/root-orphans?sort_by=children_count&sort_dir=desc&limit=10')
            ->assertOk();
        $rows = collect($response->json('data'));
        $rowIds = $rows->pluck('id')->all();

        $this->assertSame($rootB->id, $rows->first()['id']);
        $this->assertContains($rootA->id, $rowIds);
        $this->assertContains($noNodeRoot->id, $rowIds);
        $this->assertNotContains($superAdmin->id, $rowIds);
        $this->assertNotContains($nonRoot->id, $rowIds);
        $this->assertSame(1, data_get($rows->firstWhere('id', $rootA->id), 'children_count'));

        $this->getJson('/api/admin/structure/root-orphans?search=no-node-root')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $noNodeRoot->id);
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
            ->assertJsonPath('root.children.right', null)
            ->assertJsonPath('stats.total_downline_count', 0);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: User, 4: User}
     */
    private function createNestedBinaryTree(): array
    {
        $root = $this->user('Root Partner', 'rootpartner');
        $left = $this->user('Left Partner', 'leftpartner');
        $right = $this->user('Right Partner', 'rightpartner');
        $leftGrandchild = $this->user('Left Grandchild', 'leftgrandchild');
        $greatGrandchild = $this->user('Great Grandchild', 'greatgrandchild');

        $rootNode = $this->node($root);
        $leftNode = $this->node($left, $rootNode, 'L');
        $this->node($right, $rootNode, 'R');
        $leftGrandchildNode = $this->node($leftGrandchild, $leftNode, 'L');
        $this->node($greatGrandchild, $leftGrandchildNode, 'L');

        return [$root, $left, $right, $leftGrandchild, $greatGrandchild];
    }

    /**
     * @return array<int, User>
     */
    private function createSevenDescendantTree(): array
    {
        $root = $this->user('Root Partner', 'rootseven');
        $left = $this->user('Left Partner', 'leftseven');
        $right = $this->user('Right Partner', 'rightseven');
        $leftLeft = $this->user('Left Left', 'leftleft');
        $leftRight = $this->user('Left Right', 'leftright');
        $leftLeftLeft = $this->user('Left Left Left', 'leftleftleft');
        $rightLeft = $this->user('Right Left', 'rightleft');
        $rightRight = $this->user('Right Right', 'rightright');

        $rootNode = $this->node($root);
        $leftNode = $this->node($left, $rootNode, 'L');
        $rightNode = $this->node($right, $rootNode, 'R');
        $leftLeftNode = $this->node($leftLeft, $leftNode, 'L');
        $this->node($leftRight, $leftNode, 'R');
        $this->node($leftLeftLeft, $leftLeftNode, 'L');
        $this->node($rightLeft, $rightNode, 'L');
        $this->node($rightRight, $rightNode, 'R');

        return [$root, $left, $right, $leftLeft, $leftRight, $leftLeftLeft, $rightLeft, $rightRight];
    }

    private function user(string $name, string $login): User
    {
        return User::factory()->create([
            'name' => $name,
            'login' => $login,
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
        ]);
    }
}

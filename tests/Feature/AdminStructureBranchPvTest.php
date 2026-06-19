<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStructureBranchPvTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_structure_returns_left_and_right_pv(): void
    {
        [$start, $vip] = [$this->package('START', 100, 100), $this->package('VIP', 300, 300)];
        $root = $this->user('Root', 'root');
        $left = $this->user('Left Start', 'leftstart', $start);
        $right = $this->user('Right Vip', 'rightvip', $vip);
        $rootNode = $this->node($root);
        $this->node($left, $rootNode, 'L');
        $this->node($right, $rootNode, 'R');

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/structure?user_id={$root->id}")
            ->assertOk()
            ->assertJsonPath('summary.left_pv', '100.00')
            ->assertJsonPath('summary.right_pv', '300.00')
            ->assertJsonPath('summary.weak_leg_pv', 100)
            ->assertJsonPath('stats.left_branch_pv', '100.00')
            ->assertJsonPath('stats.right_branch_pv', '300.00')
            ->assertJsonPath('root.left_branch_pv', '100.00')
            ->assertJsonPath('root.right_branch_pv', '300.00')
            ->assertJsonPath('root.weak_leg_pv', 100);
    }

    public function test_admin_structure_uses_full_direct_child_subtree_volume(): void
    {
        [$start, $vip] = [$this->package('START', 100, 100), $this->package('VIP', 300, 300)];
        $root = $this->user('Root', 'nestedroot');
        $left = $this->user('Left Start', 'nestedleft', $start);
        $leftGrandchild = $this->user('Left Vip', 'nestedvip', $vip);
        $rootNode = $this->node($root);
        $leftNode = $this->node($left, $rootNode, 'L');
        $this->node($leftGrandchild, $leftNode, 'R');

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/structure?user_id={$root->id}")
            ->assertOk()
            ->assertJsonPath('summary.left_count', 2)
            ->assertJsonPath('summary.right_count', 0)
            ->assertJsonPath('summary.left_pv', '400.00')
            ->assertJsonPath('summary.right_pv', '0.00')
            ->assertJsonPath('summary.weak_leg_pv', 0)
            ->assertJsonPath('root.children.left.right_branch_pv', '300.00');
    }

    public function test_elite_contributes_package_pv_to_structure_not_price_or_upgrade_delta(): void
    {
        $elite = $this->package('ELITE', 500, 200, 300000);
        $root = $this->user('Root', 'eliteroot');
        $left = $this->user('Elite Partner', 'elitepartner', $elite);
        $rootNode = $this->node($root);
        $this->node($left, $rootNode, 'L');

        Sanctum::actingAs($this->admin());

        $response = $this->getJson("/api/admin/structure?user_id={$root->id}")
            ->assertOk()
            ->assertJsonPath('summary.left_pv', '500.00')
            ->assertJsonPath('root.left_branch_pv', '500.00')
            ->assertJsonPath('root.children.left.personal_pv', 500)
            ->assertJsonPath('root.children.left.package_pv', 500)
            ->assertJsonPath('root.children.left.left_branch_pv', '0.00')
            ->assertJsonPath('root.children.left.right_branch_pv', '0.00')
            ->assertJsonPath('root.children.left.weak_leg_pv', 0)
            ->assertJsonPath('root.children.left.package.turnover_pv', 200);

        $this->assertNotSame('200.00', $response->json('summary.left_pv'));
        $this->assertNotSame('300000.00', $response->json('summary.left_pv'));
    }

    public function test_nested_node_branch_pv_is_calculated(): void
    {
        [$start, $vip] = [$this->package('START', 100, 100), $this->package('VIP', 300, 300)];
        $root = $this->user('Root', 'nodepvroot');
        $nodeA = $this->user('Node A', 'nodea');
        $leftStart = $this->user('A Left Start', 'aleftstart', $start);
        $rightVip = $this->user('A Right Vip', 'arightvip', $vip);
        $rootNode = $this->node($root);
        $nodeANode = $this->node($nodeA, $rootNode, 'L');
        $this->node($leftStart, $nodeANode, 'L');
        $this->node($rightVip, $nodeANode, 'R');

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/structure?user_id={$root->id}")
            ->assertOk()
            ->assertJsonPath('root.left_branch_pv', '400.00')
            ->assertJsonPath('root.right_branch_pv', '0.00')
            ->assertJsonPath('root.children.left.id', $nodeA->id)
            ->assertJsonPath('root.children.left.left_branch_pv', '100.00')
            ->assertJsonPath('root.children.left.right_branch_pv', '300.00')
            ->assertJsonPath('root.children.left.weak_leg_pv', 100);
    }

    public function test_parent_branch_includes_direct_child_package_and_both_child_branches(): void
    {
        [$start, $vip] = [$this->package('START', 100, 100), $this->package('VIP', 300, 300)];
        $root0061 = $this->user('0061', 'node0061', $start);
        $node0141 = $this->user('0141', 'node0141', $start);
        $node0301 = $this->user('0301', 'node0301', $vip);
        $node0611 = $this->user('0611', 'node0611', $start);
        $node0621 = $this->user('0621', 'node0621', $start);
        $rootNode = $this->node($root0061);
        $node0141Node = $this->node($node0141, $rootNode, 'R');
        $node0301Node = $this->node($node0301, $node0141Node, 'R');
        $this->node($node0611, $node0301Node, 'L');
        $this->node($node0621, $node0301Node, 'R');

        Sanctum::actingAs($this->admin());

        $response = $this->getJson("/api/admin/structure?user_id={$root0061->id}")
            ->assertOk()
            ->assertJsonPath('root.right_branch_pv', '600.00')
            ->assertJsonPath('root.left_branch_pv', '0.00')
            ->assertJsonPath('root.children.right.right_branch_pv', '500.00')
            ->assertJsonPath('root.children.right.left_branch_pv', '0.00')
            ->assertJsonPath('root.children.right.children.right.left_branch_pv', '100.00')
            ->assertJsonPath('root.children.right.children.right.right_branch_pv', '100.00')
            ->assertJsonPath('root.children.right.children.right.children.right.left_branch_pv', '0.00')
            ->assertJsonPath('root.children.right.children.right.children.right.right_branch_pv', '0.00');

        $this->assertNotSame('300000.00', $response->json('root.right_branch_pv'));
    }

    public function test_parent_branch_matches_full_subtree_examples_for_0021(): void
    {
        $start = $this->package('START', 100, 100);
        $vip = $this->package('VIP', 300, 300);
        $elite = $this->package('ELITE', 500, 200, 300000);

        $root0021 = $this->user('0021', 'node0021');
        $node0061 = $this->user('0061', 'node0061example', $start);
        $node0051 = $this->user('0051', 'node0051example', $start);
        $rootNode = $this->node($root0021);
        $node0061Node = $this->node($node0061, $rootNode, 'L');
        $node0051Node = $this->node($node0051, $rootNode, 'R');

        $this->node($this->user('0061 left VIP', 'node0061left', $vip), $node0061Node, 'L');
        $this->node($this->user('0061 right ELITE', 'node0061right', $elite), $node0061Node, 'R');

        $node0051Left = $this->node($this->user('0051 left VIP', 'node0051left', $vip), $node0051Node, 'L');
        $this->node($this->user('0051 left child VIP', 'node0051leftchild', $vip), $node0051Left, 'L');
        $this->node($this->user('0051 right VIP', 'node0051right', $vip), $node0051Node, 'R');

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/structure?user_id={$root0021->id}")
            ->assertOk()
            ->assertJsonPath('root.children.left.personal_pv', 100)
            ->assertJsonPath('root.children.left.left_branch_pv', '300.00')
            ->assertJsonPath('root.children.left.right_branch_pv', '500.00')
            ->assertJsonPath('root.children.right.personal_pv', 100)
            ->assertJsonPath('root.children.right.left_branch_pv', '600.00')
            ->assertJsonPath('root.children.right.right_branch_pv', '300.00')
            ->assertJsonPath('root.left_branch_pv', '900.00')
            ->assertJsonPath('root.right_branch_pv', '1000.00');
    }

    public function test_tree_node_includes_package_and_status_labels(): void
    {
        $start = $this->package('START', 100, 100);
        $root = $this->user('Root', 'labelroot', $start, 'gold_director');
        $this->node($root);

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/structure?user_id={$root->id}")
            ->assertOk()
            ->assertJsonPath('root.package.code', 'START')
            ->assertJsonPath('root.package.label', 'Старт')
            ->assertJsonPath('root.package.activity_pv', 100)
            ->assertJsonPath('root.package.turnover_pv', 100)
            ->assertJsonPath('root.mlm_status.code', 'GOLD_DIRECTOR')
            ->assertJsonPath('root.mlm_status.label', 'Золотой директор')
            ->assertJsonPath('root.status_label', 'Золотой директор');
    }

    public function test_root_personal_pv_uses_activity_pv(): void
    {
        $start = $this->package('START', 100, 100);
        $vip = $this->package('VIP', 300, 300);
        $elite = $this->package('ELITE', 500, 200, 300000);

        foreach ([[$start, 100], [$vip, 300], [$elite, 500]] as [$package, $expectedPv]) {
            $root = $this->user('Root '.$package->code, 'root'.strtolower($package->code), $package);
            $this->node($root);

            Sanctum::actingAs($this->admin());

            $this->getJson("/api/admin/structure?user_id={$root->id}")
                ->assertOk()
                ->assertJsonPath('root.personal_pv', $expectedPv)
                ->assertJsonPath('root.package_activity_pv', $expectedPv);
        }
    }

    public function test_selected_user_returns_correct_tree_and_summary(): void
    {
        [$start, $vip] = [$this->package('START', 100, 100), $this->package('VIP', 300, 300)];
        $root = $this->user('Root', 'selectedroot');
        $left = $this->user('Left', 'selectedleft', $start);
        $leftChild = $this->user('Left Child', 'selectedchild', $vip);
        $rootNode = $this->node($root);
        $leftNode = $this->node($left, $rootNode, 'L');
        $this->node($leftChild, $leftNode, 'R');

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/structure?user_id={$left->id}")
            ->assertOk()
            ->assertJsonPath('root.id', $left->id)
            ->assertJsonPath('summary.left_count', 0)
            ->assertJsonPath('summary.right_count', 1)
            ->assertJsonPath('summary.left_pv', '0.00')
            ->assertJsonPath('summary.right_pv', '300.00');
    }

    public function test_summary_matches_root_node_branch_pv(): void
    {
        [$start, $vip] = [$this->package('START', 100, 100), $this->package('VIP', 300, 300)];
        $root = $this->user('Root', 'matchroot');
        $left = $this->user('Left', 'matchleft', $start);
        $right = $this->user('Right', 'matchright', $vip);
        $rootNode = $this->node($root);
        $this->node($left, $rootNode, 'L');
        $this->node($right, $rootNode, 'R');

        Sanctum::actingAs($this->admin());

        $response = $this->getJson("/api/admin/structure?user_id={$root->id}")
            ->assertOk();

        $this->assertSame($response->json('summary.left_pv'), $response->json('root.left_branch_pv'));
        $this->assertSame($response->json('summary.right_pv'), $response->json('root.right_branch_pv'));
        $this->assertSame($response->json('summary.weak_leg_pv'), $response->json('root.weak_leg_pv'));
    }

    public function test_staff_users_are_excluded_from_branch_counts_and_pv(): void
    {
        $start = $this->package('START', 100, 100);
        $root = $this->user('Root', 'staffroot');
        $staff = User::factory()->create([
            'role' => User::ROLE_SUPPORT,
            'current_package_id' => $start->id,
        ]);
        $rootNode = $this->node($root);
        $this->node($staff, $rootNode, 'L');

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/structure?user_id={$root->id}")
            ->assertOk()
            ->assertJsonPath('summary.left_count', 0)
            ->assertJsonPath('summary.right_count', 0)
            ->assertJsonPath('summary.left_pv', '0.00')
            ->assertJsonPath('summary.right_pv', '0.00')
            ->assertJsonPath('root.left_branch_pv', '0.00')
            ->assertJsonPath('root.right_branch_pv', '0.00')
            ->assertJsonPath('root.weak_leg_pv', 0)
            ->assertJsonPath('summary.total_structure_count', 0);
    }

    public function test_user_and_support_cannot_access_admin_structure(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));
        $this->getJson('/api/admin/structure')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPPORT]));
        $this->getJson('/api/admin/structure')->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    private function user(string $name, string $login, ?Package $package = null, string $status = 'user'): User
    {
        return User::factory()->create([
            'name' => $name,
            'login' => $login,
            'status' => $status,
            'current_package_id' => $package?->id,
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

    private function package(string $code, int $activityPv, int $turnoverPv, int $price = 60000): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $price,
            'pv' => $activityPv,
            'activity_pv' => $activityPv,
            'turnover_pv' => $turnoverPv,
            'referral_percent' => 0,
            'binary_percent' => 0,
            'sort_order' => match ($code) {
                'VIP' => 2,
                'ELITE' => 3,
                default => 1,
            },
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}

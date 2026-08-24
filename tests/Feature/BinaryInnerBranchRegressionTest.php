<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\Package;
use App\Models\PvTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BinaryInnerBranchRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_binary_recalculation_counts_full_left_and_right_subtrees(): void
    {
        $package = $this->package('START', 7);
        $root = $this->partner('Test1', $package);
        $test2 = $this->partner('test2', $package, $root);
        $test3 = $this->partner('test3', $package, $root);
        $test4 = $this->partner('test4', $package);
        $test5 = $this->partner('test5', $package);
        $test44 = $this->partner('test44', $package);
        $test6 = $this->partner('test6', $package);
        $test7 = $this->partner('test7', $package);
        $test50 = $this->partner('test50', $package);
        $test66 = $this->partner('test66', $package);
        $test100 = $this->partner('test100', $package);
        $test9 = $this->partner('test9', $package);

        $rootNode = $this->node($root);
        $test2Node = $this->node($test2, $rootNode, 'L');
        $test3Node = $this->node($test3, $rootNode, 'R');

        $test4Node = $this->node($test4, $test2Node, 'L');
        $this->node($test5, $test2Node, 'R');
        $this->node($test44, $test4Node, 'L');

        $test6Node = $this->node($test6, $test3Node, 'L');
        $test7Node = $this->node($test7, $test3Node, 'R');
        $this->node($test50, $test6Node, 'L');
        $this->node($test66, $test6Node, 'R');
        $test100Node = $this->node($test100, $test7Node, 'R');
        $this->node($test9, $test100Node, 'R');

        $this->pv($test2, $test4, 'L', 100);
        $this->pv($test2, $test5, 'R', 300);
        $this->pv($test4, $test44, 'L', 500);
        $this->pv($test3, $test6, 'L', 300);
        $this->pv($test3, $test7, 'R', 300);
        $this->pv($test6, $test50, 'L', 500);
        $this->pv($test6, $test66, 'R', 500);
        $this->pv($test7, $test100, 'R', 100);
        $this->pv($test100, $test9, 'R', 500);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->postJson("/api/admin/partners/{$root->id}/binary/recalculate")
            ->assertOk()
            ->assertJsonPath('data.left_total_pv', '900.00')
            ->assertJsonPath('data.right_total_pv', '2200.00')
            ->assertJsonPath('data.left_nodes_count', 4)
            ->assertJsonPath('data.right_nodes_count', 7)
            ->assertJsonPath('data.matched_pv', '900.00')
            ->assertJsonPath('data.bonus_total', '31500.00')
            ->assertJsonPath('data.main_amount', '28350.00')
            ->assertJsonPath('data.deposit_amount', '3150.00');
    }

    private function partner(string $login, Package $package, ?User $sponsor = null): User
    {
        return User::factory()->create([
            'name' => $login,
            'login' => $login.'-'.uniqid(),
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'sponsor_id' => $sponsor?->id,
            'current_package_id' => $package->id,
        ]);
    }

    private function package(string $code, int $binaryPercent): Package
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
            'binary_percent' => $binaryPercent,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
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
            'path' => trim(($parent?->path ? $parent->path.'.' : '').$user->id, '.'),
            'is_active' => true,
        ]);
    }

    private function pv(User $upline, User $buyer, string $branch, int $pv): void
    {
        PvTransaction::query()->create([
            'buyer_id' => $buyer->id,
            'upline_id' => $upline->id,
            'source' => 'test_inner_branch_fixture',
            'branch' => $branch,
            'pv' => $pv,
            'is_bonusable' => true,
        ]);
    }
}

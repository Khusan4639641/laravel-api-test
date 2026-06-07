<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Services\BinaryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardOverviewBranchPvTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_overview_returns_branch_pv_from_downline_packages(): void
    {
        [$start, $vip] = [$this->package('START', 100, 100), $this->package('VIP', 300, 300)];
        $tree = app(BinaryTreeService::class);
        $root = User::factory()->create();
        $leftChild = User::factory()->create(['current_package_id' => $start->id]);
        $rightChild = User::factory()->create(['current_package_id' => $vip->id]);

        $tree->placeUser($root);
        $tree->placeUser($leftChild, $root, 'L');
        $tree->placeUser($rightChild, $root, 'R');

        Sanctum::actingAs($root);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('structure.left_pv', '100.00')
            ->assertJsonPath('structure.right_pv', '300.00')
            ->assertJsonPath('structure.weak_leg_pv', 100)
            ->assertJsonPath('user.left_pv', '100.00')
            ->assertJsonPath('user.right_pv', '300.00')
            ->assertJsonPath('user.weak_leg_pv', 100);
    }

    public function test_buyer_does_not_receive_own_package_pv_into_own_branches(): void
    {
        $start = $this->package('START', 100, 100);
        $tree = app(BinaryTreeService::class);
        $parent = User::factory()->create();
        $buyer = User::factory()->create();

        $tree->placeUser($parent);
        $tree->placeUser($buyer, $parent, 'L');

        Sanctum::actingAs($buyer);

        $this->postJson("/api/packages/{$start->id}/activate")
            ->assertOk();

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('structure.left_pv', '0.00')
            ->assertJsonPath('structure.right_pv', '0.00')
            ->assertJsonPath('structure.weak_leg_pv', 0);

        Sanctum::actingAs($parent);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('structure.left_pv', '100.00')
            ->assertJsonPath('structure.right_pv', '0.00')
            ->assertJsonPath('structure.weak_leg_pv', 0);
    }

    public function test_elite_child_contributes_turnover_pv_not_price_or_activity_pv(): void
    {
        $elite = $this->package('ELITE', 500, 200, 300000);
        $tree = app(BinaryTreeService::class);
        $root = User::factory()->create();
        $child = User::factory()->create(['current_package_id' => $elite->id]);

        $tree->placeUser($root);
        $tree->placeUser($child, $root, 'L');

        Sanctum::actingAs($root);

        $response = $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('structure.left_pv', '200.00')
            ->assertJsonPath('structure.right_pv', '0.00');

        $this->assertNotSame('300000.00', $response->json('structure.left_pv'));
        $this->assertNotSame('500.00', $response->json('structure.left_pv'));
    }

    public function test_dashboard_structure_summary_returns_same_branch_pv_as_overview(): void
    {
        [$start, $vip] = [$this->package('START', 100, 100), $this->package('VIP', 300, 300)];
        $tree = app(BinaryTreeService::class);
        $root = User::factory()->create();
        $leftChild = User::factory()->create(['current_package_id' => $start->id]);
        $rightChild = User::factory()->create(['current_package_id' => $vip->id]);

        $tree->placeUser($root);
        $tree->placeUser($leftChild, $root, 'L');
        $tree->placeUser($rightChild, $root, 'R');

        Sanctum::actingAs($root);

        $overview = $this->getJson('/api/dashboard/overview')->assertOk()->json('structure');
        $structure = $this->getJson('/api/dashboard/structure')->assertOk()->json('summary');

        $this->assertSame($overview['left_pv'], $structure['left_pv']);
        $this->assertSame($overview['right_pv'], $structure['right_pv']);
        $this->assertSame($overview['weak_leg_pv'], $structure['weak_leg_pv']);
    }

    public function test_weak_leg_is_minimum_branch_pv(): void
    {
        $user = User::factory()->create([
            'left_pv' => 1000,
            'right_pv' => 300,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('structure.left_pv', '1000.00')
            ->assertJsonPath('structure.right_pv', '300.00')
            ->assertJsonPath('structure.weak_leg_pv', 300)
            ->assertJsonPath('structure.weak_leg', 'right');
    }

    public function test_downline_count_with_empty_cached_fields_still_returns_branch_pv(): void
    {
        $start = $this->package('START', 100, 100);
        $tree = app(BinaryTreeService::class);
        $root = User::factory()->create(['left_pv' => 0, 'right_pv' => 0]);
        $child = User::factory()->create(['current_package_id' => $start->id]);

        $tree->placeUser($root);
        $tree->placeUser($child, $root, 'L');

        Sanctum::actingAs($root);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('structure.total_partners', 1)
            ->assertJsonPath('structure.left_pv', '100.00')
            ->assertJsonPath('structure.right_pv', '0.00');
    }

    public function test_user_cannot_see_another_users_branch_pv(): void
    {
        [$start, $vip] = [$this->package('START', 100, 100), $this->package('VIP', 300, 300)];
        $tree = app(BinaryTreeService::class);
        $rootA = User::factory()->create();
        $rootB = User::factory()->create();
        $childA = User::factory()->create(['current_package_id' => $start->id]);
        $childB = User::factory()->create(['current_package_id' => $vip->id]);

        $tree->placeUser($rootA);
        $tree->placeUser($rootB);
        $tree->placeUser($childA, $rootA, 'L');
        $tree->placeUser($childB, $rootB, 'R');

        Sanctum::actingAs($rootA);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('structure.left_pv', '100.00')
            ->assertJsonPath('structure.right_pv', '0.00')
            ->assertJsonPath('structure.total_partners', 1);
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

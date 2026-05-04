<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Services\BinaryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PvAccrualTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_activation_accrues_pv_to_all_binary_parents(): void
    {
        $treeService = app(BinaryTreeService::class);
        $root = User::factory()->create();
        $leftChild = User::factory()->create();
        $rightGrandchild = User::factory()->create();
        $package = $this->createPackage('VIP', 180000, 180000, 1);

        $treeService->placeUser($leftChild, $root, 'L');
        $treeService->placeUser($rightGrandchild, $leftChild, 'R');

        Sanctum::actingAs($rightGrandchild);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertOk();

        $root->refresh();
        $leftChild->refresh();
        $rightGrandchild->refresh();

        $this->assertSame('180000.00', $root->left_pv);
        $this->assertSame('0.00', $root->right_pv);
        $this->assertSame('180000.00', $root->remaining_left_pv);
        $this->assertSame('180000.00', $root->total_pv);

        $this->assertSame('0.00', $leftChild->left_pv);
        $this->assertSame('180000.00', $leftChild->right_pv);
        $this->assertSame('180000.00', $leftChild->remaining_right_pv);
        $this->assertSame('180000.00', $leftChild->total_pv);

        $this->assertSame('0.00', $rightGrandchild->left_pv);
        $this->assertSame('0.00', $rightGrandchild->right_pv);
        $this->assertSame('180000.00', $rightGrandchild->total_pv);
    }

    public function test_package_activation_without_binary_node_updates_only_user_total_pv(): void
    {
        $user = User::factory()->create();
        $package = $this->createPackage('START', 30000, 30000, 1);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertOk();

        $user->refresh();

        $this->assertSame('0.00', $user->left_pv);
        $this->assertSame('0.00', $user->right_pv);
        $this->assertSame('30000.00', $user->total_pv);
    }

    private function createPackage(string $code, int $price, int $pv, int $sortOrder): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $price,
            'pv' => $pv,
            'referral_percent' => 0,
            'binary_percent' => 0,
            'sort_order' => $sortOrder,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}

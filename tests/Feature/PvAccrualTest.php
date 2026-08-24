<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Order;
use App\Models\Product;
use App\Models\PvTransaction;
use App\Models\User;
use App\Services\BinaryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PvAccrualTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_activation_propagates_through_internal_branch_turn(): void
    {
        $treeService = app(BinaryTreeService::class);
        $root = User::factory()->create();
        $leftChild = User::factory()->create();
        $rightGrandchild = User::factory()->create();
        $package = $this->createPackage('VIP', 180000, 300, 1);

        $treeService->placeUser($leftChild, $root, 'L');
        $treeService->placeUser($rightGrandchild, $leftChild, 'R');

        Sanctum::actingAs($rightGrandchild);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertOk();

        $root->refresh();
        $leftChild->refresh();
        $rightGrandchild->refresh();

        $this->assertSame('300.00', $root->left_pv);
        $this->assertSame('0.00', $root->right_pv);
        $this->assertSame('300.00', $root->remaining_left_pv);
        $this->assertSame('300.00', $root->total_pv);

        $this->assertSame('0.00', $leftChild->left_pv);
        $this->assertSame('300.00', $leftChild->right_pv);
        $this->assertSame('300.00', $leftChild->remaining_right_pv);
        $this->assertSame('300.00', $leftChild->total_pv);

        $this->assertSame('0.00', $rightGrandchild->left_pv);
        $this->assertSame('0.00', $rightGrandchild->right_pv);
        $this->assertSame('300.00', $rightGrandchild->total_pv);
        $this->assertPvTransaction($rightGrandchild, $leftChild, 'package_vip', 'R', '300.00', true);
        $this->assertPvTransaction($rightGrandchild, $root, 'package_vip', 'L', '300.00', true);
        $this->assertSame(2, PvTransaction::query()->where('buyer_id', $rightGrandchild->id)->count());
    }

    public function test_package_activation_continues_up_same_direction_branch(): void
    {
        $treeService = app(BinaryTreeService::class);
        $root = User::factory()->create();
        $leftChild = User::factory()->create();
        $leftGrandchild = User::factory()->create();
        $package = $this->createPackage('VIP', 180000, 300, 1);

        $treeService->placeUser($root);
        $treeService->placeUser($leftChild, $root, 'L');
        $treeService->placeUser($leftGrandchild, $leftChild, 'L');

        Sanctum::actingAs($leftGrandchild);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertOk();

        $root->refresh();
        $leftChild->refresh();
        $leftGrandchild->refresh();

        $this->assertSame('300.00', $root->left_pv);
        $this->assertSame('0.00', $root->right_pv);
        $this->assertSame('300.00', $leftChild->left_pv);
        $this->assertSame('0.00', $leftChild->right_pv);
        $this->assertPvTransaction($leftGrandchild, $leftChild, 'package_vip', 'L', '300.00', true);
        $this->assertPvTransaction($leftGrandchild, $root, 'package_vip', 'L', '300.00', true);
        $this->assertSame(2, PvTransaction::query()->where('buyer_id', $leftGrandchild->id)->count());
    }

    public function test_start_package_propagates_one_hundred_pv_to_direct_parent_left_branch(): void
    {
        $treeService = app(BinaryTreeService::class);
        $parent = User::factory()->create();
        $buyer = User::factory()->create();
        $package = $this->createPackage('START', 60000, 100, 1);

        $treeService->placeUser($parent);
        $treeService->placeUser($buyer, $parent, 'L');

        Sanctum::actingAs($buyer);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertOk();

        $buyer->refresh();
        $parent->refresh();

        $this->assertSame('0.00', $buyer->left_pv);
        $this->assertSame('0.00', $buyer->right_pv);
        $this->assertSame('100.00', $buyer->total_pv);
        $this->assertSame('100.00', $parent->left_pv);
        $this->assertSame('0.00', $parent->right_pv);
        $this->assertPvTransaction($buyer, $parent, 'package_start', 'L', '100.00', true);
    }

    public function test_package_activation_without_binary_node_updates_only_user_total_pv(): void
    {
        $user = User::factory()->create();
        $package = $this->createPackage('START', 60000, 100, 1);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertOk();

        $user->refresh();

        $this->assertSame('0.00', $user->left_pv);
        $this->assertSame('0.00', $user->right_pv);
        $this->assertSame('100.00', $user->total_pv);
    }

    public function test_product_order_pv_propagates_through_internal_branch_turn_without_buyer_branch_pv(): void
    {
        $treeService = app(BinaryTreeService::class);
        $root = User::factory()->create();
        $directParent = User::factory()->create();
        $buyer = User::factory()->create();
        $product = $this->createProduct('Safi Test Product', 10000, 999);

        $treeService->placeUser($root);
        $treeService->placeUser($directParent, $root, 'L');
        $treeService->placeUser($buyer, $directParent, 'R');

        Sanctum::actingAs($buyer);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'recipient_name' => 'Safi Client',
            'phone' => '+77010000000',
            'city' => 'Almaty',
            'delivery_address' => 'Abay 10',
        ])
            ->assertCreated()
            ->assertJsonPath('order.total_pv', '20.00');

        $buyer->refresh();
        $directParent->refresh();
        $root->refresh();

        $this->assertSame('0.00', $buyer->left_pv);
        $this->assertSame('0.00', $buyer->right_pv);
        $this->assertSame('0.00', $buyer->remaining_left_pv);
        $this->assertSame('0.00', $buyer->remaining_right_pv);

        $this->assertSame('0.00', $directParent->left_pv);
        $this->assertSame('20.00', $directParent->right_pv);
        $this->assertSame('20.00', $directParent->remaining_right_pv);

        $this->assertSame('20.00', $root->left_pv);
        $this->assertSame('0.00', $root->right_pv);
        $this->assertSame('20.00', $root->remaining_left_pv);

        $order = Order::query()->firstOrFail();

        $this->assertSame('product_order', $order->metadata['pv_turnover']['source']);
        $this->assertSame('20.00', $order->metadata['pv_turnover']['pv']);
        $this->assertTrue($order->metadata['pv_turnover']['is_bonusable']);
        $this->assertSame('10000.00', $order->metadata['pv_turnover']['meta']['turnover_amount']);
        $this->assertPvTransaction($buyer, $directParent, 'product_order', 'R', '20.00', true, $order);
        $this->assertPvTransaction($buyer, $root, 'product_order', 'L', '20.00', true, $order);
        $this->assertSame(2, PvTransaction::query()->where('buyer_id', $buyer->id)->where('source', 'product_order')->count());
    }

    public function test_elite_upgrade_propagates_only_two_hundred_non_bonusable_pv_to_uplines(): void
    {
        $treeService = app(BinaryTreeService::class);
        $root = User::factory()->create();
        $directParent = User::factory()->create();
        $buyer = User::factory()->create();
        $vip = $this->createPackage('VIP', 180000, 300, 2);
        $elite = $this->createPackage('ELITE', 300000, 500, 3, 200);

        $treeService->placeUser($root);
        $treeService->placeUser($directParent, $root, 'R');
        $treeService->placeUser($buyer, $directParent, 'L');

        $buyer->forceFill([
            'current_package_id' => $vip->id,
            'total_pv' => 300,
        ])->save();

        Sanctum::actingAs($buyer);

        $this->postJson("/api/packages/{$elite->id}/upgrade")
            ->assertOk()
            ->assertJsonPath('additional_pv', '200.00');

        $buyer->refresh();
        $directParent->refresh();
        $root->refresh();

        $this->assertSame('500.00', $buyer->total_pv);
        $this->assertSame('0.00', $buyer->left_pv);
        $this->assertSame('0.00', $buyer->right_pv);

        $this->assertSame('200.00', $directParent->left_pv);
        $this->assertSame('0.00', $directParent->remaining_left_pv);
        $this->assertSame('200.00', $root->right_pv);
        $this->assertSame('0.00', $root->remaining_right_pv);
        $this->assertPvTransaction($buyer, $directParent, 'package_elite_upgrade', 'L', '200.00', false);
        $this->assertPvTransaction($buyer, $root, 'package_elite_upgrade', 'R', '200.00', false);
        $this->assertSame(2, PvTransaction::query()->where('buyer_id', $buyer->id)->where('source', 'package_elite_upgrade')->count());
    }

    private function assertPvTransaction(
        User $buyer,
        User $upline,
        string $source,
        string $branch,
        string $pv,
        bool $isBonusable,
        ?Order $sourceOrder = null,
    ): void {
        $query = PvTransaction::query()
            ->where('buyer_id', $buyer->id)
            ->where('upline_id', $upline->id)
            ->where('source', $source)
            ->where('branch', $branch)
            ->where('pv', $pv)
            ->where('is_bonusable', $isBonusable);

        if ($sourceOrder) {
            $query->where('source_order_id', $sourceOrder->id);
        }

        $this->assertTrue($query->exists(), "Missing PV transaction {$source} {$branch} {$pv}.");
    }

    private function createPackage(string $code, int $price, int $pv, int $sortOrder, ?int $turnoverPv = null): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $price,
            'pv' => $pv,
            'activity_pv' => $pv,
            'turnover_pv' => $turnoverPv ?? $pv,
            'referral_percent' => 0,
            'binary_percent' => 0,
            'sort_order' => $sortOrder,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }

    private function createProduct(string $name, int $price, int $pv): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => strtoupper(str_replace(' ', '-', $name)),
            'description' => $name,
            'price' => $price,
            'pv' => $pv,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\Package;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\BinaryTreeService;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardStructurePartnersListTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_dashboard_structure_keeps_direct_invited_separate_from_binary_downline(): void
    {
        $root = $this->partner('Root User', 'root_user');
        $this->partner('Direct Invited', 'direct_invited', $root);

        Sanctum::actingAs($root);

        $this->getJson('/api/dashboard/structure')
            ->assertOk()
            ->assertJsonPath('summary.total_partners', 0)
            ->assertJsonPath('summary.direct_invited', 1)
            ->assertJsonCount(0, 'partners.data');
    }

    public function test_user_dashboard_structure_returns_downline_partners(): void
    {
        $service = app(BinaryTreeService::class);
        $root = $this->partner('Root User', 'downline_root');
        $left = $this->partner('Left Partner', 'downline_left', $root);
        $right = $this->partner('Right Partner', 'downline_right', $root);
        $leftChild = $this->partner('Left Child', 'downline_left_child', $left);

        $service->placeUser($root, null);
        $service->placeUser($left, $root, 'L');
        $service->placeUser($right, $root, 'R');
        $service->placeUser($leftChild, $left, 'L');

        Sanctum::actingAs($root);

        $partners = collect($this->getJson('/api/dashboard/structure')
            ->assertOk()
            ->assertJsonPath('summary.total_partners', 3)
            ->assertJsonPath('summary.left_count', 2)
            ->assertJsonPath('summary.right_count', 1)
            ->json('partners.data'));

        $expectedIds = [$left->id, $right->id, $leftChild->id];
        $actualIds = $partners->pluck('id')->all();
        sort($expectedIds);
        sort($actualIds);

        $this->assertSame($expectedIds, $actualIds);
        $this->assertSame('left', $partners->firstWhere('id', $left->id)['branch']);
        $this->assertSame(2, $partners->firstWhere('id', $leftChild->id)['line']);
    }

    public function test_user_can_fetch_own_dashboard_tree_with_descendants(): void
    {
        $service = app(BinaryTreeService::class);
        $root = $this->partner('Tree Root', 'tree_root');
        $left = $this->partner('Tree Left', 'tree_left', $root);
        $right = $this->partner('Tree Right', 'tree_right', $root);
        $leftGrandchild = $this->partner('Tree Left Grandchild', 'tree_left_grandchild', $left);

        $service->placeUser($root, null);
        $service->placeUser($left, $root, 'L');
        $service->placeUser($right, $root, 'R');
        $service->placeUser($leftGrandchild, $left, 'L');

        Sanctum::actingAs($root);

        $this->getJson('/api/dashboard/structure')
            ->assertOk()
            ->assertJsonPath('tree.id', $root->id)
            ->assertJsonPath('tree.is_root', true)
            ->assertJsonPath('tree.children.left.id', $left->id)
            ->assertJsonPath('tree.children.right.id', $right->id)
            ->assertJsonPath('tree.children.left.children.left.id', $leftGrandchild->id)
            ->assertJsonPath('tree.children.left.line', 1)
            ->assertJsonPath('tree.children.left.children.left.line', 2)
            ->assertJsonPath('tree.children.left.package_code', 'START')
            ->assertJsonPath('tree.children.left.left_pv', '100.00')
            ->assertJsonPath('tree.children.left.right_pv', '0.00');
    }

    public function test_total_partners_is_consistent_with_returned_partners(): void
    {
        $service = app(BinaryTreeService::class);
        $root = $this->partner('Root User', 'consistent_root');
        $service->placeUser($root, null);

        foreach (range(1, 4) as $index) {
            $partner = $this->partner("Partner {$index}", "consistent_{$index}", $root);
            $service->placeUser($partner, $root, $index % 2 === 0 ? 'R' : 'L');
        }

        Sanctum::actingAs($root);

        $response = $this->getJson('/api/dashboard/structure')->assertOk();

        $this->assertSame(4, $response->json('summary.total_partners'));
        $this->assertCount(4, $response->json('partners.data'));
        $this->assertGreaterThanOrEqual(count($response->json('partners.data')), $response->json('summary.total_partners'));
    }

    public function test_user_cannot_see_another_user_structure(): void
    {
        $service = app(BinaryTreeService::class);
        $rootA = $this->partner('Root A', 'root_a');
        $rootB = $this->partner('Root B', 'root_b');
        $partnerA = $this->partner('Partner A', 'partner_a', $rootA);
        $partnerB = $this->partner('Partner B', 'partner_b', $rootB);

        $service->placeUser($rootA, null);
        $service->placeUser($rootB, null);
        $service->placeUser($partnerA, $rootA, 'L');
        $service->placeUser($partnerB, $rootB, 'L');

        Sanctum::actingAs($rootA);

        $ids = collect($this->getJson('/api/dashboard/structure')->assertOk()->json('partners.data'))->pluck('id');

        $this->assertTrue($ids->contains($partnerA->id));
        $this->assertFalse($ids->contains($partnerB->id));
    }

    public function test_user_cannot_switch_to_foreign_structure_with_query_parameters(): void
    {
        $service = app(BinaryTreeService::class);
        $rootA = $this->partner('Root A Query', 'root_a_query');
        $rootB = $this->partner('Root B Query', 'root_b_query');
        $partnerA = $this->partner('Partner A Query', 'partner_a_query', $rootA);
        $partnerB = $this->partner('Partner B Query', 'partner_b_query', $rootB);

        $service->placeUser($rootA, null);
        $service->placeUser($rootB, null);
        $service->placeUser($partnerA, $rootA, 'L');
        $service->placeUser($partnerB, $rootB, 'L');

        Sanctum::actingAs($rootA);

        $response = $this->getJson("/api/dashboard/structure?root_id={$rootB->id}&user_id={$rootB->id}")
            ->assertOk()
            ->assertJsonPath('structure.root_user_id', $rootA->id)
            ->assertJsonPath('summary.total_partners', 1);

        $ids = collect($response->json('partners.data'))->pluck('id');

        $this->assertTrue($ids->contains($partnerA->id));
        $this->assertFalse($ids->contains($partnerB->id));
        $this->assertFalse($ids->contains($rootB->id));

        $treeIds = $this->treeIds($response->json('tree'));

        $this->assertContains($rootA->id, $treeIds);
        $this->assertContains($partnerA->id, $treeIds);
        $this->assertNotContains($rootB->id, $treeIds);
        $this->assertNotContains($partnerB->id, $treeIds);
    }

    public function test_user_cannot_switch_to_parent_structure_with_query_parameters(): void
    {
        $service = app(BinaryTreeService::class);
        $root = $this->partner('Parent Root', 'parent_root_query');
        $child = $this->partner('Child Root', 'child_root_query', $root);
        $sibling = $this->partner('Sibling Partner', 'sibling_query', $root);
        $grandchild = $this->partner('Grandchild Partner', 'grandchild_query', $child);

        $service->placeUser($root, null);
        $service->placeUser($child, $root, 'L');
        $service->placeUser($sibling, $root, 'R');
        $service->placeUser($grandchild, $child, 'L');

        Sanctum::actingAs($child);

        $response = $this->getJson("/api/dashboard/structure?root_id={$root->id}")
            ->assertOk()
            ->assertJsonPath('structure.root_user_id', $child->id)
            ->assertJsonPath('summary.total_partners', 1);

        $ids = collect($response->json('partners.data'))->pluck('id');

        $this->assertTrue($ids->contains($grandchild->id));
        $this->assertFalse($ids->contains($root->id));
        $this->assertFalse($ids->contains($sibling->id));

        $treeIds = $this->treeIds($response->json('tree'));

        $this->assertContains($child->id, $treeIds);
        $this->assertContains($grandchild->id, $treeIds);
        $this->assertNotContains($root->id, $treeIds);
        $this->assertNotContains($sibling->id, $treeIds);
    }

    public function test_guest_cannot_access_dashboard_structure_tree(): void
    {
        $this->getJson('/api/dashboard/structure')->assertUnauthorized();
    }

    public function test_empty_state_is_only_for_user_without_partners(): void
    {
        $root = $this->partner('Empty Root', 'empty_root');
        BinaryNode::query()->create([
            'user_id' => $root->id,
            'parent_id' => null,
            'position' => null,
            'depth' => 0,
            'path' => (string) $root->id,
        ]);

        Sanctum::actingAs($root);

        $this->getJson('/api/dashboard/structure')
            ->assertOk()
            ->assertJsonPath('summary.total_partners', 0)
            ->assertJsonCount(0, 'partners.data');
    }

    public function test_dashboard_structure_frontend_filters_by_name_email_id_login_and_phone(): void
    {
        $contents = file_get_contents(resource_path('js/safi/pages/dashboard/Structure.tsx'));

        $this->assertStringContainsString("partner.id", $contents);
        $this->assertStringContainsString("partner.name", $contents);
        $this->assertStringContainsString("partner.login", $contents);
        $this->assertStringContainsString("partner.email", $contents);
        $this->assertStringContainsString("partner.phone", $contents);
        $this->assertStringContainsString('Не удалось загрузить список структуры', $contents);
    }

    private function partner(string $name, string $login, ?User $sponsor = null): User
    {
        $this->seed(PackageSeeder::class);
        $package = Package::query()->where('code', 'START')->firstOrFail();

        $user = User::factory()->create([
            'name' => $name,
            'login' => $login,
            'email' => "{$login}@safilife.test",
            'sponsor_id' => $sponsor?->id,
            'current_package_id' => $package->id,
            'status' => 'user',
            'account_status' => 'active',
        ]);

        UserProfile::query()->create([
            'user_id' => $user->id,
            'phone' => '+7700'.str_pad((string) $user->id, 7, '0', STR_PAD_LEFT),
        ]);

        return $user;
    }

    /**
     * @return array<int, int>
     */
    private function treeIds(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $ids = isset($node['id']) ? [(int) $node['id']] : [];
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];

        return [
            ...$ids,
            ...$this->treeIds($children['left'] ?? null),
            ...$this->treeIds($children['right'] ?? null),
        ];
    }
}

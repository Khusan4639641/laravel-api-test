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
            ->assertJsonCount(0, 'partners');
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
            ->json('partners'));

        $expectedIds = [$left->id, $right->id, $leftChild->id];
        $actualIds = $partners->pluck('id')->all();
        sort($expectedIds);
        sort($actualIds);

        $this->assertSame($expectedIds, $actualIds);
        $this->assertSame('left', $partners->firstWhere('id', $left->id)['branch']);
        $this->assertSame(2, $partners->firstWhere('id', $leftChild->id)['line']);
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
        $this->assertCount(4, $response->json('partners'));
        $this->assertGreaterThanOrEqual(count($response->json('partners')), $response->json('summary.total_partners'));
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

        $ids = collect($this->getJson('/api/dashboard/structure')->assertOk()->json('partners'))->pluck('id');

        $this->assertTrue($ids->contains($partnerA->id));
        $this->assertFalse($ids->contains($partnerB->id));
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
            ->assertJsonCount(0, 'partners');
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
}

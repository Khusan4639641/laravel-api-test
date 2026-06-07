<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\BinaryTreeService;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardStructureListTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_structure_returns_downline_partners_list(): void
    {
        [$root, $left, $right, $leftChild] = $this->structure();

        Sanctum::actingAs($root);

        $response = $this->getJson('/api/dashboard/structure')
            ->assertOk()
            ->assertJsonPath('summary.total_partners', 3)
            ->assertJsonPath('summary.left_partners', 2)
            ->assertJsonPath('summary.right_partners', 1)
            ->assertJsonCount(3, 'partners');

        $this->assertEqualsCanonicalizing(
            [$left->id, $right->id, $leftChild->id],
            collect($response->json('partners'))->pluck('id')->all()
        );
    }

    public function test_dashboard_structure_list_excludes_current_user(): void
    {
        [$root] = $this->structure();

        Sanctum::actingAs($root);

        $ids = collect($this->getJson('/api/dashboard/structure')->assertOk()->json('partners'))
            ->pluck('id')
            ->all();

        $this->assertNotContains($root->id, $ids);
    }

    public function test_dashboard_structure_partners_list_includes_branch_and_line(): void
    {
        [$root, $left, $right, $leftChild] = $this->structure();

        Sanctum::actingAs($root);

        $partners = collect($this->getJson('/api/dashboard/structure')->assertOk()->json('partners'));

        $this->assertSame('left', $partners->firstWhere('id', $left->id)['branch']);
        $this->assertSame(1, $partners->firstWhere('id', $left->id)['line']);
        $this->assertSame('right', $partners->firstWhere('id', $right->id)['branch']);
        $this->assertSame(1, $partners->firstWhere('id', $right->id)['line']);
        $this->assertSame('left', $partners->firstWhere('id', $leftChild->id)['branch']);
        $this->assertSame(2, $partners->firstWhere('id', $leftChild->id)['line']);
    }

    public function test_dashboard_structure_partners_list_includes_package_and_status(): void
    {
        [$root, $left] = $this->structure();

        Sanctum::actingAs($root);

        $partner = collect($this->getJson('/api/dashboard/structure')->assertOk()->json('partners'))
            ->firstWhere('id', $left->id);

        $this->assertNotEmpty($partner['package']['code'] ?? null);
        $this->assertNotEmpty($partner['status'] ?? null);
        $this->assertNotEmpty($partner['account_status'] ?? null);
    }

    public function test_dashboard_structure_search_finds_partner_in_downline(): void
    {
        [$root, $left] = $this->structure();

        Sanctum::actingAs($root);

        $this->assertSame([$left->id], $this->idsFrom('/api/dashboard/structure?search='.$left->email));
        $this->assertContains($left->id, $this->idsFrom('/api/dashboard/structure?search='.$left->login));
        $this->assertSame([$left->id], $this->idsFrom('/api/dashboard/structure?search=7000000001'));
    }

    public function test_dashboard_structure_search_does_not_return_partner_outside_current_user_structure(): void
    {
        [$root] = $this->structure('owner');
        [, $outside] = $this->structure('outside');

        Sanctum::actingAs($root);

        $response = $this->getJson('/api/dashboard/structure?search='.$outside->email)
            ->assertOk();

        $this->assertSame(0, $response->json('meta.total'));
        $this->assertSame([], $response->json('partners'));
    }

    public function test_dashboard_structure_branch_filter_left_returns_only_left_branch(): void
    {
        [$root] = $this->structure();

        Sanctum::actingAs($root);

        $partners = collect($this->getJson('/api/dashboard/structure?branch=left')->assertOk()->json('partners'));

        $this->assertCount(2, $partners);
        $this->assertTrue($partners->every(fn (array $partner): bool => $partner['branch'] === 'left'));
    }

    public function test_dashboard_structure_branch_filter_right_returns_only_right_branch(): void
    {
        [$root] = $this->structure();

        Sanctum::actingAs($root);

        $partners = collect($this->getJson('/api/dashboard/structure?branch=right')->assertOk()->json('partners'));

        $this->assertCount(1, $partners);
        $this->assertTrue($partners->every(fn (array $partner): bool => $partner['branch'] === 'right'));
    }

    public function test_dashboard_structure_partners_list_is_not_empty_when_summary_has_partners(): void
    {
        [$root] = $this->structure();

        Sanctum::actingAs($root);

        $response = $this->getJson('/api/dashboard/structure')->assertOk();

        $this->assertGreaterThan(0, $response->json('summary.total_partners'));
        $this->assertNotEmpty($response->json('partners'));
    }

    public function test_unauthenticated_user_cannot_access_dashboard_structure(): void
    {
        $this->getJson('/api/dashboard/structure')->assertUnauthorized();
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: User}
     */
    private function structure(string $prefix = 'demo'): array
    {
        $service = app(BinaryTreeService::class);
        $root = $this->partner("{$prefix} Root", "{$prefix}_root");
        $left = $this->partner("{$prefix} Left", "{$prefix}_left", $root, '+77000000001');
        $right = $this->partner("{$prefix} Right", "{$prefix}_right", $root, '+77000000002');
        $leftChild = $this->partner("{$prefix} Left Child", "{$prefix}_left_child", $left, '+77000000003');

        $service->placeUser($root, null);
        $service->placeUser($left, $root, 'L');
        $service->placeUser($right, $root, 'R');
        $service->placeUser($leftChild, $left, 'L');

        return [$root, $left, $right, $leftChild];
    }

    private function partner(string $name, string $login, ?User $sponsor = null, ?string $phone = null): User
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
            'phone' => $phone ?: '+7700'.str_pad((string) $user->id, 7, '0', STR_PAD_LEFT),
        ]);

        return $user;
    }

    /**
     * @return array<int, int>
     */
    private function idsFrom(string $uri): array
    {
        return collect($this->getJson($uri)->assertOk()->json('partners'))
            ->pluck('id')
            ->all();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\BinaryTreeService;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardStructureListTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_structure_returns_paginated_downline_partners(): void
    {
        [$root] = $this->structureWithDescendants(25, 'page');

        Sanctum::actingAs($root);

        $this->getJson('/api/dashboard/structure?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'partners.data')
            ->assertJsonPath('partners.meta.total', 25)
            ->assertJsonPath('partners.meta.last_page', 3)
            ->assertJsonPath('partners.meta.current_page', 1)
            ->assertJsonPath('partners.meta.per_page', 10);
    }

    public function test_dashboard_structure_list_excludes_current_user(): void
    {
        [$root] = $this->structureWithDescendants(3, 'exclude_current');

        Sanctum::actingAs($root);

        $ids = $this->idsFrom('/api/dashboard/structure?per_page=50');

        $this->assertNotContains($root->id, $ids);
    }

    public function test_dashboard_structure_list_excludes_users_outside_current_structure(): void
    {
        [$root] = $this->structureWithDescendants(3, 'owner');
        [, $outsideUsers] = $this->structureWithDescendants(3, 'outside');
        $outside = $outsideUsers->first();

        Sanctum::actingAs($root);

        $response = $this->getJson('/api/dashboard/structure?search='.$outside->email)
            ->assertOk();

        $this->assertSame(0, $response->json('partners.meta.total'));
        $this->assertSame([], $response->json('partners.data'));
    }

    public function test_dashboard_structure_search_finds_descendant_by_id(): void
    {
        [$root, $users] = $this->structureWithDescendants(5, 'find_id');
        $target = $users->last();

        Sanctum::actingAs($root);

        $this->assertContains($target->id, $this->idsFrom('/api/dashboard/structure?search='.$target->id));
    }

    public function test_dashboard_structure_search_finds_descendant_by_login(): void
    {
        [$root, $users] = $this->structureWithDescendants(5, 'find_login');
        $target = $users->last();

        Sanctum::actingAs($root);

        $this->assertContains($target->id, $this->idsFrom('/api/dashboard/structure?search='.$target->login));
    }

    public function test_dashboard_structure_search_finds_descendant_by_email(): void
    {
        [$root, $users] = $this->structureWithDescendants(5, 'find_email');
        $target = $users->last();

        Sanctum::actingAs($root);

        $this->assertSame([$target->id], $this->idsFrom('/api/dashboard/structure?search='.$target->email));
    }

    public function test_dashboard_structure_search_finds_descendant_by_phone(): void
    {
        [$root, $users] = $this->structureWithDescendants(5, 'find_phone');
        $target = $users->last();

        Sanctum::actingAs($root);

        $this->assertSame([$target->id], $this->idsFrom('/api/dashboard/structure?search=77000999999'));
    }

    public function test_dashboard_structure_search_finds_descendant_by_package_label_and_code(): void
    {
        [$root, $users] = $this->structureWithDescendants(5, 'find_package');
        $target = $users->last();
        $elite = $this->package('ELITE');
        $target->forceFill(['current_package_id' => $elite->id])->save();

        Sanctum::actingAs($root);

        $this->assertContains($target->id, $this->idsFrom('/api/dashboard/structure?search=ELITE'));
        $this->assertContains($target->id, $this->idsFrom('/api/dashboard/structure?search=%D1%8D%D0%BB%D0%B8%D1%82'));
    }

    public function test_dashboard_structure_search_finds_descendant_by_status_label_and_code(): void
    {
        [$root, $users] = $this->structureWithDescendants(5, 'find_status');
        $target = $users->last();
        $target->forceFill(['status' => 'gold_director'])->save();

        Sanctum::actingAs($root);

        $this->assertContains($target->id, $this->idsFrom('/api/dashboard/structure?search=gold_director'));
        $this->assertContains($target->id, $this->idsFrom('/api/dashboard/structure?search=%D0%B7%D0%BE%D0%BB%D0%BE%D1%82%D0%BE%D0%B9'));
    }

    public function test_dashboard_structure_branch_left_returns_only_left_branch(): void
    {
        [$root] = $this->structureWithDescendants(12, 'left_filter');

        Sanctum::actingAs($root);

        $partners = collect($this->getJson('/api/dashboard/structure?branch=left&per_page=50')->assertOk()->json('partners.data'));

        $this->assertNotEmpty($partners);
        $this->assertTrue($partners->every(fn (array $partner): bool => $partner['branch'] === 'left'));
    }

    public function test_dashboard_structure_branch_right_returns_only_right_branch(): void
    {
        [$root] = $this->structureWithDescendants(12, 'right_filter');

        Sanctum::actingAs($root);

        $partners = collect($this->getJson('/api/dashboard/structure?branch=right&per_page=50')->assertOk()->json('partners.data'));

        $this->assertNotEmpty($partners);
        $this->assertTrue($partners->every(fn (array $partner): bool => $partner['branch'] === 'right'));
    }

    public function test_dashboard_structure_pagination_does_not_change_summary_totals(): void
    {
        [$root] = $this->structureWithDescendants(25, 'summary_page');

        Sanctum::actingAs($root);

        $response = $this->getJson('/api/dashboard/structure?per_page=10&page=2')
            ->assertOk();

        $this->assertSame(25, $response->json('summary.total_partners'));
        $this->assertCount(10, $response->json('partners.data'));
    }

    public function test_unauthenticated_user_cannot_access_dashboard_structure(): void
    {
        $this->getJson('/api/dashboard/structure')->assertUnauthorized();
    }

    public function test_user_cannot_see_another_users_structure_partners(): void
    {
        [$rootA, $usersA] = $this->structureWithDescendants(3, 'root_a');
        [, $usersB] = $this->structureWithDescendants(3, 'root_b');
        $outside = $usersB->first();

        Sanctum::actingAs($rootA);

        $ids = $this->idsFrom('/api/dashboard/structure?per_page=50');

        $this->assertContains($usersA->first()->id, $ids);
        $this->assertNotContains($outside->id, $ids);
    }

    public function test_dashboard_structure_excludes_staff_roles_from_downline(): void
    {
        $service = app(BinaryTreeService::class);
        $root = $this->partner('Staff Root', 'staff_root');
        $partner = $this->partner('Visible Partner', 'visible_partner');
        $staff = $this->partner('Hidden Admin', 'hidden_admin', role: User::ROLE_ADMIN);

        $service->placeUser($root, null);
        $service->placeUser($partner, $root, 'L');
        $service->placeUser($staff, $root, 'R');

        Sanctum::actingAs($root);

        $response = $this->getJson('/api/dashboard/structure?per_page=50')->assertOk();
        $ids = collect($response->json('partners.data'))->pluck('id')->all();

        $this->assertSame(1, $response->json('summary.total_partners'));
        $this->assertContains($partner->id, $ids);
        $this->assertNotContains($staff->id, $ids);
    }

    /**
     * @return array{0: User, 1: Collection<int, User>}
     */
    private function structureWithDescendants(int $count, string $prefix): array
    {
        $service = app(BinaryTreeService::class);
        $root = $this->partner("{$prefix} Root", "{$prefix}_root");
        $users = collect();

        $service->placeUser($root, null);

        foreach (range(1, $count) as $index) {
            $partner = $this->partner(
                "{$prefix} Partner {$index}",
                "{$prefix}_user_".str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                phone: $index === $count ? '+77000999999' : '+7700'.str_pad((string) $index, 7, '0', STR_PAD_LEFT),
            );

            $service->placeUser($partner, $root, $index % 2 === 0 ? 'R' : 'L');
            $users->push($partner);
        }

        return [$root, $users];
    }

    private function partner(string $name, string $login, ?User $sponsor = null, ?string $phone = null, string $role = User::ROLE_USER): User
    {
        $package = $this->package('START');

        $user = User::factory()->create([
            'name' => $name,
            'login' => $login,
            'email' => "{$login}@safilife.test",
            'role' => $role,
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

    private function package(string $code): Package
    {
        $this->seed(PackageSeeder::class);

        return Package::query()->where('code', $code)->firstOrFail();
    }

    /**
     * @return array<int, int>
     */
    private function idsFrom(string $uri): array
    {
        return collect($this->getJson($uri)->assertOk()->json('partners.data'))
            ->pluck('id')
            ->all();
    }
}

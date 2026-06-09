<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\User;
use App\Services\PartnerDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerCreateStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_single_create_places_user_into_selected_sponsor_left_tree(): void
    {
        $sponsor = $this->partner('structure-left-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $user = $this->createAdminPartner('structure-left-test1', $sponsor, 'left');
        $response = $this->getJson("/api/admin/structure?user_id={$sponsor->id}")
            ->assertOk();
        $node = $this->nodeByLogin($response, 'structure-left-test1');

        $this->assertNotNull($node);
        $this->assertSame($sponsor->id, $user->sponsor_id);
        $this->assertNull($user->current_package_id);
        $this->assertSame('structure-left-test1', $node['login']);
        $this->assertSame('-', $node['package_label']);
        $this->assertSame(0, (int) $node['personal_pv']);
        $this->assertSame($user->id, $this->sideChild($sponsor, 'left')?->id);
    }

    public function test_admin_single_create_places_user_without_package_into_tree(): void
    {
        $sponsor = $this->partner('structure-no-package-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $user = $this->createAdminPartner('structure-no-package', $sponsor, 'left', ['package_id' => null]);
        $response = $this->getJson("/api/admin/structure?user_id={$sponsor->id}")
            ->assertOk();
        $node = $this->nodeByLogin($response, 'structure-no-package');

        $this->assertNotNull($node);
        $this->assertNull($user->current_package_id);
        $this->assertNull($node['package']);
        $this->assertSame('-', $node['package_label']);
        $this->assertSame(0, (int) $node['personal_pv']);
        $this->assertSame('0.00', (string) $node['left_pv']);
        $this->assertSame('0.00', (string) $node['right_pv']);
    }

    public function test_admin_single_create_left_chain_four_users_appears_in_tree(): void
    {
        $sponsor = $this->partner('structure-left-chain-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $users = collect(range(1, 4))
            ->map(fn (int $index): User => $this->createAdminPartner("structure-left-chain-{$index}", $sponsor, 'left'))
            ->all();

        $response = $this->getJson("/api/admin/structure?user_id={$sponsor->id}")
            ->assertOk()
            ->assertJsonPath('summary.right_count', 0);

        $this->assertSideChain($sponsor, $users, 'left');
        $this->assertNotNull($this->nodeByLogin($response, 'structure-left-chain-4'));
        $this->assertSame(4, $response->json('summary.left_count'));
    }

    public function test_admin_single_create_right_chain_four_users_appears_in_tree(): void
    {
        $sponsor = $this->partner('structure-right-chain-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $users = collect(range(1, 4))
            ->map(fn (int $index): User => $this->createAdminPartner("structure-right-chain-{$index}", $sponsor, 'right'))
            ->all();

        $response = $this->getJson("/api/admin/structure?user_id={$sponsor->id}")
            ->assertOk()
            ->assertJsonPath('summary.left_count', 0);

        $this->assertSideChain($sponsor, $users, 'right');
        $this->assertNotNull($this->nodeByLogin($response, 'structure-right-chain-4'));
        $this->assertSame(4, $response->json('summary.right_count'));
    }

    public function test_admin_structure_does_not_filter_users_without_package(): void
    {
        $sponsor = $this->partner('structure-filter-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $this->createAdminPartner('structure-filter-no-package-1', $sponsor, 'left');
        $this->createAdminPartner('structure-filter-no-package-2', $sponsor, 'right');

        $response = $this->getJson("/api/admin/structure?user_id={$sponsor->id}")
            ->assertOk();

        $this->assertNotNull($this->nodeByLogin($response, 'structure-filter-no-package-1'));
        $this->assertNotNull($this->nodeByLogin($response, 'structure-filter-no-package-2'));
        $this->assertSame(2, $response->json('summary.total_structure_count'));
    }

    public function test_summary_count_matches_actual_active_tree_nodes(): void
    {
        $sponsor = $this->partner('structure-summary-sponsor');
        Sanctum::actingAs($this->superAdmin());

        foreach (range(1, 3) as $index) {
            $this->createAdminPartner("structure-summary-left-{$index}", $sponsor, 'left');
        }
        foreach (range(1, 2) as $index) {
            $this->createAdminPartner("structure-summary-right-{$index}", $sponsor, 'right');
        }

        $response = $this->getJson("/api/admin/structure?user_id={$sponsor->id}&include_flat=true")
            ->assertOk();

        $this->assertSame(5, $response->json('summary.total_structure_count'));
        $this->assertSame(5, count($response->json('flat')));
    }

    public function test_admin_structure_default_depth_includes_new_users_up_to_depth_ten(): void
    {
        $sponsor = $this->partner('structure-depth-sponsor');
        Sanctum::actingAs($this->superAdmin());

        foreach (range(1, 10) as $index) {
            $this->createAdminPartner("structure-depth-{$index}", $sponsor, 'left');
        }

        $response = $this->getJson("/api/admin/structure?user_id={$sponsor->id}")
            ->assertOk()
            ->assertJsonPath('depth', 10)
            ->assertJsonPath('has_deeper_nodes', false);

        $this->assertNotNull($this->nodeByLogin($response, 'structure-depth-10'));
        $this->assertSame(10, $response->json('summary.left_count'));
    }

    public function test_search_by_login_finds_node_inside_sponsor_structure(): void
    {
        $sponsor = $this->partner('structure-search-sponsor');
        Sanctum::actingAs($this->superAdmin());

        foreach (range(1, 4) as $index) {
            $this->createAdminPartner("structure-search-test{$index}", $sponsor, 'left');
        }

        $searchResponse = $this->getJson('/api/admin/partners/search?q=structure-search-test4')
            ->assertOk();
        $treeResponse = $this->getJson("/api/admin/structure?user_id={$sponsor->id}&include_flat=true")
            ->assertOk();

        $this->assertContains('structure-search-test4', collect($searchResponse->json('partners'))->pluck('login')->all());
        $this->assertNotNull($this->nodeByLogin($treeResponse, 'structure-search-test4', 'flat'));
    }

    public function test_soft_deleted_users_are_excluded_from_tree(): void
    {
        $admin = $this->superAdmin();
        $sponsor = $this->partner('structure-delete-sponsor');
        Sanctum::actingAs($admin);

        $deleted = $this->createAdminPartner('structure-delete-user', $sponsor, 'left');

        app(PartnerDeletionService::class)->deletePartner(
            $deleted,
            $admin,
            false,
            'Тестовое удаление из структуры',
        );

        $response = $this->getJson("/api/admin/structure?user_id={$sponsor->id}")
            ->assertOk();

        $this->assertNull($this->nodeByLogin($response, 'structure-delete-user'));
        $this->assertSame(0, $response->json('summary.total_structure_count'));
    }

    public function test_invalid_branch_returns_422(): void
    {
        $sponsor = $this->partner('structure-invalid-branch-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/admin/partners', $this->payload('structure-invalid-branch', $sponsor, 'middle'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch']);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    private function partner(string $login): User
    {
        return User::factory()->create([
            'role' => User::ROLE_USER,
            'login' => $login,
            'email' => "{$login}@example.test",
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAdminPartner(string $login, User $sponsor, string $branch, array $overrides = []): User
    {
        $this->postJson('/api/admin/partners', $this->payload($login, $sponsor, $branch, $overrides))
            ->assertCreated()
            ->assertJsonPath('placement.root_branch', $branch);

        return User::query()->where('login', $login)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $login, User $sponsor, string $branch, array $overrides = []): array
    {
        return [
            'name' => "Structure {$login}",
            'login' => $login,
            'email' => "{$login}@example.test",
            'phone' => '+7702'.str_pad((string) (abs(crc32($login)) % 10000000), 7, '0', STR_PAD_LEFT),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'sponsor_id' => $sponsor->id,
            'branch' => $branch,
            'role' => User::ROLE_USER,
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nodeByLogin($response, string $login, string $key = 'nodes'): ?array
    {
        return collect($response->json($key) ?? [])
            ->first(fn (array $node): bool => ($node['login'] ?? null) === $login);
    }

    /**
     * @param  array<int, User>  $users
     */
    private function assertSideChain(User $sponsor, array $users, string $branch): void
    {
        $current = $sponsor;
        $oppositeBranch = $branch === 'left' ? 'right' : 'left';

        foreach ($users as $expectedUser) {
            $actualChild = $this->sideChild($current, $branch);

            $this->assertNotNull($actualChild);
            $this->assertSame($expectedUser->id, $actualChild->id);
            $this->assertNull($this->sideChild($current, $oppositeBranch));

            $current = $expectedUser;
        }
    }

    private function sideChild(User $parent, string $branch): ?User
    {
        $node = $parent->binaryNode()
            ->where('is_active', true)
            ->first();

        if (! $node) {
            return null;
        }

        $position = $branch === 'left' ? 'L' : 'R';
        $childNode = $node->children()
            ->where('position', $position)
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->with('user')
            ->first();

        return $childNode?->user;
    }
}

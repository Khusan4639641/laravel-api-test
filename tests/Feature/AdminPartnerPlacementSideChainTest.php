<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\Package;
use App\Models\User;
use App\Services\PartnerDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerPlacementSideChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_single_create_left_chain(): void
    {
        $sponsor = $this->partner('left-chain-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $users = collect(range(1, 4))
            ->map(fn (int $index): User => $this->createAdminPartner("left-chain-{$index}", $sponsor, 'left'))
            ->all();

        $this->assertSideChain($sponsor, $users, 'left');
        $this->assertNull($this->sideChild($sponsor, 'right'));
        $this->assertSame(4, $this->branchCount($sponsor, 'left'));
        $this->assertSame(0, $this->branchCount($sponsor, 'right'));
    }

    public function test_admin_single_create_right_chain(): void
    {
        $sponsor = $this->partner('right-chain-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $users = collect(range(1, 4))
            ->map(fn (int $index): User => $this->createAdminPartner("right-chain-{$index}", $sponsor, 'right'))
            ->all();

        $this->assertSideChain($sponsor, $users, 'right');
        $this->assertNull($this->sideChild($sponsor, 'left'));
        $this->assertSame(0, $this->branchCount($sponsor, 'left'));
        $this->assertSame(4, $this->branchCount($sponsor, 'right'));
    }

    public function test_fourth_left_user_does_not_move_to_right(): void
    {
        $sponsor = $this->partner('fourth-left-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $users = [];
        foreach (range(1, 3) as $index) {
            $users[] = $this->createAdminPartner("fourth-left-{$index}", $sponsor, 'left');
        }

        $response = $this->postJson('/api/admin/partners', $this->payload('fourth-left-4', $sponsor, 'left'))
            ->assertCreated()
            ->assertJsonPath('root_branch', 'left')
            ->assertJsonPath('placement.root_branch', 'left');

        $users[] = User::query()->where('login', 'fourth-left-4')->firstOrFail();

        $this->assertSame('left', $response->json('root_branch'));
        $this->assertSideChain($sponsor, $users, 'left');
        $this->assertSame(0, $this->branchCount($sponsor, 'right'));
    }

    public function test_fourth_right_user_does_not_move_to_left(): void
    {
        $sponsor = $this->partner('fourth-right-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $users = [];
        foreach (range(1, 3) as $index) {
            $users[] = $this->createAdminPartner("fourth-right-{$index}", $sponsor, 'right');
        }

        $response = $this->postJson('/api/admin/partners', $this->payload('fourth-right-4', $sponsor, 'right'))
            ->assertCreated()
            ->assertJsonPath('root_branch', 'right')
            ->assertJsonPath('placement.root_branch', 'right');

        $users[] = User::query()->where('login', 'fourth-right-4')->firstOrFail();

        $this->assertSame('right', $response->json('root_branch'));
        $this->assertSideChain($sponsor, $users, 'right');
        $this->assertSame(0, $this->branchCount($sponsor, 'left'));
    }

    public function test_bulk_create_left_chain(): void
    {
        $sponsor = $this->partner('bulk-left-chain-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $response = $this->postJson('/api/admin/partners/bulk-create', [
            'partners' => collect(range(1, 5))
                ->map(fn (int $index): array => $this->payload("bulk-left-chain-{$index}", $sponsor, 'left'))
                ->all(),
        ])
            ->assertOk()
            ->assertJsonCount(5, 'created')
            ->assertJsonCount(0, 'failed');

        foreach ($response->json('created') as $created) {
            $this->assertSame('left', $created['root_branch']);
            $this->assertSame('left', $created['placement']['root_branch']);
        }

        $users = collect(range(1, 5))
            ->map(fn (int $index): User => User::query()->where('login', "bulk-left-chain-{$index}")->firstOrFail())
            ->all();

        $this->assertSideChain($sponsor, $users, 'left');
        $this->assertSame(0, $this->branchCount($sponsor, 'right'));
    }

    public function test_bulk_create_right_chain(): void
    {
        $sponsor = $this->partner('bulk-right-chain-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $response = $this->postJson('/api/admin/partners/bulk-create', [
            'partners' => collect(range(1, 5))
                ->map(fn (int $index): array => $this->payload("bulk-right-chain-{$index}", $sponsor, 'right'))
                ->all(),
        ])
            ->assertOk()
            ->assertJsonCount(5, 'created')
            ->assertJsonCount(0, 'failed');

        foreach ($response->json('created') as $created) {
            $this->assertSame('right', $created['root_branch']);
            $this->assertSame('right', $created['placement']['root_branch']);
        }

        $users = collect(range(1, 5))
            ->map(fn (int $index): User => User::query()->where('login', "bulk-right-chain-{$index}")->firstOrFail())
            ->all();

        $this->assertSideChain($sponsor, $users, 'right');
        $this->assertSame(0, $this->branchCount($sponsor, 'left'));
    }

    public function test_register_ref_branch_left_respects_left_chain(): void
    {
        $sponsor = $this->partner('ref-left-chain-sponsor');

        foreach (range(1, 4) as $index) {
            $this->postJson('/api/register', $this->registrationPayload("ref-left-chain-{$index}", $sponsor, 'left'))
                ->assertCreated();
        }

        $users = collect(range(1, 4))
            ->map(fn (int $index): User => User::query()->where('login', "ref-left-chain-{$index}")->firstOrFail())
            ->all();

        $this->assertSideChain($sponsor, $users, 'left');
        $this->assertSame(0, $this->branchCount($sponsor, 'right'));
    }

    public function test_register_ref_branch_right_respects_right_chain(): void
    {
        $sponsor = $this->partner('ref-right-chain-sponsor');

        foreach (range(1, 4) as $index) {
            $this->postJson('/api/register', $this->registrationPayload("ref-right-chain-{$index}", $sponsor, 'right'))
                ->assertCreated();
        }

        $users = collect(range(1, 4))
            ->map(fn (int $index): User => User::query()->where('login', "ref-right-chain-{$index}")->firstOrFail())
            ->all();

        $this->assertSideChain($sponsor, $users, 'right');
        $this->assertSame(0, $this->branchCount($sponsor, 'left'));
    }

    public function test_invalid_branch_returns_422(): void
    {
        $sponsor = $this->partner('invalid-side-chain-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/admin/partners', $this->payload('invalid-side-chain-user', $sponsor, 'middle'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch']);
    }

    public function test_missing_branch_returns_422_when_referral_is_used(): void
    {
        $sponsor = $this->partner('missing-ref-chain-sponsor');

        $payload = $this->registrationPayload('missing-ref-chain-user', $sponsor, 'left');
        unset($payload['branch']);

        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch']);
    }

    public function test_soft_deleted_sponsor_cannot_receive_placement(): void
    {
        $sponsor = $this->partner('deleted-side-chain-sponsor');
        $sponsor->delete();
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/admin/partners', $this->payload('under-deleted-sponsor', $sponsor, 'left'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sponsor_id']);
    }

    public function test_soft_deleted_nodes_are_not_used_as_placement_parent(): void
    {
        $admin = $this->superAdmin();
        $sponsor = $this->partner('deleted-node-chain-sponsor');
        Sanctum::actingAs($admin);

        $deletedChild = $this->createAdminPartner('deleted-node-chain-child', $sponsor, 'left');
        $deletedNodeId = $this->activeNode($deletedChild)->id;

        app(PartnerDeletionService::class)->deletePartner(
            $deletedChild,
            $admin,
            false,
            'Тест удаления перед side-chain placement',
        );

        $newChild = $this->createAdminPartner('after-deleted-node-chain', $sponsor, 'left');

        $this->assertNotSame($deletedNodeId, $this->activeNode($newChild)->parent_id);
        $this->assertSame($sponsor->id, $this->sideChild($sponsor, 'left')?->sponsor_id);
        $this->assertSame($newChild->id, $this->sideChild($sponsor, 'left')?->id);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    private function partner(string $login): User
    {
        $package = $this->package();

        return User::factory()->create([
            'role' => User::ROLE_USER,
            'login' => $login,
            'email' => "{$login}@example.test",
            'current_package_id' => $package->id,
        ]);
    }

    private function package(): Package
    {
        return Package::query()->create([
            'code' => 'START',
            'name' => 'START',
            'slug' => 'start-'.uniqid(),
            'price' => 60000,
            'pv' => 100,
            'activity_pv' => 100,
            'turnover_pv' => 100,
            'referral_percent' => 10,
            'binary_percent' => 7,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }

    private function createAdminPartner(string $login, User $sponsor, string $branch): User
    {
        $response = $this->postJson('/api/admin/partners', $this->payload($login, $sponsor, $branch))
            ->assertCreated()
            ->assertJsonPath('root_branch', $branch)
            ->assertJsonPath('placement.root_branch', $branch);

        $user = User::query()->where('login', $login)->firstOrFail();

        $this->assertSame($branch, $response->json('root_branch'));
        $this->assertSame($branch, $response->json('placement.root_branch'));
        $this->assertSame($sponsor->id, $user->sponsor_id);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $login, User $sponsor, string $branch): array
    {
        return [
            'name' => "Placement {$login}",
            'login' => $login,
            'email' => "{$login}@example.test",
            'phone' => '+7700'.str_pad((string) (abs(crc32($login)) % 10000000), 7, '0', STR_PAD_LEFT),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'sponsor_id' => $sponsor->id,
            'branch' => $branch,
            'role' => User::ROLE_USER,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationPayload(string $login, User $sponsor, string $branch): array
    {
        return [
            'name' => "Referral {$login}",
            'login' => $login,
            'email' => "{$login}@example.test",
            'phone' => '+7701'.str_pad((string) (abs(crc32($login)) % 10000000), 7, '0', STR_PAD_LEFT),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => $sponsor->login,
            'branch' => $branch,
        ];
    }

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

    private function activeNode(User $user): BinaryNode
    {
        return $user->binaryNode()
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function branchCount(User $sponsor, string $branch): int
    {
        $sponsorNode = $this->activeNode($sponsor);
        $position = $branch === 'left' ? 'L' : 'R';
        $root = $sponsorNode->children()
            ->where('position', $position)
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->first();

        if (! $root) {
            return 0;
        }

        return BinaryNode::query()
            ->where(function ($query) use ($root): void {
                $query->whereKey($root->id)
                    ->orWhere('path', 'like', $root->path.'.%');
            })
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->count();
    }
}

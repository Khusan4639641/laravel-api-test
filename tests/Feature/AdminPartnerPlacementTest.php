<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\User;
use App\Services\PartnerDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerPlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_single_create_respects_left_branch_repeatedly(): void
    {
        $sponsor = $this->partner('left-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $created = collect(range(1, 4))
            ->map(fn (int $index): User => $this->createAdminPartner("left-partner-{$index}", $sponsor, 'left'));

        $created->each(function (User $user) use ($sponsor): void {
            $this->assertInsideSponsorBranch($sponsor, $user, 'left');
        });
        $this->assertSame(4, $this->branchCount($sponsor, 'left'));
        $this->assertSame(0, $this->branchCount($sponsor, 'right'));
    }

    public function test_admin_single_create_respects_right_branch_repeatedly(): void
    {
        $sponsor = $this->partner('right-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $created = collect(range(1, 4))
            ->map(fn (int $index): User => $this->createAdminPartner("right-partner-{$index}", $sponsor, 'right'));

        $created->each(function (User $user) use ($sponsor): void {
            $this->assertInsideSponsorBranch($sponsor, $user, 'right');
        });
        $this->assertSame(0, $this->branchCount($sponsor, 'left'));
        $this->assertSame(4, $this->branchCount($sponsor, 'right'));
    }

    public function test_fourth_left_partner_does_not_auto_move_to_right(): void
    {
        $sponsor = $this->partner('bug-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $this->createAdminPartner('bug-left-1', $sponsor, 'left');
        $this->createAdminPartner('bug-left-2', $sponsor, 'left');
        $this->createAdminPartner('bug-left-3', $sponsor, 'left');

        $response = $this->postJson('/api/admin/partners', $this->payload('bug-left-4', $sponsor, 'left'))
            ->assertCreated()
            ->assertJsonPath('root_branch', 'left');

        $fourth = User::query()->where('login', 'bug-left-4')->firstOrFail();

        $this->assertSame('left', $response->json('root_branch'));
        $this->assertInsideSponsorBranch($sponsor, $fourth, 'left');
        $this->assertSame(0, $this->branchCount($sponsor, 'right'));
    }

    public function test_placement_uses_bfs_inside_selected_branch(): void
    {
        $sponsor = $this->partner('bfs-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $rightRoot = $this->createAdminPartner('bfs-right-root', $sponsor, 'right');
        $leftRoot = $this->createAdminPartner('bfs-left-1', $sponsor, 'left');
        $leftSecond = $this->createAdminPartner('bfs-left-2', $sponsor, 'left');
        $leftThird = $this->createAdminPartner('bfs-left-3', $sponsor, 'left');
        $leftFourth = $this->createAdminPartner('bfs-left-4', $sponsor, 'left');

        $this->assertSame($this->activeNode($leftRoot)->id, $this->activeNode($leftSecond)->parent_id);
        $this->assertSame('L', $this->activeNode($leftSecond)->position);
        $this->assertSame($this->activeNode($leftRoot)->id, $this->activeNode($leftThird)->parent_id);
        $this->assertSame('R', $this->activeNode($leftThird)->position);
        $this->assertSame($this->activeNode($leftSecond)->id, $this->activeNode($leftFourth)->parent_id);
        $this->assertSame('L', $this->activeNode($leftFourth)->position);
        $this->assertNotSame($this->activeNode($rightRoot)->id, $this->activeNode($leftFourth)->parent_id);
        $this->assertInsideSponsorBranch($sponsor, $leftFourth, 'left');
    }

    public function test_bulk_create_respects_selected_branch(): void
    {
        $sponsor = $this->partner('bulk-placement-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $response = $this->postJson('/api/admin/partners/bulk-create', [
            'partners' => collect(range(1, 5))
                ->map(fn (int $index): array => $this->payload("bulk-left-{$index}", $sponsor, 'left'))
                ->all(),
        ])
            ->assertOk()
            ->assertJsonCount(5, 'created')
            ->assertJsonCount(0, 'failed');

        foreach ($response->json('created') as $created) {
            $this->assertSame('left', $created['root_branch']);
            $user = User::query()->where('login', $created['credentials']['login'])->firstOrFail();
            $this->assertInsideSponsorBranch($sponsor, $user, 'left');
        }

        $this->assertSame(5, $this->branchCount($sponsor, 'left'));
        $this->assertSame(0, $this->branchCount($sponsor, 'right'));
    }

    public function test_referral_registration_respects_branch_param_repeatedly(): void
    {
        $sponsor = $this->partner('ref-placement-sponsor');

        foreach (range(1, 4) as $index) {
            $this->postJson('/api/register', $this->registrationPayload("ref-left-{$index}", $sponsor, 'left'))
                ->assertCreated();

            $user = User::query()->where('login', "ref-left-{$index}")->firstOrFail();
            $this->assertInsideSponsorBranch($sponsor, $user, 'left');
        }

        $this->assertSame(4, $this->branchCount($sponsor, 'left'));
        $this->assertSame(0, $this->branchCount($sponsor, 'right'));
    }

    public function test_invalid_branch_returns_validation_error(): void
    {
        $sponsor = $this->partner('invalid-branch-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/admin/partners', $this->payload('invalid-branch-user', $sponsor, 'middle'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch']);
    }

    public function test_branch_missing_returns_validation_error_when_sponsor_selected(): void
    {
        $sponsor = $this->partner('missing-branch-sponsor');
        Sanctum::actingAs($this->superAdmin());

        $payload = $this->payload('missing-branch-user', $sponsor, 'left');
        unset($payload['branch']);

        $this->postJson('/api/admin/partners', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch']);
    }

    public function test_sponsor_search_excludes_deleted_users(): void
    {
        Sanctum::actingAs($this->superAdmin());
        $deleted = $this->partner('deleted-sponsor-search');
        $deleted->delete();

        $response = $this->getJson('/api/admin/partners/search?q=deleted-sponsor-search')
            ->assertOk();

        $this->assertNotContains($deleted->id, collect($response->json('partners'))->pluck('id')->all());
    }

    public function test_placement_does_not_attach_under_soft_deleted_node(): void
    {
        $admin = $this->superAdmin();
        $sponsor = $this->partner('deleted-node-sponsor');
        Sanctum::actingAs($admin);

        $deletedChild = $this->createAdminPartner('deleted-node-child', $sponsor, 'left');
        $deletedNodeId = $this->activeNode($deletedChild)->id;

        app(PartnerDeletionService::class)->deletePartner(
            $deletedChild,
            $admin,
            false,
            'Тест удаления перед placement',
        );

        $newChild = $this->createAdminPartner('after-deleted-node', $sponsor, 'left');

        $this->assertNotSame($deletedNodeId, $this->activeNode($newChild)->parent_id);
        $this->assertInsideSponsorBranch($sponsor, $newChild, 'left');
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

    private function createAdminPartner(string $login, User $sponsor, string $branch): User
    {
        $response = $this->postJson('/api/admin/partners', $this->payload($login, $sponsor, $branch))
            ->assertCreated()
            ->assertJsonPath('root_branch', $branch);

        $user = User::query()->where('login', $login)->firstOrFail();

        $this->assertSame($branch, $response->json('root_branch'));
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

    private function activeNode(User $user): BinaryNode
    {
        return $user->binaryNode()
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function assertInsideSponsorBranch(User $sponsor, User $user, string $branch): void
    {
        $this->assertSame($branch, $this->rootBranchFor($sponsor, $user));
    }

    private function rootBranchFor(User $sponsor, User $user): ?string
    {
        $sponsorNode = $this->activeNode($sponsor);
        $current = $this->activeNode($user);

        while ($current && (int) $current->parent_id !== (int) $sponsorNode->id) {
            if ((int) $current->id === (int) $sponsorNode->id) {
                return null;
            }

            $current = $current->parent()->where('is_active', true)->first();
        }

        return match ($current?->position) {
            'L' => 'left',
            'R' => 'right',
            default => null,
        };
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

<?php

namespace Tests\Support;

use App\Models\BinaryNode;
use App\Models\Package;
use App\Models\PvTransaction;
use App\Models\User;

trait CreatesBinaryBonusEligibility
{
    /**
     * @return array{left: User, right: User}
     */
    protected function makeBinaryBonusEligible(User $user): array
    {
        $leftReferral = $this->makeDirectReferralInBinaryBranch($user, 'L');
        $rightReferral = $this->makeDirectReferralInBinaryBranch($user, 'R');
        $this->seedCachedBranchPvTransaction($user, $leftReferral, 'L');
        $this->seedCachedBranchPvTransaction($user, $rightReferral, 'R');

        return [
            'left' => $leftReferral,
            'right' => $rightReferral,
        ];
    }

    protected function makeDirectReferralInBinaryBranch(User $user, string $position): User
    {
        $sponsorNode = $this->ensureBinaryNode($user);

        $referral = User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'sponsor_id' => $user->id,
            'current_package_id' => $this->binaryEligibilityPackage()->id,
        ]);

        $this->createChildNode($sponsorNode, $referral, $position);

        return $referral;
    }

    protected function ensureBinaryNode(User $user): BinaryNode
    {
        $node = $user->binaryNode()->where('is_active', true)->first();

        if ($node) {
            return $node;
        }

        return BinaryNode::query()->create([
            'user_id' => $user->id,
            'parent_id' => null,
            'position' => null,
            'depth' => 0,
            'path' => (string) $user->id,
            'is_active' => true,
        ]);
    }

    protected function createChildNode(BinaryNode $parentNode, User $user, string $position): BinaryNode
    {
        return BinaryNode::query()->create([
            'user_id' => $user->id,
            'parent_id' => $parentNode->id,
            'position' => $position,
            'depth' => $parentNode->depth + 1,
            'path' => trim($parentNode->path.'.'.$user->id, '.'),
            'is_active' => true,
        ]);
    }

    private function seedCachedBranchPvTransaction(User $user, User $buyer, string $branch): void
    {
        $leftBranch = $branch === 'L';
        $cachedPv = (string) ($leftBranch ? $user->left_pv : $user->right_pv);
        $remainingPv = (string) ($leftBranch ? $user->remaining_left_pv : $user->remaining_right_pv);
        $pv = bccomp($cachedPv, $remainingPv, 2) >= 0 ? $cachedPv : $remainingPv;

        if (bccomp($pv, '0', 2) <= 0) {
            return;
        }

        $exists = PvTransaction::query()
            ->where('upline_id', $user->id)
            ->where('buyer_id', $buyer->id)
            ->where('branch', $branch)
            ->where('source', 'test_cached_branch_pv')
            ->exists();

        if ($exists) {
            return;
        }

        PvTransaction::query()->create([
            'buyer_id' => $buyer->id,
            'upline_id' => $user->id,
            'source' => 'test_cached_branch_pv',
            'branch' => $branch,
            'pv' => $pv,
            'is_bonusable' => true,
        ]);
    }

    private function binaryEligibilityPackage(): Package
    {
        return Package::query()->firstOrCreate(
            ['code' => 'START'],
            [
                'name' => 'START',
                'slug' => 'start',
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
            ],
        );
    }
}

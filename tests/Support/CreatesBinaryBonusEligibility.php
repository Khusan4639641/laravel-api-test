<?php

namespace Tests\Support;

use App\Models\BinaryNode;
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
}

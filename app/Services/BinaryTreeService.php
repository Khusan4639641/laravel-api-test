<?php

namespace App\Services;

use App\Models\BinaryNode;
use App\Models\User;
use InvalidArgumentException;

class BinaryTreeService
{
    public function placeUser(User $user, ?User $sponsor = null, ?string $preferredPosition = null): BinaryNode
    {
        if ($user->binaryNode()->where('is_active', true)->exists()) {
            throw new InvalidArgumentException('User is already placed in the binary tree.');
        }

        if (! $sponsor) {
            return BinaryNode::query()->create([
                'user_id' => $user->id,
                'parent_id' => null,
                'position' => null,
                'depth' => 0,
                'path' => (string) $user->id,
                'is_active' => true,
            ]);
        }

        $position = $this->normalizeRequiredPosition($preferredPosition);

        if ($sponsor->trashed() || $sponsor->account_status !== 'active' || ! in_array($sponsor->role, [User::ROLE_USER, User::ROLE_SUPER_ADMIN], true)) {
            throw new InvalidArgumentException('Sponsor is not available.');
        }

        $sponsorNode = $sponsor->binaryNode()->where('is_active', true)->first();

        if (! $sponsorNode) {
            $sponsorNode = BinaryNode::query()->create([
                'user_id' => $sponsor->id,
                'parent_id' => null,
                'position' => null,
                'depth' => 0,
                'path' => (string) $sponsor->id,
                'is_active' => true,
            ]);
        }

        $parentNode = $this->findSpilloverPosition($sponsor->fresh(), $position);

        if (! $parentNode) {
            throw new InvalidArgumentException('Unable to find a free binary tree position.');
        }

        $childPosition = $this->resolveFreeChildPosition($parentNode, $position);

        return BinaryNode::query()->create([
            'user_id' => $user->id,
            'parent_id' => $parentNode->id,
            'position' => $childPosition,
            'depth' => $parentNode->depth + 1,
            'path' => trim($parentNode->path.'.'.$user->id, '.'),
            'is_active' => true,
        ]);
    }

    public function placeUnderSponsor(User $newUser, User $sponsor, string $branch): BinaryNode
    {
        return $this->placeUser($newUser, $sponsor, $branch);
    }

    public function findSpilloverPosition(User $sponsor, ?string $preferredPosition = null): ?BinaryNode
    {
        return $this->findDeepestSlotBySelectedSide($sponsor, (string) $preferredPosition);
    }

    public function findDeepestSlotBySelectedSide(User $sponsor, string $branch): ?BinaryNode
    {
        if ($sponsor->trashed() || $sponsor->account_status !== 'active') {
            return null;
        }

        $position = $this->normalizeRequiredPosition($branch);
        $currentNode = $sponsor->binaryNode()->where('is_active', true)->first();

        if (! $currentNode) {
            return null;
        }

        while (true) {
            if ($this->slotIsFree($currentNode, $position)) {
                return $currentNode;
            }

            $nextNode = $this->childAt($currentNode, $position);

            if (! $nextNode) {
                return null;
            }

            $currentNode = $nextNode;
        }
    }

    public function hasFreePosition(BinaryNode $node, ?string $position = null): bool
    {
        if ($position !== null) {
            return $this->slotIsFree($node, $this->normalizePosition($position));
        }

        return $this->slotIsFree($node, 'L') || $this->slotIsFree($node, 'R');
    }

    public function findFirstAvailableSlotInBranch(User $sponsor, string $branch): ?BinaryNode
    {
        return $this->findDeepestSlotBySelectedSide($sponsor, $branch);
    }

    private function resolveFreeChildPosition(BinaryNode $node, string $preferredPosition): string
    {
        if ($this->hasFreePosition($node, $preferredPosition)) {
            return $preferredPosition;
        }

        throw new InvalidArgumentException('Selected side-chain has no free child position.');
    }

    private function childAt(BinaryNode $node, string $position): ?BinaryNode
    {
        return $node->children()
            ->where('position', $position)
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->first();
    }

    private function slotIsFree(BinaryNode $node, string $position): bool
    {
        return ! BinaryNode::withTrashed()
            ->where('parent_id', $node->id)
            ->where('position', $position)
            ->exists();
    }

    private function normalizePosition(?string $position): string
    {
        if ($position === null || trim($position) === '') {
            throw new InvalidArgumentException('Binary position must be L or R.');
        }

        $position = strtoupper($position);

        return match ($position) {
            'L', 'LEFT' => 'L',
            'R', 'RIGHT' => 'R',
            default => throw new InvalidArgumentException('Binary position must be L or R.'),
        };
    }

    private function normalizeRequiredPosition(?string $position): string
    {
        return $this->normalizePosition($position);
    }
}

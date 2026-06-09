<?php

namespace App\Services;

use App\Models\BinaryNode;
use App\Models\User;
use Illuminate\Support\Collection;
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

    public function findSpilloverPosition(User $sponsor, ?string $preferredPosition = null): ?BinaryNode
    {
        if ($sponsor->trashed() || $sponsor->account_status !== 'active') {
            return null;
        }

        $position = $this->normalizeRequiredPosition($preferredPosition);
        $sponsorNode = $sponsor->binaryNode()->where('is_active', true)->first();

        if (! $sponsorNode) {
            return null;
        }

        if ($this->hasFreePosition($sponsorNode, $position)) {
            return $sponsorNode;
        }

        $branchRoot = $this->childAt($sponsorNode, $position);

        if (! $branchRoot) {
            return null;
        }

        /** @var Collection<int, BinaryNode> $queue */
        $queue = collect([$branchRoot]);

        while ($queue->isNotEmpty()) {
            /** @var BinaryNode $node */
            $node = $queue->shift();

            if ($this->hasFreePosition($node)) {
                return $node;
            }

            $children = $node->children()
                ->where('is_active', true)
                ->whereHas('user', fn ($query) => $query->activeAccount())
                ->orderByRaw("case position when 'L' then 0 when 'R' then 1 else 2 end")
                ->get();

            $queue = $queue->merge($children);
        }

        return null;
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
        return $this->findSpilloverPosition($sponsor, $branch);
    }

    private function resolveFreeChildPosition(BinaryNode $node, string $preferredPosition): string
    {
        if ($this->hasFreePosition($node, $preferredPosition)) {
            return $preferredPosition;
        }

        if ($this->hasFreePosition($node, 'L')) {
            return 'L';
        }

        if ($this->hasFreePosition($node, 'R')) {
            return 'R';
        }

        throw new InvalidArgumentException('Binary node has no free child position.');
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

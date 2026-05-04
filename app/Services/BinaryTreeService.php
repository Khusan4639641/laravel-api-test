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
        if ($user->binaryNode()->exists()) {
            throw new InvalidArgumentException('User is already placed in the binary tree.');
        }

        $position = $this->normalizePosition($preferredPosition);

        if (! $sponsor) {
            return BinaryNode::query()->create([
                'user_id' => $user->id,
                'parent_id' => null,
                'position' => null,
                'depth' => 0,
                'path' => (string) $user->id,
            ]);
        }

        $sponsorNode = $sponsor->binaryNode()->first();

        if (! $sponsorNode) {
            $sponsorNode = BinaryNode::query()->create([
                'user_id' => $sponsor->id,
                'parent_id' => null,
                'position' => null,
                'depth' => 0,
                'path' => (string) $sponsor->id,
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
        ]);
    }

    public function findSpilloverPosition(User $sponsor, ?string $preferredPosition = null): ?BinaryNode
    {
        $position = $this->normalizePosition($preferredPosition);
        $sponsorNode = $sponsor->binaryNode()->first();

        if (! $sponsorNode) {
            return null;
        }

        if ($this->hasFreePosition($sponsorNode, $position)) {
            return $sponsorNode;
        }

        $branchRoot = $this->childAt($sponsorNode, $position);

        if (! $branchRoot) {
            return $sponsorNode;
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
                ->orderByRaw("case position when 'L' then 0 when 'R' then 1 else 2 end")
                ->get();

            $queue = $queue->merge($children);
        }

        return null;
    }

    public function hasFreePosition(BinaryNode $node, ?string $position = null): bool
    {
        if ($position !== null) {
            return $this->childAt($node, $this->normalizePosition($position)) === null;
        }

        return $this->childAt($node, 'L') === null || $this->childAt($node, 'R') === null;
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
        return $node->children()->where('position', $position)->first();
    }

    private function normalizePosition(?string $position): string
    {
        $position = strtoupper((string) $position);

        return match ($position) {
            'L', 'LEFT' => 'L',
            'R', 'RIGHT' => 'R',
            default => throw new InvalidArgumentException('Binary position must be L or R.'),
        };
    }
}

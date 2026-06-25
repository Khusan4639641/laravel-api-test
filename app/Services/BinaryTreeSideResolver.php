<?php

namespace App\Services;

use App\Models\BinaryNode;

class BinaryTreeSideResolver
{
    public function getRootSideForDescendant(int $rootUserId, int $descendantUserId): ?string
    {
        if ($rootUserId === $descendantUserId) {
            return null;
        }

        $rootNode = BinaryNode::query()
            ->where('user_id', $rootUserId)
            ->where('is_active', true)
            ->first(['id']);
        $descendantNode = BinaryNode::query()
            ->where('user_id', $descendantUserId)
            ->where('is_active', true)
            ->first(['id', 'parent_id', 'position']);

        if (! $rootNode || ! $descendantNode) {
            return null;
        }

        $currentNode = $descendantNode;
        $rootChildPosition = null;

        while ($currentNode->parent_id !== null) {
            if ((int) $currentNode->parent_id === (int) $rootNode->id) {
                $rootChildPosition = strtoupper((string) $currentNode->position);

                break;
            }

            $currentNode = BinaryNode::query()
                ->whereKey($currentNode->parent_id)
                ->where('is_active', true)
                ->first(['id', 'parent_id', 'position']);

            if (! $currentNode) {
                return null;
            }
        }

        return match ($rootChildPosition) {
            'L', 'LEFT' => 'left',
            'R', 'RIGHT' => 'right',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public function getRootSideMapForDescendants(int $rootUserId): array
    {
        $rootNode = BinaryNode::query()
            ->where('user_id', $rootUserId)
            ->where('is_active', true)
            ->first(['id', 'path']);

        if (! $rootNode?->path) {
            return [];
        }

        $rootPath = trim((string) $rootNode->path, '.');
        $rootSegmentsCount = count(explode('.', $rootPath));
        $rootChildPositions = BinaryNode::query()
            ->where('parent_id', $rootNode->id)
            ->where('is_active', true)
            ->pluck('position', 'user_id');
        $rootSides = [];

        BinaryNode::query()
            ->where('path', 'like', $rootPath.'.%')
            ->where('is_active', true)
            ->get(['user_id', 'path'])
            ->each(function (BinaryNode $node) use (&$rootSides, $rootSegmentsCount, $rootChildPositions): void {
                $segments = array_values(array_filter(explode('.', trim((string) $node->path, '.'))));
                $rootChildUserId = (int) ($segments[$rootSegmentsCount] ?? 0);
                $position = strtoupper((string) $rootChildPositions->get($rootChildUserId));
                $rootSide = match ($position) {
                    'L', 'LEFT' => 'left',
                    'R', 'RIGHT' => 'right',
                    default => null,
                };

                if ($rootSide !== null) {
                    $rootSides[(int) $node->user_id] = $rootSide;
                }
            });

        return $rootSides;
    }
}

<?php

namespace App\Services;

use App\Models\BinaryNode;
use App\Models\PvTransaction;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardBranchVolumeService
{
    /**
     * @return array{left_count: int, right_count: int, left_pv: string, right_pv: string, weak_leg_pv: float, total_pv: string, weak_leg: string, left_branch_pv: string, right_branch_pv: string}
     */
    public function getVolumesForRoot(User $root): array
    {
        $volumes = $this->getBranchVolumes($root);
        $counts = $this->branchCounts($root);

        return [
            'left_count' => $counts['left_count'],
            'right_count' => $counts['right_count'],
            'left_pv' => $volumes['left_pv'],
            'right_pv' => $volumes['right_pv'],
            'weak_leg_pv' => $volumes['weak_leg_pv'],
            'total_pv' => $volumes['total_pv'],
            'weak_leg' => $volumes['weak_leg'],
            'left_branch_pv' => $volumes['left_pv'],
            'right_branch_pv' => $volumes['right_pv'],
        ];
    }

    /**
     * @return array{left_pv: string, right_pv: string, weak_leg_pv: float, total_pv: string, weak_leg: string, fallback_left_pv: string, fallback_right_pv: string, transaction_left_pv: string, transaction_right_pv: string}
     */
    public function getBranchVolumes(User $user): array
    {
        $user->loadMissing('binaryNode');

        $fallbackVolumes = $this->calculateDirectionalBranchPv($user);
        $transactionVolumes = $this->transactionVolumes($user);
        $hasBinaryNode = (bool) $user->binaryNode?->path;
        $leftPv = $this->resolveBranchVolume(
            (string) ($user->left_pv ?? '0'),
            $transactionVolumes['left_pv'],
            $fallbackVolumes['left_pv'],
            ! $hasBinaryNode,
        );
        $rightPv = $this->resolveBranchVolume(
            (string) ($user->right_pv ?? '0'),
            $transactionVolumes['right_pv'],
            $fallbackVolumes['right_pv'],
            ! $hasBinaryNode,
        );
        $weakLegPv = $this->minDecimal($leftPv, $rightPv);

        return [
            'left_pv' => $leftPv,
            'right_pv' => $rightPv,
            'weak_leg_pv' => (float) $weakLegPv,
            'total_pv' => bcadd($leftPv, $rightPv, 2),
            'weak_leg' => bccomp($leftPv, $rightPv, 2) <= 0 ? 'left' : 'right',
            'fallback_left_pv' => $fallbackVolumes['left_pv'],
            'fallback_right_pv' => $fallbackVolumes['right_pv'],
            'transaction_left_pv' => $transactionVolumes['left_pv'],
            'transaction_right_pv' => $transactionVolumes['right_pv'],
        ];
    }

    public function getUserTurnoverPvForBranch(User $user): float
    {
        $user->loadMissing('currentPackage');

        return $user->currentPackage ? (float) $user->currentPackage->turnoverPv() : 0.0;
    }

    public function getUserPersonalPv(User $user): float
    {
        $user->loadMissing('currentPackage');

        return $user->currentPackage ? (float) $user->currentPackage->activityPv() : 0.0;
    }

    /**
     * @return array{left_pv: string, right_pv: string}
     */
    public function calculateDirectionalBranchPv(User $user): array
    {
        $user->loadMissing('binaryNode');
        $rootNode = $user->binaryNode;

        if (! $rootNode?->path) {
            return ['left_pv' => '0.00', 'right_pv' => '0.00'];
        }

        $nodes = BinaryNode::query()
            ->with(['user.currentPackage'])
            ->where(function ($query) use ($rootNode): void {
                $query->where('id', $rootNode->id)
                    ->orWhere('path', 'like', $rootNode->path.'.%');
            })
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->orderBy('depth')
            ->orderBy('id')
            ->get();

        $volumes = $this->calculatePackageFallbackNodeVolumes($nodes);
        $rootVolumes = $volumes[$rootNode->id] ?? null;

        return [
            'left_pv' => $rootVolumes['left_branch_pv'] ?? '0.00',
            'right_pv' => $rootVolumes['right_branch_pv'] ?? '0.00',
        ];
    }

    /**
     * @param  Collection<int, BinaryNode>  $nodes
     * @return array<int, array{left_branch_pv: string, right_branch_pv: string, weak_leg_pv: float, subtree_turnover_pv: string}>
     */
    public function calculateNodeBranchVolumes(Collection $nodes): array
    {
        $fallbackVolumes = $this->calculatePackageFallbackNodeVolumes($nodes);
        $transactionVolumes = $this->transactionVolumesForNodes($nodes);
        $volumes = [];

        foreach ($nodes as $node) {
            $user = $node->user;
            $userId = $user?->id;
            $leftPv = $this->resolveBranchVolume(
                (string) ($user?->left_pv ?? '0'),
                $userId ? ($transactionVolumes[$userId]['left_pv'] ?? '0.00') : '0.00',
                $fallbackVolumes[$node->id]['left_branch_pv'] ?? '0.00',
                false,
            );
            $rightPv = $this->resolveBranchVolume(
                (string) ($user?->right_pv ?? '0'),
                $userId ? ($transactionVolumes[$userId]['right_pv'] ?? '0.00') : '0.00',
                $fallbackVolumes[$node->id]['right_branch_pv'] ?? '0.00',
                false,
            );
            $weakLegPv = $this->minDecimal($leftPv, $rightPv);

            $volumes[$node->id] = [
                'left_branch_pv' => $leftPv,
                'right_branch_pv' => $rightPv,
                'weak_leg_pv' => (float) $weakLegPv,
                'subtree_turnover_pv' => $fallbackVolumes[$node->id]['subtree_turnover_pv'] ?? '0.00',
            ];
        }

        return $volumes;
    }

    /**
     * Updates display cache only. Remaining PV is intentionally untouched to avoid recounting used binary PV.
     *
     * @return array{changed: bool, left_pv: string, right_pv: string, total_pv: string}
     */
    public function syncCachedBranchVolumes(User $user): array
    {
        $volumes = $this->getBranchVolumes($user);
        $currentLeftPv = $this->decimal((string) ($user->left_pv ?? '0'));
        $currentRightPv = $this->decimal((string) ($user->right_pv ?? '0'));
        $currentTotalPv = $this->decimal((string) ($user->total_pv ?? '0'));
        $totalPv = bcadd($volumes['left_pv'], $volumes['right_pv'], 2);
        $changed = bccomp($currentLeftPv, $volumes['left_pv'], 2) !== 0
            || bccomp($currentRightPv, $volumes['right_pv'], 2) !== 0
            || bccomp($currentTotalPv, $totalPv, 2) < 0;

        if ($changed) {
            $user->forceFill([
                'left_pv' => $volumes['left_pv'],
                'right_pv' => $volumes['right_pv'],
                'total_pv' => bccomp($currentTotalPv, $totalPv, 2) < 0 ? $totalPv : $currentTotalPv,
            ])->save();
        }

        return [
            'changed' => $changed,
            'left_pv' => $volumes['left_pv'],
            'right_pv' => $volumes['right_pv'],
            'total_pv' => bccomp($currentTotalPv, $totalPv, 2) < 0 ? $totalPv : $currentTotalPv,
        ];
    }

    /**
     * @return array{left_count: int, right_count: int}
     */
    private function branchCounts(User $user): array
    {
        $user->loadMissing('binaryNode');
        $rootNode = $user->binaryNode;

        if (! $rootNode?->path) {
            return ['left_count' => 0, 'right_count' => 0];
        }

        $rootSegments = explode('.', $rootNode->path);
        $rootChildren = BinaryNode::query()
            ->where('parent_id', $rootNode->id)
            ->where('is_active', true)
            ->pluck('position', 'user_id');
        $counts = ['left_count' => 0, 'right_count' => 0];

        $this->descendantNodes($rootNode->path)
            ->with('user')
            ->orderBy('depth')
            ->orderBy('id')
            ->get()
            ->each(function (BinaryNode $node) use (&$counts, $rootSegments, $rootChildren): void {
                if (! $node->user || ! $this->isActiveMlmPartner($node->user)) {
                    return;
                }

                $branch = $this->rootBranch($node, $rootSegments, $rootChildren);

                if ($branch === 'left') {
                    $counts['left_count']++;
                }

                if ($branch === 'right') {
                    $counts['right_count']++;
                }
            });

        return $counts;
    }

    /**
     * @return array{left_pv: string, right_pv: string}
     */
    private function transactionVolumes(User $user): array
    {
        $volumes = ['left_pv' => '0.00', 'right_pv' => '0.00'];
        $user->loadMissing('binaryNode');
        $uplineNode = $user->binaryNode;

        PvTransaction::query()
            ->with(['buyer.binaryNode'])
            ->where('upline_id', $user->id)
            ->whereColumn('buyer_id', '!=', 'upline_id')
            ->whereNull('voided_at')
            ->whereHas('buyer', fn ($query) => $query->activeMlm()->where('role', User::ROLE_USER))
            ->get()
            ->each(function (PvTransaction $row) use (&$volumes, $uplineNode): void {
                if (! $uplineNode || ! $this->buyerBelongsToDirectionalBranch($uplineNode, $row->buyer?->binaryNode, $row->branch)) {
                    return;
                }

                if ($row->branch === 'L') {
                    $volumes['left_pv'] = bcadd($volumes['left_pv'], (string) $row->pv, 2);
                }

                if ($row->branch === 'R') {
                    $volumes['right_pv'] = bcadd($volumes['right_pv'], (string) $row->pv, 2);
                }
            });

        return [
            'left_pv' => $this->decimal($volumes['left_pv']),
            'right_pv' => $this->decimal($volumes['right_pv']),
        ];
    }

    /**
     * @param  Collection<int, BinaryNode>  $nodes
     * @return array<int, array{left_pv: string, right_pv: string}>
     */
    private function transactionVolumesForNodes(Collection $nodes): array
    {
        $userIds = $nodes
            ->map(fn (BinaryNode $node): ?int => $node->user?->id)
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        $volumes = [];
        $uplineNodes = $nodes
            ->filter(fn (BinaryNode $node): bool => $node->user !== null)
            ->keyBy(fn (BinaryNode $node): int => (int) $node->user->id);

        PvTransaction::query()
            ->with(['buyer.binaryNode'])
            ->whereIn('upline_id', $userIds)
            ->whereColumn('buyer_id', '!=', 'upline_id')
            ->whereNull('voided_at')
            ->whereHas('buyer', fn ($query) => $query->activeMlm()->where('role', User::ROLE_USER))
            ->get()
            ->each(function (PvTransaction $row) use (&$volumes, $uplineNodes): void {
                $uplineId = (int) $row->getAttribute('upline_id');
                $uplineNode = $uplineNodes->get($uplineId);

                if (! $uplineNode || ! $this->buyerBelongsToDirectionalBranch($uplineNode, $row->buyer?->binaryNode, $row->branch)) {
                    return;
                }

                $volumes[$uplineId] ??= ['left_pv' => '0.00', 'right_pv' => '0.00'];

                if ($row->branch === 'L') {
                    $volumes[$uplineId]['left_pv'] = bcadd($volumes[$uplineId]['left_pv'], (string) $row->pv, 2);
                }

                if ($row->branch === 'R') {
                    $volumes[$uplineId]['right_pv'] = bcadd($volumes[$uplineId]['right_pv'], (string) $row->pv, 2);
                }
            });

        return collect($volumes)
            ->map(fn (array $row): array => [
                'left_pv' => $this->decimal($row['left_pv']),
                'right_pv' => $this->decimal($row['right_pv']),
            ])
            ->all();
    }

    public function buyerBelongsToDirectionalBranch(BinaryNode $uplineNode, ?BinaryNode $buyerNode, ?string $branch): bool
    {
        $branch = strtoupper((string) $branch);

        if (! in_array($branch, ['L', 'R'], true) || ! $buyerNode?->path || ! $uplineNode->path) {
            return false;
        }

        $uplinePath = trim((string) $uplineNode->path, '.');
        $buyerPath = trim((string) $buyerNode->path, '.');

        if ($buyerPath === $uplinePath || ! str_starts_with($buyerPath, $uplinePath.'.')) {
            return false;
        }

        $relativePath = substr($buyerPath, strlen($uplinePath) + 1);
        $relativeUserIds = array_values(array_filter(explode('.', $relativePath), fn (string $id): bool => $id !== ''));

        if ($relativeUserIds === []) {
            return false;
        }

        $positions = BinaryNode::query()
            ->whereIn('user_id', array_map('intval', $relativeUserIds))
            ->where('is_active', true)
            ->pluck('position', 'user_id');

        foreach ($relativeUserIds as $userId) {
            if (strtoupper((string) $positions->get((int) $userId)) !== $branch) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, BinaryNode>  $nodes
     * @return array<int, array{left_branch_pv: string, right_branch_pv: string, weak_leg_pv: float, subtree_turnover_pv: string}>
     */
    private function calculatePackageFallbackNodeVolumes(Collection $nodes): array
    {
        $nodesById = [];
        $childrenByParentId = [];
        $volumes = [];

        foreach ($nodes as $node) {
            $nodesById[$node->id] = $node;

            if ($node->parent_id !== null) {
                $childrenByParentId[(int) $node->parent_id][] = $node;
            }
        }

        $calculateDirectionalPv = function (BinaryNode $node) use (&$calculateDirectionalPv, &$volumes, $childrenByParentId): array {
            $leftPv = '0.00';
            $rightPv = '0.00';
            $subtreeChildrenPv = '0.00';

            foreach ($childrenByParentId[$node->id] ?? [] as $child) {
                $childVolumes = $calculateDirectionalPv($child);
                $childOwnPv = $this->nodeTurnoverPv($child);
                $childSubtreePv = $childVolumes['subtree_turnover_pv'];
                $subtreeChildrenPv = bcadd($subtreeChildrenPv, $childSubtreePv, 2);

                if ($child->position === 'L') {
                    $leftPv = bcadd($leftPv, bcadd($childOwnPv, $childVolumes['left_branch_pv'], 2), 2);
                } elseif ($child->position === 'R') {
                    $rightPv = bcadd($rightPv, bcadd($childOwnPv, $childVolumes['right_branch_pv'], 2), 2);
                }
            }

            $weakLegPv = $this->minDecimal($leftPv, $rightPv);
            $ownTurnoverPv = $this->nodeTurnoverPv($node);
            $subtreeTurnoverPv = bcadd($ownTurnoverPv, $subtreeChildrenPv, 2);
            $volumes[$node->id] = [
                'left_branch_pv' => $leftPv,
                'right_branch_pv' => $rightPv,
                'weak_leg_pv' => (float) $weakLegPv,
                'subtree_turnover_pv' => $subtreeTurnoverPv,
            ];

            return $volumes[$node->id];
        };

        foreach ($nodes as $node) {
            if ($node->parent_id === null || ! isset($nodesById[$node->parent_id])) {
                $calculateDirectionalPv($node);
            }
        }

        return $volumes;
    }

    private function nodeTurnoverPv(BinaryNode $node): string
    {
        $user = $node->user;

        if (! $user || ! $this->isActiveMlmPartner($user) || ! $user->currentPackage) {
            return '0.00';
        }

        return $this->decimal($user->currentPackage->turnoverPv());
    }

    private function descendantsQuery(?string $path)
    {
        return BinaryNode::query()->when(
            $path,
            fn ($query) => $query->where('path', 'like', $path.'.%')->where('is_active', true),
            fn ($query) => $query->whereRaw('1 = 0'),
        );
    }

    private function descendantNodes(string $path)
    {
        return $this->descendantsQuery($path);
    }

    /**
     * @param  Collection<int, string>  $rootChildren
     */
    private function rootBranch(BinaryNode $node, array $rootSegments, Collection $rootChildren): ?string
    {
        $segments = $node->path ? explode('.', $node->path) : [];
        $rootChildUserId = $segments[count($rootSegments)] ?? null;
        $position = $rootChildren->get((int) $rootChildUserId, $node->position);

        return match (strtoupper((string) $position)) {
            'L', 'LEFT' => 'left',
            'R', 'RIGHT' => 'right',
            default => null,
        };
    }

    private function resolveBranchVolume(string $cachedPv, string $transactionPv, string $fallbackPv, bool $allowCachedFallback = true): string
    {
        $cachedPv = $this->decimal($cachedPv);
        $transactionPv = $this->decimal($transactionPv);
        $fallbackPv = $this->decimal($fallbackPv);

        if (bccomp($transactionPv, '0.00', 2) > 0) {
            return $transactionPv;
        }

        if (bccomp($fallbackPv, '0.00', 2) > 0) {
            return $fallbackPv;
        }

        return $allowCachedFallback ? $cachedPv : '0.00';
    }

    private function isActiveMlmPartner(User $user): bool
    {
        return $user->role === User::ROLE_USER
            && ! $user->trashed()
            && $user->account_status === 'active';
    }

    private function minDecimal(string $left, string $right): string
    {
        return bccomp($left, $right, 2) <= 0 ? $left : $right;
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}

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

        $fallbackVolumes = $this->packageTurnoverVolumes($user);
        $transactionVolumes = $this->transactionVolumes($user);
        $leftPv = $this->resolveBranchVolume(
            (string) ($user->left_pv ?? '0'),
            $transactionVolumes['left_pv'],
            $fallbackVolumes['left_pv'],
        );
        $rightPv = $this->resolveBranchVolume(
            (string) ($user->right_pv ?? '0'),
            $transactionVolumes['right_pv'],
            $fallbackVolumes['right_pv'],
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
            );
            $rightPv = $this->resolveBranchVolume(
                (string) ($user?->right_pv ?? '0'),
                $userId ? ($transactionVolumes[$userId]['right_pv'] ?? '0.00') : '0.00',
                $fallbackVolumes[$node->id]['right_branch_pv'] ?? '0.00',
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
     * @return array{left_pv: string, right_pv: string}
     */
    private function packageTurnoverVolumes(User $user): array
    {
        $rootNode = $user->binaryNode;

        if (! $rootNode?->path) {
            return ['left_pv' => '0.00', 'right_pv' => '0.00'];
        }

        $rootSegments = explode('.', $rootNode->path);
        $rootChildren = BinaryNode::query()
            ->where('parent_id', $rootNode->id)
            ->where('is_active', true)
            ->pluck('position', 'user_id');
        $volumes = ['left_pv' => '0.00', 'right_pv' => '0.00'];

        $this->descendantNodes($rootNode->path)
            ->with(['user.currentPackage'])
            ->orderBy('depth')
            ->orderBy('id')
            ->get()
            ->each(function (BinaryNode $node) use (&$volumes, $rootSegments, $rootChildren): void {
                $partner = $node->user;

                if (! $partner || $partner->role !== User::ROLE_USER || $partner->account_status !== 'active' || ! $partner->currentPackage) {
                    return;
                }

                $branch = $this->rootBranch($node, $rootSegments, $rootChildren);
                $turnoverPv = $this->decimal($partner->currentPackage->turnoverPv());

                if ($branch === 'left') {
                    $volumes['left_pv'] = bcadd($volumes['left_pv'], $turnoverPv, 2);
                }

                if ($branch === 'right') {
                    $volumes['right_pv'] = bcadd($volumes['right_pv'], $turnoverPv, 2);
                }
            });

        return $volumes;
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
                if (! $node->user || $node->user->role !== User::ROLE_USER || $node->user->account_status !== 'active') {
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

        PvTransaction::query()
            ->selectRaw('branch, COALESCE(SUM(pv), 0) as total_pv')
            ->where('upline_id', $user->id)
            ->whereNull('voided_at')
            ->whereHas('buyer', fn ($query) => $query->where('role', User::ROLE_USER)->activeAccount())
            ->groupBy('branch')
            ->get()
            ->each(function (PvTransaction $row) use (&$volumes): void {
                if ($row->branch === 'L') {
                    $volumes['left_pv'] = $this->decimal((string) $row->getAttribute('total_pv'));
                }

                if ($row->branch === 'R') {
                    $volumes['right_pv'] = $this->decimal((string) $row->getAttribute('total_pv'));
                }
            });

        return $volumes;
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

        PvTransaction::query()
            ->selectRaw('upline_id, branch, COALESCE(SUM(pv), 0) as total_pv')
            ->whereIn('upline_id', $userIds)
            ->whereNull('voided_at')
            ->whereHas('buyer', fn ($query) => $query->where('role', User::ROLE_USER)->activeAccount())
            ->groupBy('upline_id', 'branch')
            ->get()
            ->each(function (PvTransaction $row) use (&$volumes): void {
                $uplineId = (int) $row->getAttribute('upline_id');
                $volumes[$uplineId] ??= ['left_pv' => '0.00', 'right_pv' => '0.00'];

                if ($row->branch === 'L') {
                    $volumes[$uplineId]['left_pv'] = $this->decimal((string) $row->getAttribute('total_pv'));
                }

                if ($row->branch === 'R') {
                    $volumes[$uplineId]['right_pv'] = $this->decimal((string) $row->getAttribute('total_pv'));
                }
            });

        return $volumes;
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

        $calculateSubtreePv = function (BinaryNode $node) use (&$calculateSubtreePv, &$volumes, $childrenByParentId): string {
            $leftPv = '0.00';
            $rightPv = '0.00';
            $otherPv = '0.00';

            foreach ($childrenByParentId[$node->id] ?? [] as $child) {
                $childSubtreePv = $calculateSubtreePv($child);

                if ($child->position === 'L') {
                    $leftPv = bcadd($leftPv, $childSubtreePv, 2);
                } elseif ($child->position === 'R') {
                    $rightPv = bcadd($rightPv, $childSubtreePv, 2);
                } else {
                    $otherPv = bcadd($otherPv, $childSubtreePv, 2);
                }
            }

            $weakLegPv = $this->minDecimal($leftPv, $rightPv);
            $ownTurnoverPv = $this->nodeTurnoverPv($node);
            $subtreeTurnoverPv = bcadd(bcadd($ownTurnoverPv, $leftPv, 2), bcadd($rightPv, $otherPv, 2), 2);
            $volumes[$node->id] = [
                'left_branch_pv' => $leftPv,
                'right_branch_pv' => $rightPv,
                'weak_leg_pv' => (float) $weakLegPv,
                'subtree_turnover_pv' => $subtreeTurnoverPv,
            ];

            return $subtreeTurnoverPv;
        };

        foreach ($nodes as $node) {
            if ($node->parent_id === null || ! isset($nodesById[$node->parent_id])) {
                $calculateSubtreePv($node);
            }
        }

        return $volumes;
    }

    private function nodeTurnoverPv(BinaryNode $node): string
    {
        $user = $node->user;

        if (! $user || $user->role !== User::ROLE_USER || $user->account_status !== 'active' || ! $user->currentPackage) {
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

    private function resolveBranchVolume(string $cachedPv, string $transactionPv, string $fallbackPv): string
    {
        $cachedPv = $this->decimal($cachedPv);
        $transactionPv = $this->decimal($transactionPv);
        $fallbackPv = $this->decimal($fallbackPv);

        if (bccomp($cachedPv, '0.00', 2) > 0) {
            return $cachedPv;
        }

        if (bccomp($transactionPv, '0.00', 2) > 0) {
            return $transactionPv;
        }

        return $fallbackPv;
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

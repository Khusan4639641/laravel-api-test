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
            ->pluck('position', 'user_id');
        $volumes = ['left_pv' => '0.00', 'right_pv' => '0.00'];

        $this->descendantNodes($rootNode->path)
            ->with(['user.currentPackage'])
            ->orderBy('depth')
            ->orderBy('id')
            ->get()
            ->each(function (BinaryNode $node) use (&$volumes, $rootSegments, $rootChildren): void {
                $partner = $node->user;

                if (! $partner || $partner->role !== User::ROLE_USER || ! $partner->currentPackage) {
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
            ->pluck('position', 'user_id');
        $counts = ['left_count' => 0, 'right_count' => 0];

        $this->descendantNodes($rootNode->path)
            ->with('user')
            ->orderBy('depth')
            ->orderBy('id')
            ->get()
            ->each(function (BinaryNode $node) use (&$counts, $rootSegments, $rootChildren): void {
                if (! $node->user || $node->user->role !== User::ROLE_USER) {
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
            ->whereHas('buyer', fn ($query) => $query->where('role', User::ROLE_USER))
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

    private function descendantsQuery(?string $path)
    {
        return BinaryNode::query()->when(
            $path,
            fn ($query) => $query->where('path', 'like', $path.'.%'),
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

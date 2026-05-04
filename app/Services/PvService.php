<?php

namespace App\Services;

use App\Models\BinaryNode;
use App\Models\Order;
use App\Models\User;
use InvalidArgumentException;

class PvService
{
    public function __construct(
        private readonly StatusService $statusService,
    ) {
    }

    public function accruePvUpTree(User $sourceUser, float|string $pv, ?Order $sourceOrder = null): void
    {
        $pv = (string) $pv;

        if (bccomp($pv, '0', 2) <= 0) {
            throw new InvalidArgumentException('PV amount must be greater than zero.');
        }

        $node = $sourceUser->binaryNode()->first();

        while ($node?->parent_id) {
            /** @var BinaryNode $currentNode */
            $currentNode = $node;
            $parentNode = $currentNode->parent()->first();

            if (! $parentNode) {
                break;
            }

            $parentUser = $parentNode->user()->lockForUpdate()->first();

            if ($parentUser) {
                $this->addBranchPv($parentUser, $currentNode->position, $pv);
                $this->statusService->recalculate($parentUser);
            }

            $node = $parentNode;
        }

        // TODO: Trigger binary bonus recalculation for affected ancestors.
    }

    public function addUserPv(User $user, float|string $pv): void
    {
        $pv = (string) $pv;

        if (bccomp($pv, '0', 2) <= 0) {
            throw new InvalidArgumentException('PV amount must be greater than zero.');
        }

        $freshUser = User::query()->lockForUpdate()->findOrFail($user->id);

        $freshUser->forceFill([
            'total_pv' => bcadd((string) $freshUser->total_pv, $pv, 2),
        ])->save();

        $this->statusService->recalculate($freshUser);
    }

    private function addBranchPv(User $user, ?string $branch, string $pv): void
    {
        match ($branch) {
            'L' => $user->forceFill([
                'left_pv' => bcadd((string) $user->left_pv, $pv, 2),
                'remaining_left_pv' => bcadd((string) $user->remaining_left_pv, $pv, 2),
                'total_pv' => bcadd((string) $user->total_pv, $pv, 2),
            ])->save(),
            'R' => $user->forceFill([
                'right_pv' => bcadd((string) $user->right_pv, $pv, 2),
                'remaining_right_pv' => bcadd((string) $user->remaining_right_pv, $pv, 2),
                'total_pv' => bcadd((string) $user->total_pv, $pv, 2),
            ])->save(),
            default => null,
        };
    }
}

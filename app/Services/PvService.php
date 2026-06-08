<?php

namespace App\Services;

use App\Models\BinaryNode;
use App\Models\Order;
use App\Models\PvTransaction;
use App\Models\User;
use InvalidArgumentException;

class PvService
{
    public function __construct(
        private readonly StatusService $statusService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function accrueTurnoverToUplines(
        User $buyer,
        float|string $pv,
        string $source,
        array $meta = [],
        ?Order $sourceOrder = null,
        bool $isBonusable = true,
    ): void {
        $this->propagateToUplines($buyer, $pv, $source, $meta, $sourceOrder, $isBonusable);
    }

    public function accruePvUpTree(User $sourceUser, float|string $pv, ?Order $sourceOrder = null, bool $isBonusable = true): void
    {
        $this->propagateToUplines($sourceUser, $pv, 'legacy_pv_accrual', [], $sourceOrder, $isBonusable);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function propagateToUplines(
        User $buyer,
        float|string $pv,
        string $source,
        array $meta,
        ?Order $sourceOrder,
        bool $isBonusable,
    ): void
    {
        $pv = (string) $pv;

        if (bccomp($pv, '0', 2) <= 0) {
            throw new InvalidArgumentException('PV amount must be greater than zero.');
        }

        $this->recordTurnoverAudit($sourceOrder, $pv, $source, $meta, $isBonusable);

        $node = $buyer->binaryNode()->where('is_active', true)->first();

        while ($node?->parent_id) {
            /** @var BinaryNode $currentNode */
            $currentNode = $node;
            $parentNode = $currentNode->parent()->first();

            if (! $parentNode) {
                break;
            }

            $parentUser = $parentNode->user()->lockForUpdate()->first();

            if ($parentUser) {
                $this->addBranchPv($parentUser, $currentNode->position, $pv, $isBonusable);
                $this->recordPvTransaction(
                    buyer: $buyer,
                    upline: $parentUser,
                    pv: $pv,
                    source: $source,
                    branch: $currentNode->position,
                    meta: $meta,
                    sourceOrder: $sourceOrder,
                    isBonusable: $isBonusable,
                );
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

    private function addBranchPv(
        User $user,
        ?string $branch,
        string $pv,
        bool $isBonusable,
    ): void
    {
        match ($branch) {
            'L' => $user->forceFill([
                'left_pv' => bcadd((string) $user->left_pv, $pv, 2),
                'remaining_left_pv' => $isBonusable
                    ? bcadd((string) $user->remaining_left_pv, $pv, 2)
                    : (string) $user->remaining_left_pv,
                'total_pv' => bcadd((string) $user->total_pv, $pv, 2),
            ])->save(),
            'R' => $user->forceFill([
                'right_pv' => bcadd((string) $user->right_pv, $pv, 2),
                'remaining_right_pv' => $isBonusable
                    ? bcadd((string) $user->remaining_right_pv, $pv, 2)
                    : (string) $user->remaining_right_pv,
                'total_pv' => bcadd((string) $user->total_pv, $pv, 2),
            ])->save(),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function recordPvTransaction(
        User $buyer,
        User $upline,
        string $pv,
        string $source,
        ?string $branch,
        array $meta,
        ?Order $sourceOrder,
        bool $isBonusable,
    ): void {
        if (! in_array($branch, ['L', 'R'], true)) {
            return;
        }

        PvTransaction::query()->create([
            'buyer_id' => $buyer->id,
            'upline_id' => $upline->id,
            'source_order_id' => $sourceOrder?->id,
            'source' => $source,
            'branch' => $branch,
            'pv' => $pv,
            'is_bonusable' => $isBonusable,
            'metadata' => $meta ?: null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function recordTurnoverAudit(?Order $sourceOrder, string $pv, string $source, array $meta, bool $isBonusable): void
    {
        if (! $sourceOrder) {
            return;
        }

        $metadata = is_array($sourceOrder->metadata) ? $sourceOrder->metadata : [];
        $metadata['pv_turnover'] = [
            'source' => $source,
            'pv' => $pv,
            'is_bonusable' => $isBonusable,
            'meta' => $meta,
            'recorded_at' => now()->toISOString(),
        ];

        $sourceOrder->forceFill([
            'metadata' => $metadata,
        ])->save();
    }
}

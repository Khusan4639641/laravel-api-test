<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Models\BinaryNode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StructureController extends Controller
{
    use RespondsWithPagination;

    public function __invoke(Request $request): JsonResponse
    {
        $selectedUser = null;
        $rootNode = null;
        $maxDepth = min(max((int) $request->integer('depth', 5), 0), 10);
        $includeFlat = $request->boolean('include_flat');

        if ($request->filled('user_id')) {
            $selectedUser = User::query()
                ->with(['currentPackage', 'sponsor', 'wallets'])
                ->find((int) $request->integer('user_id'));

            if (! $selectedUser) {
                throw new NotFoundHttpException('Selected user was not found.');
            }

            $rootNode = $selectedUser->binaryNode()->first();
        } else {
            $rootNode = BinaryNode::query()
                ->whereNull('parent_id')
                ->orderBy('id')
                ->first();

            $selectedUser = $rootNode?->user()->with(['currentPackage', 'sponsor', 'wallets'])->first()
                ?: User::query()->with(['currentPackage', 'sponsor', 'wallets'])->orderBy('id')->first();
        }

        if (! $selectedUser) {
            return response()->json([
                'root_user_id' => null,
                'root' => null,
                'nodes' => [],
            ]);
        }

        $stats = $this->statsFor($selectedUser, $rootNode);
        $nodes = $this->subtreeNodes($rootNode, $maxDepth);
        $loadedRootNode = $rootNode ? $nodes->firstWhere('id', $rootNode->id) : null;
        $rootDepth = $loadedRootNode?->depth ?? 0;
        $root = $loadedRootNode
            ? $this->nodeTree($loadedRootNode, $nodes, $rootDepth)
            : $this->userOnlyNode($selectedUser);
        $response = [
            'root_user_id' => $selectedUser->id,
            'root' => $root,
            'stats' => $stats,
            'nodes' => $this->flattenTree($root),
            'depth' => $maxDepth,
        ];

        if ($includeFlat) {
            $response['flat'] = $this->descendantFlatNodes($rootNode);
        }

        return response()->json($response);
    }

    /**
     * @return \Illuminate\Support\Collection<int, BinaryNode>
     */
    private function subtreeNodes(?BinaryNode $rootNode, int $maxDepth)
    {
        if (! $rootNode) {
            return collect();
        }

        return BinaryNode::query()
            ->with(['user.currentPackage', 'user.sponsor', 'user.wallets'])
            ->where(function ($query) use ($rootNode): void {
                $query->where('id', $rootNode->id)
                    ->orWhere('path', 'like', $rootNode->path.'.%');
            })
            ->where('depth', '<=', $rootNode->depth + $maxDepth)
            ->orderBy('depth')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, BinaryNode>  $nodes
     * @return array<string, mixed>
     */
    private function nodeTree(BinaryNode $node, $nodes, int $rootDepth): array
    {
        $leftNode = $nodes->first(fn (BinaryNode $child): bool => (int) $child->parent_id === (int) $node->id && $child->position === 'L');
        $rightNode = $nodes->first(fn (BinaryNode $child): bool => (int) $child->parent_id === (int) $node->id && $child->position === 'R');

        return $this->userNode($node->user, $node, [
            'left' => $leftNode ? $this->nodeTree($leftNode, $nodes, $rootDepth) : null,
            'right' => $rightNode ? $this->nodeTree($rightNode, $nodes, $rootDepth) : null,
        ], max(0, $node->depth - $rootDepth));
    }

    /**
     * @return array<string, mixed>
     */
    private function userOnlyNode(User $user): array
    {
        return $this->userNode($user, null, [
            'left' => null,
            'right' => null,
        ], 0);
    }

    /**
     * @param  array{left: array<string, mixed>|null, right: array<string, mixed>|null}  $children
     * @return array<string, mixed>
     */
    private function userNode(User $user, ?BinaryNode $node, array $children, int $level): array
    {
        $package = $user->currentPackage;
        $sponsor = $user->sponsor;
        $wallets = $user->relationLoaded('wallets') ? $user->wallets : collect();
        $balance = (float) $wallets->where('type', 'main')->sum('balance');
        $totalBalance = (float) $wallets->whereIn('type', ['main', 'bonus', 'deposit'])->sum('balance');

        return [
            'id' => $user->id,
            'user_id' => $user->id,
            'binary_node_id' => $node?->id,
            'parent_id' => $node?->parent_id,
            'login' => $user->login,
            'name' => $user->name,
            'email' => $user->email,
            'sponsor' => $sponsor ? [
                'id' => $sponsor->id,
                'name' => $sponsor->name,
                'login' => $sponsor->login,
            ] : null,
            'package' => $package?->name ?? $package?->code,
            'status' => $user->status,
            'left_pv' => $user->left_pv,
            'right_pv' => $user->right_pv,
            'total_pv' => $user->total_pv,
            'balance' => $balance,
            'total_balance' => $totalBalance,
            'level' => $level,
            'branch' => $node?->position,
            'position' => $node?->position,
            'children' => $children,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $node
     * @return array<int, array<string, mixed>>
     */
    private function flattenTree(?array $node): array
    {
        if (! $node) {
            return [];
        }

        $children = $node['children'] ?? ['left' => null, 'right' => null];
        $copy = $node;
        unset($copy['children']);

        return array_values(array_filter([
            $copy,
            ...$this->flattenTree($children['left'] ?? null),
            ...$this->flattenTree($children['right'] ?? null),
        ]));
    }

    /**
     * @return array<string, int>
     */
    private function statsFor(User $rootUser, ?BinaryNode $rootNode): array
    {
        return [
            'direct_invited_count' => User::query()->where('sponsor_id', $rootUser->id)->count(),
            'total_downline_count' => $this->descendantCount($rootNode),
            'left_branch_count' => $this->branchCount($rootNode, 'L'),
            'right_branch_count' => $this->branchCount($rootNode, 'R'),
        ];
    }

    private function descendantCount(?BinaryNode $rootNode): int
    {
        if (! $rootNode) {
            return 0;
        }

        return BinaryNode::query()
            ->where('path', 'like', $rootNode->path.'.%')
            ->count();
    }

    private function branchCount(?BinaryNode $rootNode, string $position): int
    {
        if (! $rootNode) {
            return 0;
        }

        $branchRoot = BinaryNode::query()
            ->where('parent_id', $rootNode->id)
            ->where('position', $position)
            ->first();

        if (! $branchRoot) {
            return 0;
        }

        return 1 + BinaryNode::query()
            ->where('path', 'like', $branchRoot->path.'.%')
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function descendantFlatNodes(?BinaryNode $rootNode): array
    {
        if (! $rootNode) {
            return [];
        }

        return BinaryNode::query()
            ->with(['user.currentPackage', 'user.sponsor', 'user.wallets'])
            ->where('path', 'like', $rootNode->path.'.%')
            ->orderBy('depth')
            ->orderBy('id')
            ->get()
            ->map(fn (BinaryNode $node): array => $this->userNode($node->user, $node, [
                'left' => null,
                'right' => null,
            ], max(0, $node->depth - $rootNode->depth)))
            ->values()
            ->all();
    }
}

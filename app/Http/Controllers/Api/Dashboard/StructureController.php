<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\BinaryNodeResource;
use App\Models\BinaryNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StructureController extends Controller
{
    use RespondsWithPagination;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load('binaryNode');
        $rootPath = $user->binaryNode?->path;
        $rootDepth = $user->binaryNode?->depth ?? 0;
        $rootSegments = $rootPath ? explode('.', $rootPath) : [];
        $rootChildren = BinaryNode::query()
            ->where('parent_id', $user->binaryNode?->id)
            ->pluck('position', 'user_id');

        $partners = BinaryNode::query()
            ->with(['user.profile', 'user.currentPackage'])
            ->when(
                $rootPath,
                fn ($query) => $query->where('path', 'like', $rootPath.'.%'),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->orderBy('depth')
            ->orderBy('id')
            ->paginate($this->perPage($request));
        $partners->getCollection()->transform(function (BinaryNode $node) use ($rootSegments, $rootChildren, $rootDepth) {
            $this->decorateNodeWithRootBranch($node, $rootSegments, $rootChildren, $rootDepth);

            return $node;
        });

        $branchCounts = ['L' => 0, 'R' => 0];

        $this->descendantsQuery($rootPath)
            ->select(['id', 'user_id', 'depth', 'path'])
            ->get()
            ->each(function (BinaryNode $node) use (&$branchCounts, $rootSegments, $rootChildren, $rootDepth) {
                $this->decorateNodeWithRootBranch($node, $rootSegments, $rootChildren, $rootDepth);
                $branch = $node->getAttribute('root_branch');

                if (isset($branchCounts[$branch])) {
                    $branchCounts[$branch]++;
                }
            });

        return $this->paginated($partners, BinaryNodeResource::class, 'partners', $request, [
            'structure' => [
                'root_user_id' => $user->id,
                'referral_code' => (string) $user->id,
                'left_pv' => $user->left_pv,
                'right_pv' => $user->right_pv,
                'remaining_left_pv' => $user->remaining_left_pv,
                'remaining_right_pv' => $user->remaining_right_pv,
                'weak_leg' => ((float) $user->left_pv <= (float) $user->right_pv) ? 'left' : 'right',
                'total_partners' => $partners->total(),
                'left_partners' => $branchCounts['L'],
                'right_partners' => $branchCounts['R'],
            ],
        ]);
    }

    private function descendantsQuery(?string $path)
    {
        return BinaryNode::query()->when(
            $path,
            fn ($query) => $query->where('path', 'like', $path.'.%'),
            fn ($query) => $query->whereRaw('1 = 0'),
        );
    }

    private function decorateNodeWithRootBranch(BinaryNode $node, array $rootSegments, $rootChildren, int $rootDepth): void
    {
        $segments = $node->path ? explode('.', $node->path) : [];
        $rootChildUserId = $segments[count($rootSegments)] ?? null;

        $node->setAttribute('root_branch', $rootChildren->get((int) $rootChildUserId, $node->position));
        $node->setAttribute('relative_level', max(($node->depth ?? 0) - $rootDepth, 0));
    }
}

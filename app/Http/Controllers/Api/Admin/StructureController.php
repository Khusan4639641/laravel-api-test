<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Models\BinaryNode;
use App\Models\User;
use App\Services\DashboardBranchVolumeService;
use App\Support\SystemLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StructureController extends Controller
{
    use RespondsWithPagination;

    public function __invoke(Request $request, DashboardBranchVolumeService $branchVolumeService): JsonResponse
    {
        $selectedUser = null;
        $rootNode = null;
        $maxDepth = min(max((int) $request->integer('depth', 50), 0), 50);
        $includeFlat = $request->boolean('include_flat');
        $selectedUserId = $request->filled('user_id')
            ? (int) $request->integer('user_id')
            : ($request->filled('root_id') ? (int) $request->integer('root_id') : null);

        if ($selectedUserId !== null) {
            $selectedUser = User::query()
                ->activeAccount()
                ->with(['currentPackage', 'sponsor', 'wallets'])
                ->find($selectedUserId);

            if (! $selectedUser) {
                throw new NotFoundHttpException('Selected user was not found.');
            }

            $rootNode = $selectedUser->binaryNode()->where('is_active', true)->first();
        } else {
            $rootNode = BinaryNode::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->whereHas('user', fn ($query) => $query->activeAccount())
                ->orderBy('id')
                ->first();

            $selectedUser = $rootNode?->user()->with(['currentPackage', 'sponsor', 'wallets'])->first()
                ?: User::query()->activeAccount()->with(['currentPackage', 'sponsor', 'wallets'])->orderBy('id')->first();
        }

        if (! $selectedUser) {
            return response()->json([
                'root_user_id' => null,
                'root' => null,
                'nodes' => [],
            ]);
        }

        $stats = $this->statsFor($selectedUser, $rootNode, $branchVolumeService);
        $nodes = $this->subtreeNodes($rootNode, $maxDepth);
        $volumeNodes = $this->allSubtreeNodes($rootNode);
        $nodeVolumes = $branchVolumeService->calculateNodeBranchVolumes($volumeNodes);
        $hiddenNodesCount = max(0, $volumeNodes->count() - $nodes->count());
        $loadedRootNode = $rootNode ? $nodes->firstWhere('id', $rootNode->id) : null;
        $rootDepth = $loadedRootNode?->depth ?? 0;
        $root = $loadedRootNode
            ? $this->nodeTree($loadedRootNode, $nodes, $rootDepth, $branchVolumeService, $nodeVolumes)
            : $this->userOnlyNode($selectedUser, $branchVolumeService);
        $response = [
            'root_user_id' => $selectedUser->id,
            'root' => $root,
            'tree' => $root,
            'summary' => $stats,
            'stats' => $stats,
            'nodes' => $this->flattenTree($root),
            'depth' => $maxDepth,
            'has_deeper_nodes' => $hiddenNodesCount > 0,
            'hidden_nodes_count' => $hiddenNodesCount,
        ];

        if ($includeFlat) {
            $response['flat'] = $this->descendantFlatNodes($rootNode, $branchVolumeService, $nodeVolumes);
        }

        return response()->json($response);
    }

    public function rootOrphans(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'account_status' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'package_code' => ['nullable', 'string', 'max:50'],
            'sort_by' => ['nullable', 'string', 'max:50'],
            'sort_dir' => ['nullable', 'string', 'max:4'],
        ]);

        $query = $this->rootOrphanPartnersQuery();
        $total = (clone $query)->count();

        $this->applyRootOrphanSearch($query, $request->query('search', $request->query('q')));
        $this->applyRootOrphanFilters($query, $request);

        $limit = $this->structureListLimit($request);
        $offset = $this->structureListOffset($request, $limit);
        $filteredTotal = (clone $query)->count();
        $sortBy = $this->rootOrphanSortBy($request);
        $sortDir = $this->rootOrphanSortDir($request);

        $query->orderBy($sortBy, $sortDir);

        if ($sortBy !== 'id') {
            $query->orderBy('id');
        }

        $partners = $query
            ->offset($offset)
            ->limit($limit)
            ->get();
        $data = $partners
            ->map(fn (User $user): array => $this->rootOrphanPartnerRow($user))
            ->values()
            ->all();

        return response()->json([
            'summary' => [
                'total_root_orphans' => $total,
                'filtered_root_orphans' => $filteredTotal,
            ],
            'pagination' => [
                'total' => $total,
                'filtered_total' => $filteredTotal,
                'limit' => $limit,
                'offset' => $offset,
                'has_next' => $offset + $limit < $filteredTotal,
                'has_prev' => $offset > 0,
            ],
            'data' => $data,
            'partners' => $data,
            'root_orphans' => $data,
            'links' => $this->structureListOffsetLinks($request, $limit, $offset, $filteredTotal),
            'meta' => $this->structureListOffsetMeta($request, $limit, $offset, $filteredTotal, $partners->count()),
        ]);
    }

    private function rootOrphanPartnersQuery(): Builder
    {
        return User::query()
            ->where('role', User::ROLE_USER)
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('binaryNode')
                    ->orWhereHas('binaryNode', function (Builder $nodeQuery): void {
                        $nodeQuery
                            ->where('is_active', true)
                            ->whereNull('parent_id');
                    });
            })
            ->with(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
            ->withCount([
                'invitedUsers as children_count' => fn (Builder $query) => $query->where('role', User::ROLE_USER),
            ]);
    }

    private function applyRootOrphanSearch(Builder $query, mixed $search): void
    {
        $search = trim((string) $search);
        $phoneDigits = preg_replace('/\D+/', '', $search) ?? '';

        if ($search === '') {
            return;
        }

        $like = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $query) use ($search, $like, $phoneDigits): void {
            $query
                ->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(login) LIKE ?', [$like])
                ->orWhereRaw('LOWER(email) LIKE ?', [$like]);

            if (ctype_digit($search)) {
                $query->orWhere('id', (int) $search);
            }

            $query->orWhereHas('profile', function (Builder $profileQuery) use ($like, $phoneDigits): void {
                $profileQuery
                    ->whereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(city) LIKE ?', [$like]);

                if ($phoneDigits !== '') {
                    $profileQuery->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', ''), '(', ''), ')', '') LIKE ?",
                        ["%{$phoneDigits}%"],
                    );
                }
            });

            $query->orWhereHas('currentPackage', function (Builder $packageQuery) use ($like): void {
                $packageQuery
                    ->whereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$like]);
            });
        });
    }

    private function applyRootOrphanFilters(Builder $query, Request $request): void
    {
        $accountStatus = trim((string) $request->query('account_status', ''));
        $status = trim((string) $request->query('status', ''));
        $packageCode = trim((string) $request->query('package_code', ''));

        if ($accountStatus !== '') {
            $query->where('account_status', $accountStatus);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($packageCode !== '') {
            $query->whereHas('currentPackage', fn (Builder $packageQuery) => $packageQuery->where('code', strtoupper($packageCode)));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rootOrphanPartnerRow(User $user): array
    {
        $user->loadMissing(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode']);

        $wallets = $user->relationLoaded('wallets') ? $user->wallets : collect();
        $mainBalance = (float) $wallets->where('type', 'main')->sum('balance');
        $totalBalance = (float) $wallets->whereIn('type', ['main', 'bonus', 'deposit'])->sum('balance');
        $package = $user->currentPackage;
        $isPartnerActive = $user->isPartnerActive();
        $packageCode = $isPartnerActive ? $package?->code : null;
        $packagePv = $package ? (float) $package->activityPv() : 0;
        $statusCode = strtoupper((string) $user->status);

        return [
            'id' => $user->id,
            'user_id' => $user->id,
            'binary_node_id' => $user->binaryNode?->id,
            'parent_id' => $user->binaryNode?->parent_id,
            'sponsor_id' => $user->sponsor_id,
            'login' => $user->login,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->profile?->phone,
            'city' => $user->profile?->city,
            'role' => $user->role,
            'account_status' => $user->account_status,
            'account_status_label' => SystemLabel::accountStatus($user->account_status),
            'sponsor' => $user->sponsor ? [
                'id' => $user->sponsor->id,
                'name' => $user->sponsor->name,
                'login' => $user->sponsor->login,
            ] : null,
            'is_partner_active' => $isPartnerActive,
            'package_status' => $isPartnerActive ? 'active' : 'inactive',
            'package_status_label' => $isPartnerActive ? 'Активен' : 'Неактивен',
            'package' => $isPartnerActive && $package ? [
                'id' => $package->id,
                'code' => $packageCode,
                'name' => $package->name,
                'label' => SystemLabel::package($packageCode, $package->name, 'ru'),
            ] : null,
            'package_code' => $packageCode,
            'package_name' => $isPartnerActive && $package ? SystemLabel::package($packageCode, $package->name, 'ru') : null,
            'package_label' => $isPartnerActive && $package ? SystemLabel::package($packageCode, $package->name, 'ru') : '-',
            'status' => $user->status,
            'status_label' => SystemLabel::mlmStatus($user->status, null, 'ru'),
            'mlm_status' => [
                'code' => $statusCode,
                'label' => SystemLabel::mlmStatus($user->status, null, 'ru'),
            ],
            'left_pv' => $user->left_pv,
            'right_pv' => $user->right_pv,
            'total_pv' => $user->total_pv,
            'personal_pv' => $packagePv,
            'package_pv' => $packagePv,
            'package_activity_pv' => $packagePv,
            'team_pv' => (float) ($user->left_pv ?? 0) + (float) ($user->right_pv ?? 0),
            'balance' => $mainBalance,
            'total_balance' => $totalBalance,
            'children_count' => (int) ($user->children_count ?? 0),
            'direct_children_count' => (int) ($user->children_count ?? 0),
            'created_at' => $user->created_at?->toISOString(),
        ];
    }

    private function structureListLimit(Request $request): int
    {
        return min(max($request->integer('limit', 20), 1), 100);
    }

    private function structureListOffset(Request $request, int $limit): int
    {
        if (! $request->has('offset') && $request->has('page')) {
            return max($request->integer('page', 1) - 1, 0) * $limit;
        }

        return max($request->integer('offset', 0), 0);
    }

    private function rootOrphanSortBy(Request $request): string
    {
        $sortBy = (string) $request->query('sort_by', 'children_count');
        $allowed = [
            'id',
            'name',
            'login',
            'email',
            'created_at',
            'status',
            'account_status',
            'children_count',
            'left_pv',
            'right_pv',
            'total_pv',
        ];

        return in_array($sortBy, $allowed, true) ? $sortBy : 'children_count';
    }

    private function rootOrphanSortDir(Request $request): string
    {
        return strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    }

    /**
     * @return array<string, string|null>
     */
    private function structureListOffsetLinks(Request $request, int $limit, int $offset, int $filteredTotal): array
    {
        return [
            'first' => $this->structureListUrlWithOffset($request, $limit, 0),
            'last' => $this->structureListUrlWithOffset($request, $limit, max(((int) ceil($filteredTotal / $limit) - 1) * $limit, 0)),
            'prev' => $offset > 0 ? $this->structureListUrlWithOffset($request, $limit, max($offset - $limit, 0)) : null,
            'next' => $offset + $limit < $filteredTotal ? $this->structureListUrlWithOffset($request, $limit, $offset + $limit) : null,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function structureListOffsetMeta(Request $request, int $limit, int $offset, int $filteredTotal, int $count): array
    {
        $currentPage = intdiv($offset, $limit) + 1;
        $lastPage = max((int) ceil($filteredTotal / $limit), 1);

        return [
            'current_page' => $currentPage,
            'from' => $filteredTotal > 0 ? $offset + 1 : null,
            'last_page' => $lastPage,
            'path' => $request->url(),
            'per_page' => $limit,
            'to' => $filteredTotal > 0 ? $offset + $count : null,
            'total' => $filteredTotal,
        ];
    }

    private function structureListUrlWithOffset(Request $request, int $limit, int $offset): string
    {
        return $request->fullUrlWithQuery([
            'limit' => $limit,
            'offset' => $offset,
        ]);
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
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->where('depth', '<=', $rootNode->depth + $maxDepth)
            ->orderBy('depth')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, BinaryNode>
     */
    private function allSubtreeNodes(?BinaryNode $rootNode)
    {
        if (! $rootNode) {
            return collect();
        }

        return BinaryNode::query()
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
    }

    /**
     * @param  \Illuminate\Support\Collection<int, BinaryNode>  $nodes
     * @return array<string, mixed>
     */
    private function nodeTree(BinaryNode $node, $nodes, int $rootDepth, DashboardBranchVolumeService $branchVolumeService, array $nodeVolumes): array
    {
        $leftNode = $nodes->first(fn (BinaryNode $child): bool => (int) $child->parent_id === (int) $node->id && $child->position === 'L');
        $rightNode = $nodes->first(fn (BinaryNode $child): bool => (int) $child->parent_id === (int) $node->id && $child->position === 'R');

        return $this->userNode($node->user, $node, [
            'left' => $leftNode ? $this->nodeTree($leftNode, $nodes, $rootDepth, $branchVolumeService, $nodeVolumes) : null,
            'right' => $rightNode ? $this->nodeTree($rightNode, $nodes, $rootDepth, $branchVolumeService, $nodeVolumes) : null,
        ], max(0, $node->depth - $rootDepth), $branchVolumeService, $nodeVolumes[$node->id] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function userOnlyNode(User $user, DashboardBranchVolumeService $branchVolumeService): array
    {
        return $this->userNode($user, null, [
            'left' => null,
            'right' => null,
        ], 0, $branchVolumeService);
    }

    /**
     * @param  array{left: array<string, mixed>|null, right: array<string, mixed>|null}  $children
     * @return array<string, mixed>
     */
    private function userNode(User $user, ?BinaryNode $node, array $children, int $level, DashboardBranchVolumeService $branchVolumeService, ?array $nodeVolume = null): array
    {
        $user->loadMissing(['currentPackage', 'sponsor', 'wallets']);
        $package = $user->currentPackage;
        $sponsor = $user->sponsor;
        $wallets = $user->relationLoaded('wallets') ? $user->wallets : collect();
        $balance = (float) $wallets->where('type', 'main')->sum('balance');
        $totalBalance = (float) $wallets->whereIn('type', ['main', 'bonus', 'deposit'])->sum('balance');
        $leftBranchPv = (string) ($nodeVolume['left_branch_pv'] ?? '0.00');
        $rightBranchPv = (string) ($nodeVolume['right_branch_pv'] ?? '0.00');
        $weakLegPv = (float) ($nodeVolume['weak_leg_pv'] ?? min((float) $leftBranchPv, (float) $rightBranchPv));
        $packageCode = $package?->code;
        $isPartnerActive = $user->isPartnerActive();
        $activePackageCode = $isPartnerActive ? $packageCode : null;
        $personalPv = $branchVolumeService->getUserPersonalPv($user);
        $turnoverPv = $branchVolumeService->getUserTurnoverPvForBranch($user);
        $statusCode = strtoupper((string) $user->status);

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
            'is_partner_active' => $isPartnerActive,
            'package_status' => $isPartnerActive ? 'active' : 'inactive',
            'package_status_label' => $isPartnerActive ? 'Активен' : 'Неактивен',
            'package' => $isPartnerActive && $package ? [
                'id' => $package->id,
                'code' => $activePackageCode,
                'name' => $package->name,
                'label' => SystemLabel::package($activePackageCode, $package->name, 'ru'),
                'activity_pv' => $personalPv,
                'turnover_pv' => $turnoverPv,
            ] : null,
            'package_code' => $activePackageCode,
            'package_name' => $isPartnerActive && $package ? SystemLabel::package($activePackageCode, $package->name, 'ru') : null,
            'package_label' => $isPartnerActive && $package ? SystemLabel::package($activePackageCode, $package->name, 'ru') : '-',
            'status' => $user->status,
            'status_label' => SystemLabel::mlmStatus($user->status, null, 'ru'),
            'mlm_status' => [
                'code' => $statusCode,
                'label' => SystemLabel::mlmStatus($user->status, null, 'ru'),
            ],
            'left_pv' => $leftBranchPv,
            'right_pv' => $rightBranchPv,
            'left_branch_pv' => $leftBranchPv,
            'right_branch_pv' => $rightBranchPv,
            'weak_leg_pv' => $weakLegPv,
            'team_pv' => (float) $leftBranchPv + (float) $rightBranchPv,
            'personal_pv' => $personalPv,
            'package_pv' => $personalPv,
            'package_activity_pv' => $personalPv,
            'turnover_pv' => $turnoverPv,
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
    private function statsFor(User $rootUser, ?BinaryNode $rootNode, DashboardBranchVolumeService $branchVolumeService): array
    {
        $volumes = $branchVolumeService->getVolumesForRoot($rootUser);
        $totalCount = $volumes['left_count'] + $volumes['right_count'];

        return [
            'direct_invited_count' => User::query()
                ->where('sponsor_id', $rootUser->id)
                ->where('role', User::ROLE_USER)
                ->activeAccount()
                ->count(),
            'total_downline_count' => $totalCount,
            'total_structure_count' => $totalCount,
            'left_branch_count' => $volumes['left_count'],
            'right_branch_count' => $volumes['right_count'],
            'left_count' => $volumes['left_count'],
            'right_count' => $volumes['right_count'],
            'left_pv' => $volumes['left_pv'],
            'right_pv' => $volumes['right_pv'],
            'weak_leg_pv' => $volumes['weak_leg_pv'],
            'left_branch_pv' => $volumes['left_branch_pv'],
            'right_branch_pv' => $volumes['right_branch_pv'],
            'weak_leg' => $volumes['weak_leg'],
        ];
    }

    private function descendantCount(?BinaryNode $rootNode): int
    {
        if (! $rootNode) {
            return 0;
        }

        return BinaryNode::query()
            ->where('path', 'like', $rootNode->path.'.%')
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->activeAccount())
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
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->first();

        if (! $branchRoot) {
            return 0;
        }

        return 1 + BinaryNode::query()
            ->where('path', 'like', $branchRoot->path.'.%')
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function descendantFlatNodes(?BinaryNode $rootNode, DashboardBranchVolumeService $branchVolumeService, array $nodeVolumes): array
    {
        if (! $rootNode) {
            return [];
        }

        return BinaryNode::query()
            ->with(['user.currentPackage', 'user.sponsor', 'user.wallets'])
            ->where('path', 'like', $rootNode->path.'.%')
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->orderBy('depth')
            ->orderBy('id')
            ->get()
            ->map(fn (BinaryNode $node): array => $this->userNode($node->user, $node, [
                'left' => null,
                'right' => null,
            ], max(0, $node->depth - $rootNode->depth), $branchVolumeService, $nodeVolumes[$node->id] ?? null))
            ->values()
            ->all();
    }
}

<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Models\BinaryNode;
use App\Models\User;
use App\Services\DashboardBranchVolumeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StructureController extends Controller
{
    use RespondsWithPagination;

    public function __invoke(Request $request, DashboardBranchVolumeService $branchVolumeService): JsonResponse
    {
        $user = $request->user()->load(['binaryNode', 'currentPackage']);
        $rootNode = $user->binaryNode;
        $rootPath = $rootNode?->path;
        $rootDepth = $rootNode?->depth ?? 0;
        $rootSegments = $rootPath ? explode('.', $rootPath) : [];
        $rootChildren = $rootNode
            ? BinaryNode::query()
                ->where('parent_id', $rootNode->id)
                ->where('is_active', true)
                ->whereHas('user', fn ($query) => $query->activeAccount())
                ->pluck('position', 'user_id')
            : collect();

        $descendantNodes = $this->descendantsQuery($rootPath)
            ->with(['user.profile', 'user.currentPackage'])
            ->orderBy('depth')
            ->orderBy('id')
            ->get();

        $descendantNodes->each(
            fn (BinaryNode $node) => $this->decorateNodeWithRootBranch($node, $rootSegments, $rootChildren, $rootDepth)
        );

        $nodeVolumes = $branchVolumeService->calculateNodeBranchVolumes($descendantNodes);
        $partnerRows = $this->partnerRows($descendantNodes, $nodeVolumes);
        $filteredPartnerRows = $this->filterPartnerRows($partnerRows, $request);
        $paginator = $this->paginateRows($filteredPartnerRows, $request);
        $branchCounts = $this->branchCounts($partnerRows);
        $branchVolumes = $branchVolumeService->getBranchVolumes($user);
        $summary = [
            'total_partners' => $partnerRows->count(),
            'direct_invited' => User::query()
                ->where('sponsor_id', $user->id)
                ->where('role', User::ROLE_USER)
                ->activeAccount()
                ->count(),
            'left_count' => $branchCounts['left'],
            'right_count' => $branchCounts['right'],
            'left_partners' => $branchCounts['left'],
            'right_partners' => $branchCounts['right'],
            'left_pv' => $branchVolumes['left_pv'],
            'right_pv' => $branchVolumes['right_pv'],
            'weak_leg_pv' => $branchVolumes['weak_leg_pv'],
            'remaining_left_pv' => $user->remaining_left_pv,
            'remaining_right_pv' => $user->remaining_right_pv,
            'weak_leg' => $branchVolumes['weak_leg'],
        ];

        $partnersPayload = [
            'data' => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];

        $canInvite = $this->canInvite($user);

        return response()->json([
            'summary' => $summary,
            'referral_links' => [
                'left' => $canInvite ? $this->referralLink($request, $user, 'left') : '',
                'right' => $canInvite ? $this->referralLink($request, $user, 'right') : '',
            ],
            'can_invite' => $canInvite,
            'structure' => [
                'root_user_id' => $user->id,
                'referral_code' => $user->login ?: (string) $user->id,
                'can_invite' => $canInvite,
                ...$summary,
            ],
            'tree' => $this->tree($user, $rootNode, $descendantNodes, $rootDepth),
            'partners' => $partnersPayload,
            'data' => $partnersPayload['data'],
            'links' => $partnersPayload['links'],
            'meta' => $partnersPayload['meta'],
        ]);
    }

    private function descendantsQuery(?string $path)
    {
        return BinaryNode::query()->when(
            $path,
            fn ($query) => $query
                ->where('path', 'like', $path.'.%')
                ->where('is_active', true)
                ->whereHas('user', fn ($userQuery) => $userQuery->activeAccount()),
            fn ($query) => $query->whereRaw('1 = 0'),
        );
    }

    private function decorateNodeWithRootBranch(BinaryNode $node, array $rootSegments, Collection $rootChildren, int $rootDepth): void
    {
        $segments = $node->path ? explode('.', $node->path) : [];
        $rootChildUserId = $segments[count($rootSegments)] ?? null;
        $position = $rootChildren->get((int) $rootChildUserId, $node->position);

        $node->setAttribute('root_branch', $this->branchCode($position));
        $node->setAttribute('relative_level', max(($node->depth ?? 0) - $rootDepth, 0));
    }

    /**
     * @param  Collection<int, BinaryNode>  $descendantNodes
     * @return Collection<int, array<string, mixed>>
     */
    private function partnerRows(Collection $descendantNodes, array $nodeVolumes): Collection
    {
        return $descendantNodes
            ->filter(fn (BinaryNode $node): bool => $node->user !== null && $node->user->role === User::ROLE_USER && $node->user->account_status === 'active')
            ->map(fn (BinaryNode $node): array => $this->partnerRow($node->user, $node, $nodeVolumes[$node->id] ?? null))
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function filterPartnerRows(Collection $rows, Request $request): Collection
    {
        $branch = strtolower((string) $request->query('branch', 'all'));
        $search = trim((string) $request->query('search', $request->query('q', '')));

        if (in_array($branch, ['left', 'right'], true)) {
            $rows = $rows->filter(fn (array $row): bool => ($row['branch'] ?? null) === $branch);
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $needleDigits = preg_replace('/\D+/', '', $search) ?? '';
            $isPhoneLikeSearch = $needleDigits !== '' && preg_match('/^[\d\s()+-]+$/', $search) === 1;

            $rows = $rows->filter(function (array $row) use ($needle, $needleDigits, $isPhoneLikeSearch): bool {
                $phoneDigits = preg_replace('/\D+/', '', (string) ($row['phone'] ?? '')) ?? '';
                $package = is_array($row['package'] ?? null) ? $row['package'] : [];
                $branch = (string) ($row['branch'] ?? '');
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $row['id'] ?? null,
                    $row['name'] ?? null,
                    $row['login'] ?? null,
                    $row['email'] ?? null,
                    $row['phone'] ?? null,
                    $phoneDigits,
                    $branch,
                    $this->branchLabel($branch),
                    $row['line'] ?? null,
                    $row['depth'] ?? null,
                    $package['id'] ?? null,
                    $package['code'] ?? null,
                    $package['name'] ?? null,
                    $row['package_code'] ?? null,
                    $row['package_label'] ?? null,
                    $this->packageSearchAliases((string) ($row['package_code'] ?? ($package['code'] ?? ''))),
                    $row['status'] ?? null,
                    $row['status_label'] ?? null,
                    $this->statusSearchAliases((string) ($row['status'] ?? '')),
                    $row['personal_pv'] ?? null,
                    $row['team_pv'] ?? null,
                    $row['account_status'] ?? null,
                    $row['account_status_label'] ?? null,
                    $this->accountStatusSearchAliases((string) ($row['account_status'] ?? '')),
                    $row['registered_at'] ?? null,
                    $row['registered_date'] ?? null,
                ], fn (mixed $value): bool => $value !== null && $value !== '')));

                return str_contains($haystack, $needle)
                    || ($isPhoneLikeSearch && str_contains($phoneDigits, $needleDigits));
            });
        }

        return $rows->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerRow(User $partner, ?BinaryNode $node, ?array $nodeVolume = null): array
    {
        $package = $partner->currentPackage;
        $leftPv = (string) ($nodeVolume['left_branch_pv'] ?? $partner->left_pv ?? '0.00');
        $rightPv = (string) ($nodeVolume['right_branch_pv'] ?? $partner->right_pv ?? '0.00');
        $line = (int) ($node?->getAttribute('relative_level') ?? $node?->depth ?? 1);
        $branch = $this->branchCode($node?->getAttribute('root_branch') ?? $node?->position);

        return [
            'id' => $partner->id,
            'name' => $partner->name,
            'login' => $partner->login,
            'email' => $partner->email,
            'phone' => $partner->profile?->phone,
            'branch' => $branch,
            'position' => $branch,
            'line' => max($line, 1),
            'level' => max($line, 1),
            'depth' => max($line, 1),
            'package' => $package ? [
                'id' => $package->id,
                'code' => $package->code,
                'name' => $package->name,
            ] : null,
            'package_code' => $package?->code,
            'package_label' => \App\Support\SystemLabel::package($package?->code, $package?->name),
            'status' => $partner->status,
            'status_label' => \App\Support\SystemLabel::mlmStatus($partner->status),
            'account_status' => $partner->account_status,
            'account_status_label' => \App\Support\SystemLabel::accountStatus($partner->account_status),
            'personal_pv' => $package ? (float) $package->activityPv() : 0,
            'team_pv' => (float) $leftPv + (float) $rightPv,
            'left_pv' => number_format((float) $leftPv, 2, '.', ''),
            'right_pv' => number_format((float) $rightPv, 2, '.', ''),
            'registered_at' => $partner->created_at?->toDateString(),
            'registered_date' => $partner->created_at?->toDateString(),
            'created_at' => $partner->created_at?->toISOString(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{left: int, right: int}
     */
    private function branchCounts(Collection $rows): array
    {
        return [
            'left' => $rows->where('branch', 'left')->count(),
            'right' => $rows->where('branch', 'right')->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function paginateRows(Collection $rows, Request $request): LengthAwarePaginator
    {
        $perPage = min(max($request->integer('per_page', 10), 1), 100);
        $page = max($request->integer('page', 1), 1);

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values()->all(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    /**
     * @param  Collection<int, BinaryNode>  $nodes
     * @return array<string, mixed>
     */
    private function tree(User $rootUser, ?BinaryNode $rootNode, Collection $nodes, int $rootDepth): array
    {
        if (! $rootNode) {
            return [
                'id' => $rootUser->id,
                'name' => $rootUser->name,
                'login' => $rootUser->login,
                'line' => 0,
                'branch' => null,
                'children' => [
                    'left' => null,
                    'right' => null,
                ],
            ];
        }

        return $this->treeNode($rootNode, $nodes->prepend($rootNode), $rootDepth);
    }

    /**
     * @param  Collection<int, BinaryNode>  $nodes
     * @return array<string, mixed>
     */
    private function treeNode(BinaryNode $node, Collection $nodes, int $rootDepth): array
    {
        $node->loadMissing(['user.currentPackage']);
        $left = $nodes->first(fn (BinaryNode $child): bool => (int) $child->parent_id === (int) $node->id && $child->position === 'L');
        $right = $nodes->first(fn (BinaryNode $child): bool => (int) $child->parent_id === (int) $node->id && $child->position === 'R');
        $line = max(($node->depth ?? 0) - $rootDepth, 0);

        return [
            'id' => $node->user?->id,
            'name' => $node->user?->name,
            'login' => $node->user?->login,
            'line' => $line,
            'branch' => $this->branchCode($node->getAttribute('root_branch') ?? $node->position),
            'package' => $node->user?->currentPackage?->code,
            'children' => [
                'left' => $left ? $this->treeNode($left, $nodes, $rootDepth) : null,
                'right' => $right ? $this->treeNode($right, $nodes, $rootDepth) : null,
            ],
        ];
    }

    private function branchCode(mixed $position): ?string
    {
        return match (strtoupper((string) $position)) {
            'L', 'LEFT' => 'left',
            'R', 'RIGHT' => 'right',
            default => null,
        };
    }

    private function branchLabel(string $branch): string
    {
        return match ($branch) {
            'left' => 'Левая ветка',
            'right' => 'Правая ветка',
            default => '',
        };
    }

    private function packageSearchAliases(string $code): string
    {
        return match (strtoupper($code)) {
            'START' => 'START Start Старт',
            'VIP' => 'VIP ВИП',
            'ELITE' => 'ELITE Elite Элит Элита',
            default => '',
        };
    }

    private function statusSearchAliases(string $status): string
    {
        return match (strtolower($status)) {
            'user' => 'USER user Partner Партнёр Партнер',
            'manager' => 'MANAGER manager Менеджер',
            'leader' => 'LEADER leader Лидер',
            'director' => 'DIRECTOR director Директор',
            'bronze_director' => 'BRONZE_DIRECTOR bronze director Бронзовый директор',
            'silver_director' => 'SILVER_DIRECTOR silver director Серебряный директор',
            'gold_director' => 'GOLD_DIRECTOR gold director Золотой директор',
            'platinum_director' => 'PLATINUM_DIRECTOR platinum director Платиновый директор',
            'emerald_director' => 'EMERALD_DIRECTOR emerald director Изумрудный директор',
            'diamond_director' => 'DIAMOND_DIRECTOR diamond director Бриллиантовый директор',
            default => $status,
        };
    }

    private function accountStatusSearchAliases(string $status): string
    {
        return match (strtolower($status)) {
            'active' => 'active Active Активен активен',
            'inactive' => 'inactive Inactive Неактивно неактивно',
            'blocked' => 'blocked Blocked Заблокирован заблокирован',
            default => $status,
        };
    }

    private function referralLink(Request $request, User $user, string $branch): string
    {
        $referralCode = trim((string) $user->login);

        if ($referralCode === '') {
            return '';
        }

        return $request->getSchemeAndHttpHost().'/register-ref-branch?'.http_build_query([
            'ref' => $referralCode,
            'branch' => $branch,
        ]);
    }

    private function canInvite(User $user): bool
    {
        return $user->canInvitePartners();
    }
}

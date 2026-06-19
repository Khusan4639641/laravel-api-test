<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $usersQuery = User::query()
            ->with(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
            ->withCount(['referrals', 'invitedUsers as invited_count']);

        if ($this->isPartnersListing($request)) {
            $usersQuery->where('role', User::ROLE_USER);
        }

        $total = (clone $usersQuery)->count();
        $this->applyPartnerSearch($usersQuery, $request->query('search', $request->query('q')));

        $limit = $this->limit($request);
        $offset = $this->offset($request, $limit);
        $filteredTotal = (clone $usersQuery)->count();
        $sortBy = $this->sortBy($request);
        $sortDir = $this->sortDir($request);

        $users = $usersQuery
            ->orderBy($sortBy, $sortDir)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $data = UserResource::collection($users)->resolve($request);

        return response()->json([
            'summary' => $this->partnersSummary(),
            'pagination' => [
                'total' => $total,
                'filtered_total' => $filteredTotal,
                'limit' => $limit,
                'offset' => $offset,
                'has_next' => $offset + $limit < $filteredTotal,
                'has_prev' => $offset > 0,
            ],
            'data' => $data,
            'users' => $data,
            'links' => $this->offsetLinks($request, $limit, $offset, $filteredTotal),
            'meta' => $this->offsetMeta($request, $limit, $offset, $filteredTotal, $users->count()),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $usersQuery = User::query()
            ->where('role', User::ROLE_USER)
            ->activeAccount()
            ->with(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
            ->withCount(['referrals', 'invitedUsers as invited_count'])
            ->latest();

        $this->applyPartnerSearch($usersQuery, $validated['q'] ?? '');

        $users = $usersQuery
            ->limit((int) min(max($request->integer('limit', 10), 1), 25))
            ->get();

        return response()->json([
            'partners' => UserResource::collection($users),
        ]);
    }

    public function sponsorSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'selected_id' => ['nullable', 'integer'],
        ]);

        $limit = (int) min(max($request->integer('limit', 30), 1), 50);
        $sponsorsQuery = User::query()
            ->eligibleSponsor()
            ->with(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
            ->withCount(['referrals', 'invitedUsers as invited_count'])
            ->latest();

        $this->applySponsorSearch($sponsorsQuery, $validated['q'] ?? '');

        $sponsors = $sponsorsQuery
            ->limit($limit)
            ->get();

        $selectedId = (int) ($validated['selected_id'] ?? 0);

        if ($selectedId > 0 && ! $sponsors->contains('id', $selectedId)) {
            $selectedSponsor = User::query()
                ->eligibleSponsor()
                ->with(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
                ->withCount(['referrals', 'invitedUsers as invited_count'])
                ->find($selectedId);

            if ($selectedSponsor) {
                $sponsors->prepend($selectedSponsor);
            }
        }

        return response()->json([
            'sponsors' => UserResource::collection($sponsors->unique('id')->values()),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => UserResource::make(
                $user->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
                    ->loadCount(['referrals', 'invitedUsers as invited_count'])
            ),
        ]);
    }

    /**
     * @return array{total_partners: int, active_partners: int, vip_elite_partners: int, total_balance: float}
     */
    private function partnersSummary(): array
    {
        $partnerUserIds = User::query()
            ->select('id')
            ->where('role', User::ROLE_USER);
        $walletBalance = (float) Wallet::query()
            ->whereIn('user_id', $partnerUserIds)
            ->whereIn('type', ['main', 'bonus', 'deposit'])
            ->sum('balance');

        return [
            'total_partners' => User::query()
                ->where('role', User::ROLE_USER)
                ->count(),
            'active_partners' => User::query()
                ->where('role', User::ROLE_USER)
                ->activeMlm()
                ->count(),
            'vip_elite_partners' => User::query()
                ->where('role', User::ROLE_USER)
                ->activeMlm()
                ->whereHas('currentPackage', fn ($query) => $query->whereIn('code', ['VIP', 'ELITE']))
                ->count(),
            'total_balance' => $walletBalance,
        ];
    }

    private function applyPartnerSearch(Builder $query, mixed $search): void
    {
        $search = trim((string) $search);
        $phoneDigits = preg_replace('/\D+/', '', $search) ?? '';

        if ($search === '') {
            return;
        }

        $like = '%'.mb_strtolower($search).'%';
        $rawLike = "%{$search}%";
        $numericSearch = $this->numericSearchValue($search);
        $accountStatuses = $this->matchingCodes($search, $this->accountStatusSearchTerms());
        $mlmStatuses = $this->matchingCodes($search, $this->mlmStatusSearchTerms());
        $packageCodes = $this->matchingCodes($search, $this->packageSearchTerms());

        $query->where(function (Builder $query) use ($search, $phoneDigits, $like, $rawLike, $numericSearch, $accountStatuses, $mlmStatuses, $packageCodes): void {
            $this->applyUserTextSearch($query, $like, $rawLike);

            if (ctype_digit($search)) {
                $query->orWhere('id', (int) $search);
            }

            if ($accountStatuses !== []) {
                $query->orWhereIn('account_status', $accountStatuses);
            }

            if ($mlmStatuses !== []) {
                $query->orWhereIn('status', $mlmStatuses);
            }

            $query->orWhereHas('profile', function (Builder $profileQuery) use ($like, $rawLike, $phoneDigits): void {
                $this->applyProfileSearch($profileQuery, $like, $rawLike, $phoneDigits);
            });

            $query->orWhereHas('sponsor', function (Builder $sponsorQuery) use ($search, $like, $rawLike, $phoneDigits): void {
                $this->applyUserTextSearch($sponsorQuery, $like, $rawLike);

                if (ctype_digit($search)) {
                    $sponsorQuery->orWhere('id', (int) $search);
                }

                $sponsorQuery->orWhereHas('profile', function (Builder $profileQuery) use ($like, $rawLike, $phoneDigits): void {
                    $this->applyProfileSearch($profileQuery, $like, $rawLike, $phoneDigits);
                });
            });

            $query->orWhereHas('currentPackage', function (Builder $packageQuery) use ($like, $rawLike, $numericSearch, $packageCodes): void {
                $packageQuery
                    ->whereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$like])
                    ->orWhere('description', 'like', $rawLike);

                if ($packageCodes !== []) {
                    $packageQuery->orWhereIn('code', $packageCodes);
                }

                if ($numericSearch !== null) {
                    $packageQuery
                        ->orWhere('id', (int) $numericSearch)
                        ->orWhere('pv', $numericSearch)
                        ->orWhere('activity_pv', $numericSearch)
                        ->orWhere('turnover_pv', $numericSearch)
                        ->orWhere('price', $numericSearch);
                }
            });

            if ($numericSearch !== null) {
                $query
                    ->orWhere('left_pv', $numericSearch)
                    ->orWhere('right_pv', $numericSearch)
                    ->orWhere('remaining_left_pv', $numericSearch)
                    ->orWhere('remaining_right_pv', $numericSearch)
                    ->orWhere('total_pv', $numericSearch)
                    ->orWhereRaw('(COALESCE(left_pv, 0) + COALESCE(right_pv, 0)) = ?', [$numericSearch])
                    ->orWhereHas('wallets', function (Builder $walletQuery) use ($numericSearch): void {
                        $walletQuery
                            ->where('balance', $numericSearch)
                            ->orWhere('hold_balance', $numericSearch);
                    });
            }

            $query->orWhereHas('wallets', function (Builder $walletQuery) use ($like): void {
                $walletQuery
                    ->whereRaw('LOWER(type) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(currency) LIKE ?', [$like]);
            });
        });
    }

    private function applySponsorSearch(Builder $query, mixed $search): void
    {
        $search = trim((string) $search);
        $phoneDigits = preg_replace('/\D+/', '', $search) ?? '';

        if ($search === '') {
            return;
        }

        $like = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $query) use ($search, $phoneDigits, $like): void {
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
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$like]);

                if ($phoneDigits !== '') {
                    $profileQuery->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', ''), '(', ''), ')', '') LIKE ?",
                        ["%{$phoneDigits}%"]
                    );
                }
            });
        });
    }

    private function isPartnersListing(Request $request): bool
    {
        return $request->is('api/admin/partners');
    }

    private function limit(Request $request): int
    {
        if (! $request->has('limit') && $request->has('per_page')) {
            return $this->perPage($request);
        }

        return min(max($request->integer('limit', 20), 1), 100);
    }

    private function offset(Request $request, int $limit): int
    {
        if (! $request->has('offset') && $request->has('page')) {
            return max($request->integer('page', 1) - 1, 0) * $limit;
        }

        return max($request->integer('offset', 0), 0);
    }

    private function sortBy(Request $request): string
    {
        $sortBy = (string) $request->query('sort_by', 'created_at');
        $allowed = [
            'id',
            'name',
            'login',
            'email',
            'created_at',
            'updated_at',
            'status',
            'account_status',
            'left_pv',
            'right_pv',
            'remaining_left_pv',
            'remaining_right_pv',
            'total_pv',
            'current_package_id',
        ];

        return in_array($sortBy, $allowed, true) ? $sortBy : 'created_at';
    }

    private function sortDir(Request $request): string
    {
        return strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    }

    /**
     * @return array<string, string|null>
     */
    private function offsetLinks(Request $request, int $limit, int $offset, int $filteredTotal): array
    {
        return [
            'first' => $this->urlWithOffset($request, $limit, 0),
            'last' => $this->urlWithOffset($request, $limit, max(((int) ceil($filteredTotal / $limit) - 1) * $limit, 0)),
            'prev' => $offset > 0 ? $this->urlWithOffset($request, $limit, max($offset - $limit, 0)) : null,
            'next' => $offset + $limit < $filteredTotal ? $this->urlWithOffset($request, $limit, $offset + $limit) : null,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function offsetMeta(Request $request, int $limit, int $offset, int $filteredTotal, int $count): array
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

    private function urlWithOffset(Request $request, int $limit, int $offset): string
    {
        return $request->fullUrlWithQuery([
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    private function applyUserTextSearch(Builder $query, string $like, string $rawLike): void
    {
        $query
            ->whereRaw('LOWER(name) LIKE ?', [$like])
            ->orWhereRaw('LOWER(login) LIKE ?', [$like])
            ->orWhereRaw('LOWER(email) LIKE ?', [$like])
            ->orWhere('created_at', 'like', $rawLike);
    }

    private function applyProfileSearch(Builder $query, string $like, string $rawLike, string $phoneDigits): void
    {
        $query
            ->whereRaw('LOWER(first_name) LIKE ?', [$like])
            ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
            ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
            ->orWhereRaw('LOWER(city) LIKE ?', [$like])
            ->orWhereRaw('LOWER(country) LIKE ?', [$like])
            ->orWhere('address', 'like', $rawLike);

        if ($phoneDigits !== '') {
            $query->orWhereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', ''), '(', ''), ')', '') LIKE ?",
                ["%{$phoneDigits}%"]
            );
        }
    }

    private function numericSearchValue(string $search): ?float
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($search));

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * @param  array<string, array<int, string>>  $termsByCode
     * @return array<int, string>
     */
    private function matchingCodes(string $search, array $termsByCode): array
    {
        $normalizedSearch = $this->normalizeSearchTerm($search);

        return array_values(array_filter(array_keys($termsByCode), function (string $code) use ($termsByCode, $normalizedSearch): bool {
            $normalizedCode = $this->normalizeSearchTerm($code);

            if ($normalizedCode !== '' && $normalizedCode === $normalizedSearch) {
                return true;
            }

            foreach ($termsByCode[$code] as $term) {
                $normalizedTerm = $this->normalizeSearchTerm($term);

                if ($normalizedTerm !== '' && (
                    $normalizedTerm === $normalizedSearch
                    || str_starts_with($normalizedTerm, $normalizedSearch.' ')
                    || str_starts_with($normalizedTerm, $normalizedSearch)
                )) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function normalizeSearchTerm(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = str_replace(['ё', '-', '_'], ['е', ' ', ' '], $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function accountStatusSearchTerms(): array
    {
        return [
            'active' => ['active', 'активен', 'активный', 'активно', 'белсенді', 'активдүү'],
            'inactive' => ['inactive', 'неактивно', 'неактивен', 'не активен', 'белсенді емес'],
            'blocked' => ['blocked', 'заблокирован', 'заблокировано', 'блок', 'бұғатталған', 'бөгөттөлгөн'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function mlmStatusSearchTerms(): array
    {
        return [
            'user' => ['user', 'partner', 'партнер', 'партнёр', 'серіктес', 'өнөктөш'],
            'manager' => ['manager', 'менеджер'],
            'leader' => ['leader', 'лидер'],
            'director' => ['director', 'директор'],
            'bronze_director' => ['bronze director', 'бронзовый директор', 'қола директор', 'коло директор', 'director', 'директор'],
            'silver_director' => ['silver director', 'серебряный директор', 'күміс директор', 'күмүш директор', 'director', 'директор'],
            'gold_director' => ['gold director', 'золотой директор', 'алтын директор', 'director', 'директор'],
            'platinum_director' => ['platinum director', 'платиновый директор', 'платина директор', 'director', 'директор'],
            'emerald_director' => ['emerald director', 'изумрудный директор', 'изумруд директор', 'director', 'директор'],
            'diamond_director' => ['diamond director', 'бриллиантовый директор', 'бриллиант директор', 'director', 'директор'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function packageSearchTerms(): array
    {
        return [
            'START' => ['start', 'старт'],
            'VIP' => ['vip', 'вип'],
            'ELITE' => ['elite', 'элит', 'элита'],
        ];
    }
}

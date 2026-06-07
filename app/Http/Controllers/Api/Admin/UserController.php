<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Wallet;
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

        $this->applyPartnerSearch($usersQuery, $request->query('search', $request->query('q')));

        $users = $usersQuery
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($users, UserResource::class, 'users', $request, [
            'summary' => $this->partnersSummary(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $usersQuery = User::query()
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
                ->where('account_status', 'active')
                ->count(),
            'vip_elite_partners' => User::query()
                ->where('role', User::ROLE_USER)
                ->whereHas('currentPackage', fn ($query) => $query->whereIn('code', ['VIP', 'ELITE']))
                ->count(),
            'total_balance' => $walletBalance,
        ];
    }

    private function applyPartnerSearch($query, mixed $search): void
    {
        $search = trim((string) $search);
        $phoneDigits = preg_replace('/\D+/', '', $search) ?? '';

        if ($search === '') {
            return;
        }

        $query->where(function ($query) use ($search, $phoneDigits): void {
            if (ctype_digit($search)) {
                $query->orWhere('id', (int) $search);
            }

            $query->orWhere('name', 'like', "%{$search}%")
                ->orWhere('login', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('profile', function ($profileQuery) use ($search, $phoneDigits): void {
                    $profileQuery->where('phone', 'like', "%{$search}%");

                    if ($phoneDigits !== '') {
                        $profileQuery->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', ''), '(', ''), ')', '') LIKE ?",
                            ["%{$phoneDigits}%"]
                        );
                    }
                });
        });
    }
}

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
        $users = User::query()
            ->with(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
            ->withCount(['referrals', 'invitedUsers as invited_count'])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($users, UserResource::class, 'users', $request, [
            'summary' => $this->partnersSummary(),
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
}

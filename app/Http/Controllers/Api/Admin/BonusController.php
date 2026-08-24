<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\BonusTransactionResource;
use App\Models\BonusTransaction;
use App\Models\User;
use App\Services\BonusAdminAdjustmentService;
use App\Services\BonusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BonusController extends Controller
{
    use RespondsWithPagination;

    public function __construct(
        private readonly BonusService $bonusService,
        private readonly BonusAdminAdjustmentService $bonusAdjustmentService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));
        $status = trim((string) $request->query('status', ''));

        $bonuses = BonusTransaction::query()
            ->with(['user.profile', 'sourceUser.profile', 'sourceOrder', 'walletTransaction'])
            ->whereNotIn('status', ['reversed', 'voided', 'cancelled'])
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->where(function ($query): void {
                $query->whereNull('source_user_id')
                    ->orWhereHas('sourceUser', fn ($sourceUserQuery) => $sourceUserQuery->activeAccount());
            })
            ->when($type !== '', fn ($query) => $query->where('bonus_type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    if (ctype_digit($search)) {
                        $query->orWhere('id', (int) $search)
                            ->orWhere('user_id', (int) $search)
                            ->orWhere('source_user_id', (int) $search);
                    }

                    $query
                        ->orWhere('bonus_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('login', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('sourceUser', function ($sourceUserQuery) use ($search): void {
                            $sourceUserQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('login', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('updated_at')
            ->paginate($this->perPage($request));

        return $this->paginated($bonuses, BonusTransactionResource::class, 'bonuses', $request);
    }

    public function calculateBinary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->whereNull('deleted_at')
                    ->where('account_status', 'active'),
            ],
        ]);

        if (empty($validated['user_id'])) {
            $calculated = collect();

            User::query()
                ->where('role', User::ROLE_USER)
                ->activeAccount()
                ->whereHas('currentPackage')
                ->orderBy('id')
                ->each(function (User $user) use ($calculated): void {
                    $bonusTransaction = $this->bonusService->calculateBinaryBonus($user);

                    if ($bonusTransaction) {
                        $calculated->push($bonusTransaction->load('walletTransaction'));
                    }
                });

            return response()->json([
                'message' => $calculated->isEmpty()
                    ? __('api.partner.binary_unavailable')
                    : __('api.partner.binary_calculated'),
                'calculated_count' => $calculated->count(),
                'bonus_transactions' => BonusTransactionResource::collection($calculated),
            ]);
        }

        $user = User::query()->activeAccount()->findOrFail($validated['user_id']);
        $bonusTransaction = $this->bonusService->calculateBinaryBonus($user);

        if (! $bonusTransaction) {
            return response()->json([
                'message' => __('api.partner.binary_unavailable'),
                'bonus_transaction' => null,
            ]);
        }

        return response()->json([
            'bonus_transaction' => BonusTransactionResource::make($bonusTransaction->load('walletTransaction')),
        ]);
    }

    public function recalculateAllBinary(Request $request): JsonResponse
    {
        $result = $this->bonusService->recalculateBinaryBonusesForAllPartners($request->user());

        return response()->json([
            'message' => __('api.bonus.recalculated'),
            ...$result,
        ]);
    }

    public function update(Request $request, BonusTransaction $bonus): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $bonus = $this->bonusAdjustmentService->updateAmount(
            $bonus,
            $request->user(),
            (string) $validated['amount'],
            $validated['reason'],
        );

        return response()->json([
            'message' => __('api.bonus.updated'),
            'bonus' => BonusTransactionResource::make($bonus),
        ]);
    }

    public function destroy(Request $request, BonusTransaction $bonus): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $this->bonusAdjustmentService->delete(
            $bonus,
            $request->user(),
            $validated['reason'],
        );

        return response()->json([
            'message' => __('api.bonus.deleted'),
            'bonus_id' => $bonus->id,
        ]);
    }

    protected function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 20), 1), 100);
    }
}

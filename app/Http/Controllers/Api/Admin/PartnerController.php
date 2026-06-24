<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartnerRequest;
use App\Http\Resources\BinaryNodeResource;
use App\Http\Resources\BonusTransactionResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\WalletTransactionResource;
use App\Models\AdminActionLog;
use App\Models\BinaryNode;
use App\Models\Package;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\BonusService;
use App\Services\DashboardBranchVolumeService;
use App\Services\PackageService;
use App\Services\PartnerDeletionService;
use App\Services\PartnerRegistrationService;
use App\Services\StatusBonusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartnerController extends Controller
{
    private const PARTNER_STATUSES = [
        'user',
        'manager',
        'leader',
        'director',
        'bronze_director',
        'silver_director',
        'gold_director',
        'platinum_director',
        'emerald_director',
        'diamond_director',
    ];

    public function __construct(
        private readonly PackageService $packageService,
        private readonly BonusService $bonusService,
        private readonly StatusBonusService $statusBonusService,
        private readonly PartnerRegistrationService $partnerRegistrationService,
        private readonly PartnerDeletionService $partnerDeletionService,
        private readonly DashboardBranchVolumeService $branchVolumeService,
    ) {
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $plainPassword = $validated['password'];
        $user = $this->createPartner($validated, $plainPassword, $request->user(), 'admin_partner_create');
        $placement = $this->placementPayload($user);

        return response()->json([
            'user' => UserResource::make($user),
            'credentials' => $this->credentialsFor($user, $plainPassword),
            'placement' => $placement,
            ...$placement,
        ], 201);
    }

    public function bulkCreate(Request $request): JsonResponse
    {
        $request->validate([
            'partners' => ['required', 'array', 'max:100'],
        ]);

        $created = [];
        $failed = [];

        foreach ($request->input('partners', []) as $index => $partnerData) {
            $validator = Validator::make((array) $partnerData, $this->createRules(), $this->createMessages());

            if ($validator->fails()) {
                $failed[] = [
                    'row' => $index,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $validated = $validator->validated();
            $plainPassword = $validated['password'];

            try {
                $user = $this->createPartner($validated, $plainPassword, $request->user(), 'admin_partner_bulk_create');
                $placement = $this->placementPayload($user);
                $created[] = [
                    'row' => $index,
                    'user' => UserResource::make($user),
                    'credentials' => $this->credentialsFor($user, $plainPassword),
                    'placement' => $placement,
                    ...$placement,
                ];
            } catch (\Throwable $exception) {
                report($exception);

                $failed[] = [
                    'row' => $index,
                    'errors' => [
                        'row' => ['Не удалось создать партнёра. Проверьте данные строки.'],
                    ],
                ];
            }
        }

        return response()->json([
            'created' => $created,
            'failed' => $failed,
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $partner = $this->loadPartner($user);

        return response()->json([
            'user' => UserResource::make($partner),
            'recent_transactions' => WalletTransactionResource::collection($this->recentTransactions($partner)),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'login' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'login')->whereNull('deleted_at')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255', $this->uniqueActivePhoneRule($user->id)],
        ]);

        $user->forceFill(collect($validated)->only(['name', 'login', 'email'])->all())->save();

        if (array_key_exists('phone', $validated)) {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                ['phone' => $validated['phone']]
            );
        }

        return response()->json([
            'user' => UserResource::make($this->loadPartner($user->refresh())),
        ]);
    }

    public function status(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(self::PARTNER_STATUSES)],
            'apply_bonus_effects' => ['sometimes', 'boolean'],
        ]);

        $user->forceFill(['status' => $validated['status']])->save();

        if ($request->boolean('apply_bonus_effects', false)) {
            $this->statusBonusService->awardManualStatusBonus($user, $validated['status']);
        }

        return response()->json([
            'user' => UserResource::make($this->loadPartner($user->refresh())),
            'recent_transactions' => WalletTransactionResource::collection($this->recentTransactions($user)),
        ]);
    }

    public function package(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')],
            'apply_business_effects' => ['sometimes', 'boolean'],
        ]);

        $package = Package::query()->findOrFail($validated['package_id']);
        $applyBusinessEffects = $request->boolean('apply_business_effects', true);
        $user = $this->packageService->assignPackageManually($user, $package, $applyBusinessEffects, $request->user());

        return response()->json([
            'user' => UserResource::make($this->loadPartner($user)),
        ]);
    }

    public function block(User $user): JsonResponse
    {
        $user->forceFill(['account_status' => 'blocked'])->save();
        $user->tokens()->delete();

        return response()->json([
            'user' => UserResource::make($this->loadPartner($user->refresh())),
        ]);
    }

    public function unblock(User $user): JsonResponse
    {
        $user->forceFill(['account_status' => 'active'])->save();

        return response()->json([
            'user' => UserResource::make($this->loadPartner($user->refresh())),
        ]);
    }

    public function note(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string'],
        ]);

        $user->forceFill(['admin_note' => $validated['admin_note'] ?? null])->save();

        return response()->json([
            'user' => UserResource::make($this->loadPartner($user->refresh())),
        ]);
    }

    public function balance(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Only super admin can update partner balance.');

        $validated = $request->validate([
            'mode' => ['required', 'string', Rule::in(['set', 'adjust'])],
            'amount' => ['required', 'numeric'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $mode = (string) $validated['mode'];
        $amount = $this->decimal($validated['amount']);
        $comment = trim((string) ($validated['comment'] ?? ''));

        if ($mode === 'set' && bccomp($amount, '0.00', 2) < 0) {
            throw ValidationException::withMessages([
                'amount' => ['Set amount must be greater than or equal to zero.'],
            ]);
        }

        $result = DB::transaction(function () use ($request, $user, $mode, $amount, $comment): array {
            $wallet = Wallet::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => 'main',
                ],
                [
                    'currency' => 'KZT',
                    'balance' => 0,
                    'hold_balance' => 0,
                    'status' => 'active',
                ],
            );

            $wallet = Wallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldBalance = $this->decimal($wallet->balance);
            $newBalance = $mode === 'set'
                ? $amount
                : $this->decimal(bcadd($oldBalance, $amount, 2));

            if (bccomp($newBalance, '0.00', 2) < 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Balance cannot be negative.'],
                ]);
            }

            $delta = $this->decimal(bcsub($newBalance, $oldBalance, 2));
            $transaction = null;

            if (bccomp($delta, '0.00', 2) !== 0) {
                $wallet->forceFill(['balance' => $newBalance])->save();

                $transaction = $wallet->transactions()->create([
                    'user_id' => $user->id,
                    'type' => 'manual_adjustment',
                    'direction' => bccomp($delta, '0.00', 2) > 0 ? 'credit' : 'debit',
                    'amount' => ltrim($delta, '-'),
                    'balance_before' => $oldBalance,
                    'balance_after' => $newBalance,
                    'status' => WalletTransaction::STATUS_COMPLETED,
                    'affects_balance' => true,
                    'description' => $comment !== '' ? $comment : null,
                    'metadata' => [
                        'mode' => $mode,
                        'requested_amount' => $amount,
                        'delta' => $delta,
                        'admin_id' => $request->user()?->id,
                    ],
                ]);
            }

            AdminActionLog::query()->create([
                'admin_id' => $request->user()?->id,
                'target_user_id' => $user->id,
                'action' => 'balance_update',
                'reason' => $comment !== '' ? $comment : null,
                'metadata' => [
                    'mode' => $mode,
                    'amount' => $amount,
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                    'delta' => $delta,
                    'wallet_id' => $wallet->id,
                    'wallet_transaction_id' => $transaction?->id,
                    'self_update' => (int) ($request->user()?->id ?? 0) === (int) $user->id,
                ],
            ]);

            return [
                'user_id' => $user->id,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'wallet_transaction' => $transaction,
            ];
        });

        return response()->json([
            'message' => 'Balance updated',
            'data' => [
                'user_id' => $result['user_id'],
                'old_balance' => (float) $result['old_balance'],
                'new_balance' => (float) $result['new_balance'],
            ],
            'user' => UserResource::make($this->loadPartner($user->refresh())),
            'recent_transactions' => WalletTransactionResource::collection($this->recentTransactions($user)),
        ]);
    }

    public function changePassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $plainPassword = $validated['password'];

        $user->forceFill([
            'password' => Hash::make($plainPassword),
        ])->save();

        return response()->json([
            'message' => 'Password changed',
            'credentials' => $this->credentialsFor($user->refresh(), $plainPassword),
        ]);
    }

    public function transactions(Request $request, User $user): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 10), 1), 50);

        return response()->json([
            'transactions' => WalletTransactionResource::collection($this->recentTransactions($user, $limit)),
        ]);
    }

    public function deletePreview(Request $request, User $user): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        return response()->json($this->partnerDeletionService->previewDelete(
            $user,
            $request->boolean('delete_subtree', false),
        ));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate([
            'delete_subtree' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $result = $this->partnerDeletionService->deletePartner(
            $user,
            $request->user(),
            (bool) ($validated['delete_subtree'] ?? false),
            $validated['reason'],
        );

        return response()->json([
            'message' => 'Партнёр удалён, перерасчёт выполнен',
            ...$result->toArray(),
        ]);
    }

    public function calculateBinaryBonus(User $user): JsonResponse
    {
        $bonusTransaction = $this->bonusService->calculateBinaryBonus($user);
        $partner = $this->loadPartner($user->refresh());

        if (! $bonusTransaction) {
            return response()->json([
                'message' => 'No binary bonus available.',
                'bonus_transaction' => null,
                'user' => UserResource::make($partner),
                'recent_transactions' => WalletTransactionResource::collection($this->recentTransactions($partner)),
            ]);
        }

        return response()->json([
            'message' => 'Binary bonus calculated.',
            'bonus_transaction' => BonusTransactionResource::make($bonusTransaction->load('walletTransaction')),
            'user' => UserResource::make($partner),
            'recent_transactions' => WalletTransactionResource::collection($this->recentTransactions($partner)),
        ]);
    }

    public function recalculateBinaryBonus(Request $request, User $user): JsonResponse
    {
        $result = $this->bonusService->recalculateBinaryBonus($user, $request->user());
        $partner = $this->loadPartner($user->refresh());
        $bonusTransaction = $result['bonus_transaction'];

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data'],
            'bonus_transaction' => $bonusTransaction
                ? BonusTransactionResource::make($bonusTransaction->load('walletTransaction'))
                : null,
            'user' => UserResource::make($partner),
            'recent_transactions' => WalletTransactionResource::collection($this->recentTransactions($partner)),
        ]);
    }

    public function tree(User $user): JsonResponse
    {
        $rootNode = $user->binaryNode()->where('is_active', true)->first();

        $nodes = BinaryNode::query()
            ->with(['user.profile', 'user.currentPackage'])
            ->when(! $rootNode, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($rootNode, function ($query) use ($rootNode): void {
                $query->where('id', $rootNode->id)
                    ->orWhere('path', 'like', $rootNode->path.'.%');
            })
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->activeAccount())
            ->orderBy('depth')
            ->orderBy('id')
            ->get();

        return response()->json([
            'nodes' => BinaryNodeResource::collection($nodes),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createPartner(array $validated, string $plainPassword, ?User $actor, string $source): User
    {
        return $this->partnerRegistrationService->register(
            data: $validated,
            actor: $actor,
            payReferralBonus: $this->shouldPayReferralBonus($validated),
            source: $source,
        );
    }

    private function loadPartner(User $user): User
    {
        $partner = $user->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
            ->loadCount(['referrals', 'invitedUsers as invited_count']);

        $branchVolumes = $this->branchVolumeService->getVolumesForRoot($partner);

        return $partner->setRawAttributes([
            ...$partner->getAttributes(),
            'left_pv' => $branchVolumes['left_pv'],
            'right_pv' => $branchVolumes['right_pv'],
        ], true);
    }

    private function recentTransactions(User $user, int $limit = 10)
    {
        return WalletTransaction::query()
            ->with(['user.profile', 'wallet'])
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    private function createRules(): array
    {
        $roles = array_keys((array) config('role_permissions.roles', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:255', Rule::unique('users', 'login')->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => ['required', 'string', 'min:6', 'max:32', $this->uniqueActivePhoneRule()],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'sponsor_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->whereNull('deleted_at')
                    ->where('account_status', 'active')
                    ->whereIn('role', [User::ROLE_USER, User::ROLE_SUPER_ADMIN]),
            ],
            'branch' => ['nullable', 'required_with:sponsor_id', 'string', Rule::in(['left', 'right', 'L', 'R'])],
            'package_id' => ['nullable', 'integer', Rule::exists('packages', 'id')],
            'pay_referral_bonus' => ['sometimes', 'boolean'],
            'role' => ['nullable', 'string', Rule::in($roles ?: [
                User::ROLE_USER,
                User::ROLE_SUPPORT,
                User::ROLE_ADMIN,
                User::ROLE_ACCOUNTANT,
                User::ROLE_SUPER_ADMIN,
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function createMessages(): array
    {
        return [
            'branch.required_with' => 'Выберите левую или правую ветку для выбранного спонсора.',
            'branch.in' => 'Ветка должна быть left или right.',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function shouldPayReferralBonus(array $validated): bool
    {
        return filter_var($validated['pay_referral_bonus'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Only super admin can delete partners.');
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function uniqueActivePhoneRule(?int $ignoreUserId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($ignoreUserId): void {
            $phone = trim((string) $value);

            if ($phone === '') {
                return;
            }

            $exists = UserProfile::query()
                ->where('phone', $phone)
                ->whereHas('user', function ($query) use ($ignoreUserId): void {
                    $query->activeAccount();

                    if ($ignoreUserId !== null) {
                        $query->whereKeyNot($ignoreUserId);
                    }
                })
                ->exists();

            if ($exists) {
                $fail('The phone has already been taken.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    private function credentialsFor(User $user, string $plainPassword): array
    {
        return [
            'login' => $user->login,
            'email' => $user->email,
            'password' => $plainPassword,
            'login_url' => 'https://safilife.kz/login',
        ];
    }

    /**
     * @return array{placement_parent_id: int|null, placement_branch: string|null, root_branch: string|null}
     */
    private function placementPayload(User $user): array
    {
        $node = $user->binaryNode()
            ->where('is_active', true)
            ->with('parent')
            ->first();

        return [
            'placement_parent_id' => $node?->parent?->user_id,
            'placement_branch' => $this->positionToBranch($node?->position),
            'root_branch' => $this->rootBranchFor($user, $node),
        ];
    }

    private function rootBranchFor(User $user, ?BinaryNode $node): ?string
    {
        if (! $node || ! $user->sponsor_id) {
            return null;
        }

        $sponsorNode = BinaryNode::query()
            ->where('user_id', $user->sponsor_id)
            ->where('is_active', true)
            ->first();

        if (! $sponsorNode) {
            return null;
        }

        $current = $node;

        while ($current && (int) $current->parent_id !== (int) $sponsorNode->id) {
            if ((int) $current->id === (int) $sponsorNode->id) {
                return null;
            }

            $current = $current->parent()
                ->where('is_active', true)
                ->first();
        }

        return $this->positionToBranch($current?->position);
    }

    private function positionToBranch(?string $position): ?string
    {
        return match ($position) {
            'L' => 'left',
            'R' => 'right',
            default => null,
        };
    }
}

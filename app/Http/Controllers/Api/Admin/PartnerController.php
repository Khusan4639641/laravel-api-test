<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartnerRequest;
use App\Http\Resources\BinaryNodeResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\WalletTransactionResource;
use App\Models\BinaryNode;
use App\Models\Package;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BinaryTreeService;
use App\Services\PackageService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        private readonly BinaryTreeService $binaryTreeService,
        private readonly PackageService $packageService,
        private readonly WalletService $walletService,
    ) {
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $plainPassword = $validated['password'];
        $user = DB::transaction(fn (): User => $this->createPartner($validated, $plainPassword));

        return response()->json([
            'user' => UserResource::make($user),
            'credentials' => $this->credentialsFor($user, $plainPassword),
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
            $validator = Validator::make((array) $partnerData, $this->createRules());

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
                $user = DB::transaction(fn (): User => $this->createPartner($validated, $plainPassword));
                $created[] = [
                    'row' => $index,
                    'user' => UserResource::make($user),
                    'credentials' => $this->credentialsFor($user, $plainPassword),
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
        return response()->json([
            'user' => UserResource::make($this->loadPartner($user)),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'login' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'login')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
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
        ]);

        $user->forceFill(['status' => $validated['status']])->save();

        return response()->json([
            'user' => UserResource::make($this->loadPartner($user->refresh())),
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
        $user = $this->packageService->assignPackageManually($user, $package, $applyBusinessEffects);

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

        $transactions = WalletTransaction::query()
            ->with(['user.profile', 'wallet'])
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'transactions' => WalletTransactionResource::collection($transactions),
        ]);
    }

    public function tree(User $user): JsonResponse
    {
        $rootNode = $user->binaryNode()->first();

        $nodes = BinaryNode::query()
            ->with(['user.profile', 'user.currentPackage'])
            ->when(! $rootNode, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($rootNode, function ($query) use ($rootNode): void {
                $query->where('id', $rootNode->id)
                    ->orWhere('path', 'like', $rootNode->path.'.%');
            })
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
    private function createPartner(array $validated, string $plainPassword): User
    {
        $sponsor = ! empty($validated['sponsor_id'])
            ? User::query()->findOrFail((int) $validated['sponsor_id'])
            : null;

        $user = User::query()->create([
            'name' => $validated['name'],
            'login' => $validated['login'],
            'email' => $validated['email'],
            'password' => Hash::make($plainPassword),
            'sponsor_id' => $sponsor?->id,
            'current_package_id' => null,
            'status' => 'user',
            'account_status' => 'active',
            'role' => $validated['role'] ?? User::ROLE_USER,
        ]);

        $nameParts = explode(' ', trim($validated['name']), 2);
        $user->profile()->create([
            'first_name' => $nameParts[0] ?? null,
            'last_name' => $nameParts[1] ?? null,
            'phone' => $validated['phone'] ?? null,
            'country' => 'Казахстан',
        ]);

        $this->walletService->createUserWallets($user);

        if ($sponsor) {
            $this->binaryTreeService->placeUser($user, $sponsor, $validated['branch'] ?? null);
        }

        return $this->loadPartner($user);
    }

    private function loadPartner(User $user): User
    {
        return $user->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
            ->loadCount(['referrals', 'invitedUsers as invited_count']);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    private function createRules(): array
    {
        $roles = array_keys((array) config('role_permissions.roles', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:255', Rule::unique('users', 'login')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'sponsor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'branch' => ['nullable', 'string', Rule::in(['left', 'right', 'L', 'R'])],
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
    private function credentialsFor(User $user, string $plainPassword): array
    {
        return [
            'login' => $user->login,
            'email' => $user->email,
            'password' => $plainPassword,
            'login_url' => 'https://safilife.kz/login',
        ];
    }
}

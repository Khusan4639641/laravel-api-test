<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PackageAutoUpgradeFromPaidOrdersService
{
    public const AUDIT_TRANSACTION_TYPE = 'package_auto_upgrade';

    private const PACKAGE_SEQUENCE = ['START', 'VIP', 'ELITE'];

    private const PACKAGE_RANKS = [
        'START' => 1,
        'VIP' => 2,
        'ELITE' => 3,
    ];

    private const PACKAGE_THRESHOLDS = [
        'START' => '60000.00',
        'VIP' => '180000.00',
        'ELITE' => '300000.00',
    ];

    private const CANCELLED_ORDER_STATUSES = ['cancelled', 'voided'];

    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    public function handlePaidOrder(Order $order): PackageUpgradeResult
    {
        return DB::transaction(function () use ($order): PackageUpgradeResult {
            /** @var Order|null $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder || ! $this->isEligiblePaidProductOrder($lockedOrder)) {
                return new PackageUpgradeResult(
                    userId: $lockedOrder?->user_id,
                    paidOrdersTotal: '0.00',
                    targetPackageCode: null,
                    finalPackage: null,
                );
            }

            /** @var User $user */
            $user = User::query()
                ->with('currentPackage')
                ->whereKey($lockedOrder->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $paidOrdersTotal = $this->paidProductOrdersTotal($user);
            $targetPackageCode = $this->targetPackageCode($paidOrdersTotal);

            if ($targetPackageCode === null) {
                return new PackageUpgradeResult(
                    userId: $user->id,
                    paidOrdersTotal: $paidOrdersTotal,
                    targetPackageCode: null,
                    finalPackage: $user->currentPackage,
                );
            }

            $currentRank = $this->packageRank($user->currentPackage);
            $targetRank = self::PACKAGE_RANKS[$targetPackageCode];

            if ($currentRank >= $targetRank) {
                return new PackageUpgradeResult(
                    userId: $user->id,
                    paidOrdersTotal: $paidOrdersTotal,
                    targetPackageCode: $targetPackageCode,
                    finalPackage: $user->currentPackage,
                );
            }

            $packages = Package::query()
                ->where('status', 'active')
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->filter(fn (Package $package): bool => in_array($this->normalizePackageCode($package->code), self::PACKAGE_SEQUENCE, true))
                ->keyBy(fn (Package $package): string => $this->normalizePackageCode($package->code));

            $upgradedPackages = [];

            foreach (self::PACKAGE_SEQUENCE as $packageCode) {
                $packageRank = self::PACKAGE_RANKS[$packageCode];

                if ($packageRank <= $currentRank || $packageRank > $targetRank) {
                    continue;
                }

                /** @var Package|null $package */
                $package = $packages->get($packageCode);

                if (! $package) {
                    continue;
                }

                $this->applyPackage($user, $package);
                $this->createNotification($user, $package, $lockedOrder, $paidOrdersTotal);
                $this->recordAuditTransaction($user, $package, $lockedOrder, $paidOrdersTotal);

                $upgradedPackages[] = $packageCode;
                $currentRank = $packageRank;
            }

            $finalPackage = $user->refresh()->load('currentPackage')->currentPackage;
            $this->appendOrderMetadata($lockedOrder, $paidOrdersTotal, $targetPackageCode, $upgradedPackages, $finalPackage);

            return new PackageUpgradeResult(
                userId: $user->id,
                paidOrdersTotal: $paidOrdersTotal,
                targetPackageCode: $targetPackageCode,
                finalPackage: $finalPackage,
                upgradedPackages: $upgradedPackages,
            );
        });
    }

    private function isEligiblePaidProductOrder(Order $order): bool
    {
        if ($order->payment_status !== 'paid') {
            return false;
        }

        if (in_array((string) $order->status, self::CANCELLED_ORDER_STATUSES, true)) {
            return false;
        }

        if ($order->isDepositPurchase()) {
            return false;
        }

        return $order->items->contains(fn ($item): bool => $item->product_id !== null);
    }

    private function paidProductOrdersTotal(User $user): string
    {
        $orders = Order::query()
            ->with('items.product')
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->whereNotIn('status', self::CANCELLED_ORDER_STATUSES)
            ->whereHas('items', fn ($query) => $query->whereNotNull('product_id'))
            ->get();

        $total = $orders
            ->filter(fn (Order $order): bool => $this->isEligiblePaidProductOrder($order))
            ->reduce(
                fn (string $carry, Order $order): string => bcadd($carry, (string) $order->total_amount, 2),
                '0.00',
            );

        return $this->decimal($total);
    }

    private function targetPackageCode(string $paidOrdersTotal): ?string
    {
        foreach (array_reverse(self::PACKAGE_SEQUENCE) as $packageCode) {
            if (bccomp($paidOrdersTotal, self::PACKAGE_THRESHOLDS[$packageCode], 2) >= 0) {
                return $packageCode;
            }
        }

        return null;
    }

    private function packageRank(?Package $package): int
    {
        if (! $package) {
            return 0;
        }

        return self::PACKAGE_RANKS[$this->normalizePackageCode($package->code)] ?? 0;
    }

    private function applyPackage(User $user, Package $package): void
    {
        $user->forceFill([
            'current_package_id' => $package->id,
            'total_pv' => $this->maxDecimal((string) $user->total_pv, $package->activityPv()),
        ])->save();
    }

    private function createNotification(User $user, Package $package, Order $order, string $paidOrdersTotal): void
    {
        $packageCode = $this->normalizePackageCode($package->code);
        $key = $this->autoUpgradeKey($user, $packageCode);
        $notificationType = $this->notificationType($packageCode);

        $exists = DB::table('notifications')
            ->where('type', $notificationType)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->exists();

        if ($exists) {
            return;
        }

        $message = "Поздравляем! Вы достигли пакета {$packageCode}.";

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => $notificationType,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'type' => 'package_auto_upgrade',
                'title' => [
                    'ru' => 'Новый пакет',
                    'kz' => 'Жаңа пакет',
                    'kg' => 'Жаңы пакет',
                    'en' => 'New package',
                    'mn' => 'Шинэ багц',
                ],
                'message' => [
                    'ru' => $message,
                    'kz' => $message,
                    'kg' => $message,
                    'en' => "Congratulations! You reached the {$packageCode} package.",
                    'mn' => $message,
                ],
                'package_id' => $package->id,
                'package_code' => $packageCode,
                'package_rank' => self::PACKAGE_RANKS[$packageCode],
                'source_order_id' => $order->id,
                'paid_orders_total' => $paidOrdersTotal,
                'threshold_amount' => self::PACKAGE_THRESHOLDS[$packageCode],
                'activity_pv' => $package->activityPv(),
                'auto_upgrade_key' => $key,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordAuditTransaction(User $user, Package $package, Order $order, string $paidOrdersTotal): void
    {
        $packageCode = $this->normalizePackageCode($package->code);

        $exists = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', self::AUDIT_TRANSACTION_TYPE)
            ->where('source_type', Package::class)
            ->where('source_id', $package->id)
            ->exists();

        if ($exists) {
            return;
        }

        $this->walletService->createUserWallets($user);

        $wallet = $user->wallets()
            ->where('type', 'main')
            ->lockForUpdate()
            ->firstOrFail();

        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'type' => self::AUDIT_TRANSACTION_TYPE,
            'direction' => 'neutral',
            'amount' => '0.00',
            'balance_before' => (string) $wallet->balance,
            'balance_after' => (string) $wallet->balance,
            'status' => 'completed',
            'affects_balance' => false,
            'source_type' => Package::class,
            'source_id' => $package->id,
            'description' => "Автоматическое достижение пакета {$packageCode} по сумме оплаченных заказов",
            'metadata' => [
                'auto_upgrade_key' => $this->autoUpgradeKey($user, $packageCode),
                'source' => 'paid_product_orders',
                'source_order_id' => $order->id,
                'package_id' => $package->id,
                'package_code' => $packageCode,
                'package_rank' => self::PACKAGE_RANKS[$packageCode],
                'paid_orders_total' => $paidOrdersTotal,
                'threshold_amount' => self::PACKAGE_THRESHOLDS[$packageCode],
                'activity_pv' => $package->activityPv(),
                'affects_balance' => false,
            ],
        ]);
    }

    /**
     * @param  array<int, string>  $upgradedPackages
     */
    private function appendOrderMetadata(
        Order $order,
        string $paidOrdersTotal,
        string $targetPackageCode,
        array $upgradedPackages,
        ?Package $finalPackage,
    ): void {
        if ($upgradedPackages === []) {
            return;
        }

        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $metadata['package_auto_upgrade'] = [
            'processed_at' => now()->toISOString(),
            'paid_orders_total' => $paidOrdersTotal,
            'target_package_code' => $targetPackageCode,
            'upgraded_packages' => $upgradedPackages,
            'final_package_id' => $finalPackage?->id,
            'final_package_code' => $finalPackage?->code,
        ];

        $order->forceFill(['metadata' => $metadata])->save();
    }

    private function autoUpgradeKey(User $user, string $packageCode): string
    {
        return "package_auto_upgrade:{$user->id}:{$packageCode}";
    }

    private function notificationType(string $packageCode): string
    {
        return 'package_auto_upgrade:'.$packageCode;
    }

    private function normalizePackageCode(mixed $code): string
    {
        $normalized = mb_strtoupper(trim((string) $code));

        return match ($normalized) {
            'СТАРТ' => 'START',
            'ЭЛИТ' => 'ELITE',
            default => $normalized,
        };
    }

    private function maxDecimal(string $left, string $right): string
    {
        return bccomp($left, $right, 2) >= 0 ? $this->decimal($left) : $this->decimal($right);
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}

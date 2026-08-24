<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\PvTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BinaryTreeService;
use App\Services\PackageAutoUpgradeFromPaidOrdersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageAutoUpgradeFromPaidOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'safi.user_package_changes_enabled' => false,
            'safi.user_package_purchases_enabled' => false,
        ]);
    }

    public function test_unpaid_order_does_not_activate_package(): void
    {
        $this->packages();
        $user = User::factory()->create();
        $order = $this->productOrder($user, 500000, paymentStatus: 'pending', status: 'pending');

        $result = $this->service()->handlePaidOrder($order);

        $this->assertFalse($result->upgraded());
        $this->assertNull($user->refresh()->current_package_id);
        $this->assertSame([], $this->notificationPackageCodes($user));
    }

    public function test_paid_order_below_start_threshold_does_not_activate_package(): void
    {
        $this->packages();
        $user = User::factory()->create();
        $order = $this->productOrder($user, 50000);

        $result = $this->service()->handlePaidOrder($order);

        $this->assertFalse($result->upgraded());
        $this->assertNull($user->refresh()->current_package_id);
        $this->assertSame(0, $this->autoUpgradeAuditCount($user));
    }

    public function test_paid_order_at_start_threshold_activates_start(): void
    {
        $this->packages();
        $user = User::factory()->create(['total_pv' => 0]);
        $order = $this->productOrder($user, 60000);

        $this->service()->handlePaidOrder($order);

        $this->assertUserPackage($user, 'START', '100.00');
        $this->assertSame(['START'], $this->notificationPackageCodes($user));
        $this->assertNoBalanceIncrease($user);
        $this->assertNoPackageTurnoverPv();
    }

    public function test_paid_order_at_vip_threshold_activates_start_then_vip(): void
    {
        $this->packages();
        $user = User::factory()->create(['total_pv' => 0]);
        $order = $this->productOrder($user, 180000);

        $this->service()->handlePaidOrder($order);

        $this->assertUserPackage($user, 'VIP', '300.00');
        $this->assertSame(['START', 'VIP'], $this->notificationPackageCodes($user));
        $this->assertNoBalanceIncrease($user);
        $this->assertNoPackageTurnoverPv();
        $this->assertSame(0, WalletTransaction::query()->whereIn('type', ['package_activation', 'package_upgrade'])->count());
    }

    public function test_paid_order_at_elite_threshold_activates_start_vip_then_elite(): void
    {
        $this->packages();
        $user = User::factory()->create(['total_pv' => 0]);
        $order = $this->productOrder($user, 500000);

        $this->service()->handlePaidOrder($order);

        $this->assertUserPackage($user, 'ELITE', '500.00');
        $this->assertSame(['START', 'VIP', 'ELITE'], $this->notificationPackageCodes($user));
        $this->assertNoBalanceIncrease($user);
        $this->assertSame(3, $this->autoUpgradeAuditCount($user));
    }

    public function test_start_user_with_large_paid_total_upgrades_vip_then_elite(): void
    {
        [$start] = $this->packages();
        $user = User::factory()->create([
            'current_package_id' => $start->id,
            'total_pv' => 100,
        ]);
        $order = $this->productOrder($user, 500000);

        $this->service()->handlePaidOrder($order);

        $this->assertUserPackage($user, 'ELITE', '500.00');
        $this->assertSame(['VIP', 'ELITE'], $this->notificationPackageCodes($user));
    }

    public function test_vip_user_with_large_paid_total_upgrades_only_to_elite(): void
    {
        [, $vip] = $this->packages();
        $user = User::factory()->create([
            'current_package_id' => $vip->id,
            'total_pv' => 300,
        ]);
        $order = $this->productOrder($user, 500000);

        $this->service()->handlePaidOrder($order);

        $this->assertUserPackage($user, 'ELITE', '500.00');
        $this->assertSame(['ELITE'], $this->notificationPackageCodes($user));
    }

    public function test_elite_user_stays_elite_without_duplicate_notifications(): void
    {
        [, , $elite] = $this->packages();
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'total_pv' => 500,
        ]);
        $order = $this->productOrder($user, 500000);

        $this->service()->handlePaidOrder($order);

        $this->assertUserPackage($user, 'ELITE', '500.00');
        $this->assertSame([], $this->notificationPackageCodes($user));
        $this->assertSame(0, $this->autoUpgradeAuditCount($user));
    }

    public function test_multiple_smaller_paid_orders_accumulate_to_package_thresholds(): void
    {
        $this->packages();
        $user = User::factory()->create(['total_pv' => 0]);

        $this->service()->handlePaidOrder($this->productOrder($user, 100000));
        $this->assertUserPackage($user, 'START', '100.00');

        $this->service()->handlePaidOrder($this->productOrder($user, 80000));
        $this->assertUserPackage($user, 'VIP', '300.00');

        $this->service()->handlePaidOrder($this->productOrder($user, 120000));
        $this->assertUserPackage($user, 'ELITE', '500.00');

        $this->assertSame(['START', 'VIP', 'ELITE'], $this->notificationPackageCodes($user));
    }

    public function test_deposit_paid_orders_do_not_count_toward_auto_package_upgrade(): void
    {
        $this->packages();
        $user = User::factory()->create(['total_pv' => 0]);
        $depositOrder = $this->depositProductOrder($user, 300000);
        $normalOrder = $this->productOrder($user, 50000);

        $depositResult = $this->service()->handlePaidOrder($depositOrder);
        $normalResult = $this->service()->handlePaidOrder($normalOrder);

        $this->assertFalse($depositResult->upgraded());
        $this->assertFalse($normalResult->upgraded());
        $this->assertNull($user->refresh()->current_package_id);
        $this->assertSame('0.00', $user->total_pv);
        $this->assertSame([], $this->notificationPackageCodes($user));
        $this->assertSame(0, $this->autoUpgradeAuditCount($user));
    }

    public function test_refunded_or_cancelled_order_no_longer_counts_but_never_downgrades_package(): void
    {
        $this->packages();
        $user = User::factory()->create(['total_pv' => 0]);
        $order = $this->productOrder($user, 300000);

        $this->service()->handlePaidOrder($order);
        $this->assertUserPackage($user, 'ELITE', '500.00');

        $order->forceFill([
            'payment_status' => 'refunded',
            'status' => 'cancelled',
        ])->save();

        $this->service()->handlePaidOrder($order->refresh());

        $this->assertUserPackage($user, 'ELITE', '500.00');
        $this->assertSame(['START', 'VIP', 'ELITE'], $this->notificationPackageCodes($user));
    }

    public function test_repeated_pay_webhook_is_idempotent_for_auto_package_upgrade(): void
    {
        $this->packages();
        $this->enableTipTopPay();
        $user = User::factory()->create(['total_pv' => 0]);
        $order = $this->productOrder(
            $user,
            500000,
            paymentStatus: 'pending',
            status: 'pending',
            externalId: 'order-auto-upgrade-duplicate-001',
        );
        $payload = $this->webhookPayload($order);

        $this->postJson('/api/payments/tiptoppay/pay', $payload)->assertOk()->assertJsonPath('code', 0);
        $this->postJson('/api/payments/tiptoppay/pay', $payload)->assertOk()->assertJsonPath('code', 0);

        $this->assertUserPackage($user, 'ELITE', '500.00');
        $this->assertSame(['START', 'VIP', 'ELITE'], $this->notificationPackageCodes($user));
        $this->assertSame(3, $this->autoUpgradeAuditCount($user));
        $this->assertSame(1, $user->walletTransactions()->where('type', 'order_payment')->count());
        $this->assertNoBalanceIncrease($user);
    }

    public function test_auto_package_upgrade_does_not_create_referral_bonus(): void
    {
        $this->packages();
        $sponsor = User::factory()->create();
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'total_pv' => 0,
        ]);

        $this->service()->handlePaidOrder($this->productOrder($user, 500000));

        $this->assertUserPackage($user, 'ELITE', '500.00');
        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'referral')->count());
    }

    public function test_auto_package_upgrade_does_not_create_package_pv_turnover(): void
    {
        $this->packages();
        $root = User::factory()->create();
        $user = User::factory()->create(['total_pv' => 0]);

        app(BinaryTreeService::class)->placeUser($root);
        app(BinaryTreeService::class)->placeUser($user, $root, 'L');

        $this->service()->handlePaidOrder($this->productOrder($user, 500000));

        $this->assertUserPackage($user, 'ELITE', '500.00');
        $this->assertNoPackageTurnoverPv();
    }

    public function test_admin_marking_order_as_paid_triggers_auto_package_upgrade(): void
    {
        $this->packages();
        $user = User::factory()->create(['total_pv' => 0]);
        $order = $this->productOrder($user, 300000, paymentStatus: 'unpaid', status: 'pending');

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->patchJson("/api/admin/orders/{$order->id}/payment-status", [
            'payment_status' => 'paid',
        ])
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'paid');

        $this->assertUserPackage($user, 'ELITE', '500.00');
        $this->assertSame(['START', 'VIP', 'ELITE'], $this->notificationPackageCodes($user));
    }

    public function test_order_tiptop_pay_still_works_and_package_payment_intent_remains_disabled(): void
    {
        [$start] = $this->packages();
        $this->enableTipTopPay();
        $user = User::factory()->create();
        $order = $this->productOrder($user, 30000, paymentStatus: 'unpaid', status: 'pending');

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertOk()
            ->assertJsonPath('intent.metadata.type', 'order')
            ->assertJsonPath('intent.metadata.order_id', $order->id);

        $this->postJson("/api/dashboard/package/{$start->id}/payments/tiptoppay/intent", [
            'package_code' => 'START',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Покупка пакетов пользователем временно недоступна. Обратитесь к администратору.');
    }

    private function service(): PackageAutoUpgradeFromPaidOrdersService
    {
        return app(PackageAutoUpgradeFromPaidOrdersService::class);
    }

    /**
     * @return array{0: Package, 1: Package, 2: Package}
     */
    private function packages(): array
    {
        return [
            $this->package('START'),
            $this->package('VIP'),
            $this->package('ELITE'),
        ];
    }

    private function package(string $code): Package
    {
        $matrix = [
            'START' => [60000, 100, 100, 7, 1],
            'VIP' => [180000, 300, 300, 8, 2],
            'ELITE' => [300000, 500, 200, 10, 3],
        ];
        [$price, $activityPv, $turnoverPv, $binaryPercent, $sortOrder] = $matrix[$code];

        return Package::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $code,
                'slug' => strtolower($code).'-'.uniqid(),
                'price' => $price,
                'pv' => $activityPv,
                'activity_pv' => $activityPv,
                'turnover_pv' => $turnoverPv,
                'referral_percent' => 10,
                'binary_percent' => $binaryPercent,
                'sort_order' => $sortOrder,
                'status' => 'active',
                'is_active' => true,
                'is_upgradeable' => true,
            ],
        );
    }

    private function productOrder(
        User $user,
        int $amount,
        string $paymentStatus = 'paid',
        string $status = 'confirmed',
        ?string $externalId = null,
    ): Order {
        $product = Product::query()->create([
            'name' => 'Safi Product '.$amount,
            'sku' => 'SAFI-'.uniqid(),
            'description' => 'Safi Product',
            'price' => $amount,
            'pv' => max(1, (int) round($amount / Product::PV_MONEY_RATE)),
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'status' => 'active',
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-'.uniqid(),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_provider' => $externalId ? 'tiptoppay' : null,
            'payment_external_id' => $externalId,
            'paid_at' => $paymentStatus === 'paid' ? now() : null,
            'subtotal_amount' => $amount,
            'discount_amount' => 0,
            'total_amount' => $amount,
            'total_pv' => Product::priceToTurnoverPv($amount),
            'recipient_name' => 'Safi Client',
            'phone' => '+77010000000',
            'city' => 'Almaty',
            'delivery_address' => 'Abay 10',
            'shipping_address' => [
                'recipient_name' => 'Safi Client',
                'phone' => '+77010000000',
                'city' => 'Almaty',
                'delivery_address' => 'Abay 10',
                'address' => 'Abay 10',
            ],
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $amount,
            'unit_pv' => Product::priceToTurnoverPv($amount),
            'total_price' => $amount,
            'total_pv' => Product::priceToTurnoverPv($amount),
            'item_snapshot' => ['name' => $product->name],
        ]);

        return $order;
    }

    private function depositProductOrder(User $user, int $amount): Order
    {
        $product = Product::query()->create([
            'name' => 'Deposit Product '.$amount,
            'sku' => 'DEP-'.uniqid(),
            'description' => 'Deposit Product',
            'price' => $amount,
            'pv' => max(1, (int) round($amount / Product::PV_MONEY_RATE)),
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'status' => 'active',
            'is_deposit_product' => true,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'DEP-'.uniqid(),
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_provider' => Order::PAYMENT_PROVIDER_DEPOSIT,
            'paid_at' => now(),
            'subtotal_amount' => $amount,
            'discount_amount' => 0,
            'total_amount' => $amount,
            'total_pv' => 0,
            'metadata' => [
                'source' => Order::SOURCE_DEPOSIT_PURCHASE,
                'payment_wallet' => 'deposit',
            ],
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $amount,
            'unit_pv' => 0,
            'total_price' => $amount,
            'total_pv' => 0,
            'item_snapshot' => [
                'name' => $product->name,
                'is_deposit_product' => true,
            ],
        ]);

        return $order;
    }

    private function assertUserPackage(User $user, string $packageCode, string $totalPv): void
    {
        $user->refresh()->load('currentPackage');

        $this->assertSame($packageCode, $user->currentPackage?->code);
        $this->assertSame($totalPv, $user->total_pv);
    }

    /**
     * @return array<int, string>
     */
    private function notificationPackageCodes(User $user): array
    {
        return $user->notifications()
            ->get()
            ->map(fn ($notification): array => $notification->data)
            ->filter(fn (array $data): bool => ($data['type'] ?? null) === 'package_auto_upgrade')
            ->sortBy(fn (array $data): int => (int) ($data['package_rank'] ?? 0))
            ->pluck('package_code')
            ->values()
            ->all();
    }

    private function autoUpgradeAuditCount(User $user): int
    {
        return WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', PackageAutoUpgradeFromPaidOrdersService::AUDIT_TRANSACTION_TYPE)
            ->count();
    }

    private function assertNoBalanceIncrease(User $user): void
    {
        $mainWallet = $user->refresh()->wallets()->where('type', 'main')->first();

        $this->assertSame('0.00', (string) ($mainWallet?->balance ?? '0.00'));
        $this->assertSame(0, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('affects_balance', true)
            ->where('direction', 'credit')
            ->count());
    }

    private function assertNoPackageTurnoverPv(): void
    {
        $this->assertSame(0, PvTransaction::query()
            ->whereIn('source', ['package_start', 'package_vip', 'package_activation', 'package_upgrade', 'package_elite_upgrade'])
            ->count());
    }

    private function enableTipTopPay(): void
    {
        Config::set('tiptoppay.enabled', true);
        Config::set('tiptoppay.public_terminal_id', 'pk_test');
        Config::set('tiptoppay.payment_schema', 'Single');
        Config::set('tiptoppay.currency', 'KZT');
        Config::set('tiptoppay.webhook_secret', '');
        Config::set('tiptoppay.success_url', 'https://safilife.test/payment/success');
        Config::set('tiptoppay.fail_url', 'https://safilife.test/payment/fail');
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookPayload(Order $order): array
    {
        return [
            'InvoiceId' => $order->payment_external_id,
            'TransactionId' => 'pay-transaction-auto-upgrade',
            'Amount' => (float) $order->total_amount,
            'Currency' => 'KZT',
            'Data' => [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
            ],
        ];
    }
}

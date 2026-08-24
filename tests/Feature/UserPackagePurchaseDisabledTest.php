<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserPackagePurchaseDisabledTest extends TestCase
{
    use RefreshDatabase;

    private const DISABLED_MESSAGE = 'Покупка пакетов пользователем временно недоступна. Обратитесь к администратору.';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'safi.user_package_changes_enabled' => false,
            'safi.user_package_purchases_enabled' => false,
        ]);
    }

    public function test_authenticated_user_cannot_create_package_tiptop_pay_intent(): void
    {
        $this->enableTipTopPay();
        $start = $this->package('START');

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/dashboard/package/{$start->id}/payments/tiptoppay/intent", [
            'package_code' => 'START',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', self::DISABLED_MESSAGE);

        $this->postJson('/api/payments/tiptoppay/package-intent', [
            'package_code' => 'START',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', self::DISABLED_MESSAGE);
    }

    public function test_authenticated_user_cannot_activate_package_directly(): void
    {
        $start = $this->package('START');

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/packages/{$start->id}/activate")
            ->assertForbidden()
            ->assertJsonPath('message', self::DISABLED_MESSAGE);
    }

    public function test_authenticated_user_cannot_upgrade_package_directly(): void
    {
        $start = $this->package('START');
        $vip = $this->package('VIP');
        $user = User::factory()->create(['current_package_id' => $start->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$vip->id}/upgrade")
            ->assertForbidden()
            ->assertJsonPath('message', self::DISABLED_MESSAGE);
    }

    public function test_dashboard_packages_return_informational_status_labels(): void
    {
        [$start, $vip, $elite] = $this->packages();

        $this->assertPackageLabels(User::factory()->create(), [
            'START' => 'Вы еще не приобрели',
            'VIP' => 'Вы еще не приобрели',
            'ELITE' => 'Вы еще не приобрели',
        ]);

        $this->assertPackageLabels(User::factory()->create(['current_package_id' => $start->id]), [
            'START' => 'Ваш текущий пакет',
            'VIP' => 'Вы еще не приобрели',
            'ELITE' => 'Вы еще не приобрели',
        ]);

        $this->assertPackageLabels(User::factory()->create(['current_package_id' => $vip->id]), [
            'START' => 'Уже приобрели',
            'VIP' => 'Ваш текущий пакет',
            'ELITE' => 'Вы еще не приобрели',
        ]);

        $this->assertPackageLabels(User::factory()->create(['current_package_id' => $elite->id]), [
            'START' => 'Уже приобрели',
            'VIP' => 'Уже приобрели',
            'ELITE' => 'Ваш текущий пакет',
        ]);
    }

    public function test_admin_can_still_assign_package_to_partner(): void
    {
        $partner = User::factory()->create();
        $vip = $this->package('VIP');

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $vip->id,
            'apply_business_effects' => false,
        ])
            ->assertOk()
            ->assertJsonPath('user.current_package_id', $vip->id);

        $this->assertSame($vip->id, $partner->refresh()->current_package_id);
    }

    public function test_order_tiptop_pay_intent_still_works(): void
    {
        $this->enableTipTopPay();
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payment/tiptoppay/intent")
            ->assertOk()
            ->assertJsonPath('intent.currency', 'KZT')
            ->assertJsonPath('intent.amount', 30000)
            ->assertJsonPath('intent.metadata.type', 'order')
            ->assertJsonPath('intent.metadata.order_id', $order->id);
    }

    /**
     * @param  array<string, string>  $expectedLabels
     */
    private function assertPackageLabels(User $user, array $expectedLabels): void
    {
        Sanctum::actingAs($user);

        $packages = collect($this->getJson('/api/dashboard/packages')->assertOk()->json('packages'))->keyBy('code');

        foreach ($expectedLabels as $code => $label) {
            $this->assertFalse((bool) $packages[$code]['available']);
            $this->assertSame($label, $packages[$code]['button_label']);
        }
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
            'START' => [60000, 100, 100, 7],
            'VIP' => [180000, 300, 300, 8],
            'ELITE' => [300000, 500, 200, 10],
        ];
        [$price, $activityPv, $turnoverPv, $binaryPercent] = $matrix[$code];

        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code).'-'.uniqid(),
            'price' => $price,
            'pv' => $activityPv,
            'activity_pv' => $activityPv,
            'turnover_pv' => $turnoverPv,
            'referral_percent' => 10,
            'binary_percent' => $binaryPercent,
            'sort_order' => $code === 'START' ? 1 : ($code === 'VIP' ? 2 : 3),
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }

    private function orderFor(User $user): Order
    {
        $product = Product::query()->create([
            'name' => 'ActiveYM',
            'sku' => 'PRD-'.uniqid(),
            'price' => 30000,
            'pv' => 60,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-'.uniqid(),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal_amount' => 30000,
            'discount_amount' => 0,
            'total_amount' => 30000,
            'total_pv' => 60,
            'recipient_name' => 'Client',
            'phone' => '+77010000000',
            'city' => 'Алматы',
            'delivery_address' => 'Абая 10',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 30000,
            'unit_pv' => 60,
            'total_price' => 30000,
            'total_pv' => 60,
        ]);

        return $order;
    }
}

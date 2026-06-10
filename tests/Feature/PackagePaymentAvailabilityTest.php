<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackagePaymentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_package_can_pay_start_only(): void
    {
        $this->enableTipTopPay();
        $this->packages();
        Sanctum::actingAs(User::factory()->create());

        $packages = $this->dashboardPackages();

        $this->assertTrue($packages['START']['available']);
        $this->assertSame('pay', $packages['START']['action']);
        $this->assertSame('Оплатить онлайн', $packages['START']['button_label']);
        $this->assertSame(60000, $packages['START']['paymentAmount']);

        $this->assertFalse($packages['VIP']['available']);
        $this->assertSame('locked', $packages['VIP']['action']);
        $this->assertSame('Сначала подключите START', $packages['VIP']['disabled_reason']);

        $this->assertFalse($packages['ELITE']['available']);
        $this->assertSame('locked', $packages['ELITE']['action']);
        $this->assertSame('Сначала подключите START и VIP', $packages['ELITE']['disabled_reason']);
    }

    public function test_user_without_package_cannot_pay_vip(): void
    {
        $this->enableTipTopPay();
        [, $vip] = $this->packages();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/dashboard/package/{$vip->id}/payments/tiptoppay/intent", [
            'package_code' => 'VIP',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Сначала подключите START')
            ->assertJsonValidationErrors('package');
    }

    public function test_user_without_package_cannot_pay_elite(): void
    {
        $this->enableTipTopPay();
        [, , $elite] = $this->packages();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/dashboard/package/{$elite->id}/payments/tiptoppay/intent", [
            'package_code' => 'ELITE',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Сначала подключите START и VIP')
            ->assertJsonValidationErrors('package');
    }

    public function test_start_user_can_pay_vip_upgrade(): void
    {
        $this->enableTipTopPay();
        [$start, $vip] = $this->packages();
        Sanctum::actingAs(User::factory()->create(['current_package_id' => $start->id]));

        $packages = $this->dashboardPackages();

        $this->assertTrue($packages['START']['current']);
        $this->assertSame('current', $packages['START']['action']);
        $this->assertTrue($packages['VIP']['available']);
        $this->assertSame('upgrade', $packages['VIP']['action']);
        $this->assertSame(120000, $packages['VIP']['paymentAmount']);

        $this->postJson("/api/dashboard/package/{$vip->id}/payments/tiptoppay/intent", [
            'package_code' => 'VIP',
        ])
            ->assertOk()
            ->assertJsonPath('intent.amount', 120000)
            ->assertJsonPath('intent.metadata.upgrade_from', 'START');
    }

    public function test_start_user_cannot_pay_elite_directly(): void
    {
        $this->enableTipTopPay();
        [$start, , $elite] = $this->packages();
        Sanctum::actingAs(User::factory()->create(['current_package_id' => $start->id]));

        $this->postJson("/api/dashboard/package/{$elite->id}/payments/tiptoppay/intent", [
            'package_code' => 'ELITE',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Сначала перейдите на VIP')
            ->assertJsonValidationErrors('package');
    }

    public function test_vip_user_can_pay_elite_upgrade(): void
    {
        $this->enableTipTopPay();
        [, $vip, $elite] = $this->packages();
        Sanctum::actingAs(User::factory()->create(['current_package_id' => $vip->id]));

        $packages = $this->dashboardPackages();

        $this->assertTrue($packages['VIP']['current']);
        $this->assertSame('current', $packages['VIP']['action']);
        $this->assertTrue($packages['ELITE']['available']);
        $this->assertSame('upgrade', $packages['ELITE']['action']);
        $this->assertSame(120000, $packages['ELITE']['paymentAmount']);

        $this->postJson("/api/dashboard/package/{$elite->id}/payments/tiptoppay/intent", [
            'package_code' => 'ELITE',
        ])
            ->assertOk()
            ->assertJsonPath('intent.amount', 120000)
            ->assertJsonPath('intent.metadata.upgrade_from', 'VIP');
    }

    public function test_elite_user_cannot_pay_another_package(): void
    {
        $this->enableTipTopPay();
        [, , $elite] = $this->packages();
        Sanctum::actingAs(User::factory()->create(['current_package_id' => $elite->id]));

        $packages = $this->dashboardPackages();

        $this->assertSame('passed', $packages['START']['action']);
        $this->assertSame('passed', $packages['VIP']['action']);
        $this->assertSame('current', $packages['ELITE']['action']);
        $this->assertTrue($packages['ELITE']['current']);
        $this->assertFalse($packages['START']['available']);
        $this->assertFalse($packages['VIP']['available']);
    }

    public function test_package_intent_uses_backend_amount_not_frontend_amount(): void
    {
        $this->enableTipTopPay();
        [$start, $vip] = $this->packages();
        $user = User::factory()->create(['current_package_id' => $start->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/dashboard/package/{$vip->id}/payments/tiptoppay/intent", [
            'package_code' => 'VIP',
            'amount' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('intent.amount', 120000);

        $this->assertDatabaseHas('payments', [
            'id' => $response->json('payment_id'),
            'user_id' => $user->id,
            'type' => Payment::TYPE_PACKAGE,
            'status' => Payment::STATUS_PENDING,
            'amount' => '120000.00',
        ]);
    }

    public function test_package_is_not_activated_before_pay_webhook(): void
    {
        $this->enableTipTopPay();
        [$start] = $this->packages();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/dashboard/package/{$start->id}/payments/tiptoppay/intent", [
            'package_code' => 'START',
        ])->assertOk();

        $this->assertNull($user->refresh()->current_package_id);
    }

    public function test_pay_webhook_activates_package(): void
    {
        $this->enableTipTopPay();
        [$start] = $this->packages();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $externalId = $this->postJson("/api/dashboard/package/{$start->id}/payments/tiptoppay/intent", [
            'package_code' => 'START',
        ])
            ->assertOk()
            ->json('external_id');

        $this->postJson('/api/payments/tiptoppay/pay', $this->webhookPayload($externalId, 60000))
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertSame($start->id, $user->refresh()->current_package_id);
        $this->assertSame('100.00', $user->total_pv);
    }

    public function test_package_payment_does_not_increase_buyer_balance(): void
    {
        $this->enableTipTopPay();
        [$start] = $this->packages();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $externalId = $this->postJson("/api/dashboard/package/{$start->id}/payments/tiptoppay/intent", [
            'package_code' => 'START',
        ])
            ->assertOk()
            ->json('external_id');

        $this->postJson('/api/payments/tiptoppay/pay', $this->webhookPayload($externalId, 60000))->assertOk();

        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $transaction = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'package_activation')
            ->firstOrFail();

        $this->assertSame('0.00', $wallet->balance);
        $this->assertSame('60000.00', $transaction->amount);
        $this->assertFalse((bool) $transaction->affects_balance);
    }

    public function test_missing_tiptop_config_makes_package_payment_unavailable(): void
    {
        $this->enableTipTopPay(publicTerminalId: '');
        $this->packages();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $packages = $this->dashboardPackages();

        $this->assertFalse($packages['START']['available']);
        $this->assertSame('locked', $packages['START']['action']);
        $this->assertSame('Онлайн-оплата временно недоступна', $packages['START']['disabled_reason']);

        $this->postJson("/api/dashboard/package/{$packages['START']['id']}/payments/tiptoppay/intent", [
            'package_code' => 'START',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'TipTop Pay terminal is not configured')
            ->assertJsonValidationErrors('publicTerminalId');
    }

    private function enableTipTopPay(string $publicTerminalId = 'pk_test'): void
    {
        Config::set('tiptoppay.enabled', true);
        Config::set('tiptoppay.public_terminal_id', $publicTerminalId);
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

    /**
     * @return array<string, array<string, mixed>>
     */
    private function dashboardPackages(): array
    {
        return collect($this->getJson('/api/dashboard/packages')->assertOk()->json('packages'))
            ->keyBy('code')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookPayload(string $externalId, int $amount): array
    {
        return [
            'InvoiceId' => $externalId,
            'TransactionId' => 'pkg-'.uniqid(),
            'Amount' => $amount,
            'Currency' => 'KZT',
        ];
    }
}

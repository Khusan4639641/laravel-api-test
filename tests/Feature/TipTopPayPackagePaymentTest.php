<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipTopPayPackagePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_start_package_payment_intent(): void
    {
        $this->enableTipTopPay();
        $start = $this->package('START');
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/dashboard/package/{$start->id}/payments/tiptoppay/intent", ['package_code' => 'START'])
            ->assertOk()
            ->assertJsonPath('intent.currency', 'KZT')
            ->assertJsonPath('intent.amount', 60000)
            ->assertJsonPath('intent.metadata.type', 'package')
            ->assertJsonPath('intent.metadata.package_code', 'START');
    }

    public function test_start_first_purchase_amount_is_backend_calculated_and_vip_is_locked_until_start(): void
    {
        $this->enableTipTopPay();
        $start = $this->package('START');
        $vip = $this->package('VIP');
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/dashboard/package/{$start->id}/payments/tiptoppay/intent", ['package_code' => 'START'])
            ->assertOk()
            ->assertJsonPath('intent.amount', 60000);

        $this->postJson("/api/dashboard/package/{$vip->id}/payments/tiptoppay/intent", ['package_code' => 'VIP'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package')
            ->assertJsonPath('message', 'Сначала подключите START');
    }

    public function test_start_to_vip_upgrade_amount_is_120000(): void
    {
        $this->enableTipTopPay();
        $start = $this->package('START');
        $vip = $this->package('VIP');
        $user = User::factory()->create([
            'current_package_id' => $start->id,
            'total_pv' => 100,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/dashboard/package/{$vip->id}/payments/tiptoppay/intent", [
            'package_code' => 'VIP',
            'upgrade_from' => 'START',
        ])
            ->assertOk()
            ->assertJsonPath('intent.amount', 120000)
            ->assertJsonPath('intent.metadata.upgrade_from', 'START');
    }

    public function test_vip_to_elite_upgrade_amount_is_120000(): void
    {
        $this->enableTipTopPay();
        $vip = $this->package('VIP');
        $elite = $this->package('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $vip->id,
            'total_pv' => 300,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/dashboard/package/{$elite->id}/payments/tiptoppay/intent", [
            'package_code' => 'ELITE',
            'upgrade_from' => 'VIP',
        ])
            ->assertOk()
            ->assertJsonPath('intent.amount', 120000)
            ->assertJsonPath('intent.metadata.upgrade_from', 'VIP');
    }

    public function test_elite_cannot_be_first_package_and_start_to_elite_is_blocked(): void
    {
        $this->enableTipTopPay();
        $start = $this->package('START');
        $elite = $this->package('ELITE');
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/dashboard/package/{$elite->id}/payments/tiptoppay/intent", ['package_code' => 'ELITE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');

        $user->forceFill(['current_package_id' => $start->id, 'total_pv' => 100])->save();

        $this->postJson("/api/dashboard/package/{$elite->id}/payments/tiptoppay/intent", [
            'package_code' => 'ELITE',
            'upgrade_from' => 'START',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');
    }

    public function test_package_is_not_activated_before_pay_webhook_and_pay_activates_it(): void
    {
        $this->enableTipTopPay();
        $start = $this->package('START');
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $externalId = $this->postJson("/api/dashboard/package/{$start->id}/payments/tiptoppay/intent", ['package_code' => 'START'])
            ->assertOk()
            ->json('external_id');

        $this->assertNull($user->refresh()->current_package_id);

        $this->postJson('/api/payments/tiptoppay/pay', $this->webhookPayload($externalId, 60000))
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertSame($start->id, $user->refresh()->current_package_id);
        $this->assertSame('100.00', $user->total_pv);
        $this->assertSame('paid', Payment::query()->where('external_id', $externalId)->firstOrFail()->status);
    }

    public function test_package_payment_does_not_increase_buyer_balance_and_transaction_affects_balance_false(): void
    {
        $this->enableTipTopPay();
        $start = $this->package('START');
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $externalId = $this->postJson("/api/dashboard/package/{$start->id}/payments/tiptoppay/intent", ['package_code' => 'START'])
            ->assertOk()
            ->json('external_id');

        $this->postJson('/api/payments/tiptoppay/pay', $this->webhookPayload($externalId, 60000))->assertOk();

        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $transaction = $user->walletTransactions()->where('type', 'package_activation')->firstOrFail();

        $this->assertSame('0.00', $wallet->balance);
        $this->assertSame('60000.00', $transaction->amount);
        $this->assertFalse((bool) $transaction->affects_balance);
    }

    public function test_start_vip_referral_bonus_rules_still_work(): void
    {
        $this->enableTipTopPay();
        $start = $this->package('START');
        $sponsor = User::factory()->create();
        $user = User::factory()->create(['sponsor_id' => $sponsor->id]);

        Sanctum::actingAs($user);
        $externalId = $this->postJson("/api/dashboard/package/{$start->id}/payments/tiptoppay/intent", ['package_code' => 'START'])
            ->assertOk()
            ->json('external_id');

        $this->postJson('/api/payments/tiptoppay/pay', $this->webhookPayload($externalId, 60000))->assertOk();

        $bonus = BonusTransaction::query()->where('user_id', $sponsor->id)->where('bonus_type', 'referral')->firstOrFail();

        $this->assertSame('5000.00', $bonus->amount);
        $this->assertSame('package_activation', $bonus->metadata['source']);
    }

    public function test_elite_upgrade_does_not_create_referral_bonus(): void
    {
        $this->enableTipTopPay();
        $vip = $this->package('VIP');
        $elite = $this->package('ELITE');
        $sponsor = User::factory()->create();
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'current_package_id' => $vip->id,
            'total_pv' => 300,
        ]);

        Sanctum::actingAs($user);
        $externalId = $this->postJson("/api/dashboard/package/{$elite->id}/payments/tiptoppay/intent", [
            'package_code' => 'ELITE',
            'upgrade_from' => 'VIP',
        ])
            ->assertOk()
            ->json('external_id');

        $this->postJson('/api/payments/tiptoppay/pay', $this->webhookPayload($externalId, 120000))->assertOk();

        $this->assertSame($elite->id, $user->refresh()->current_package_id);
        $this->assertSame('500.00', $user->total_pv);
        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'referral')->count());
    }

    public function test_repeated_pay_webhook_does_not_duplicate_package_activation(): void
    {
        $this->enableTipTopPay();
        $start = $this->package('START');
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $externalId = $this->postJson("/api/dashboard/package/{$start->id}/payments/tiptoppay/intent", ['package_code' => 'START'])
            ->assertOk()
            ->json('external_id');
        $payload = $this->webhookPayload($externalId, 60000);

        $this->postJson('/api/payments/tiptoppay/pay', $payload)->assertOk()->assertJsonPath('code', 0);
        $this->postJson('/api/payments/tiptoppay/pay', $payload)->assertOk()->assertJsonPath('code', 0);

        $this->assertSame(1, WalletTransaction::query()->where('user_id', $user->id)->where('type', 'package_activation')->count());
        $this->assertSame('100.00', $user->refresh()->total_pv);
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
